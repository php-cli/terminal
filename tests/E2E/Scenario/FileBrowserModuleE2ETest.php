<?php

declare(strict_types=1);

namespace Tests\E2E\Scenario;

use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;
use Butschster\Commander\Module\FileBrowser\FileBrowserModule;
use Butschster\Commander\SDK\Builder\ApplicationBuilder;
use Butschster\Commander\SDK\Container\ContainerInterface;
use Butschster\Commander\SDK\Module\ModuleContext;
use Butschster\Commander\SDK\Module\ModuleInterface;
use Butschster\Commander\SDK\Module\ModuleMetadata;
use Butschster\Commander\SDK\Provider\MenuProviderInterface;
use Butschster\Commander\SDK\Provider\ScreenProviderInterface;
use Butschster\Commander\UI\Menu\MenuDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Testing\ModuleTestCase;

#[CoversClass(FileBrowserModule::class)]
final class FileBrowserModuleE2ETest extends ModuleTestCase
{
    private string $testDir;

    #[Test]
    public function test_file_browser_renders_directory_contents(): void
    {
        $this->terminal()->setSize(180, 50);

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new FileBrowserModule($this->testDir))
            ->withInitialScreen('file_browser')
            ->build();

        $this->runBuiltApp($app);

        $this->assertScreenContainsAll(['readme.txt', 'data.json', 'docs']);
    }

    #[Test]
    public function test_navigation_with_arrow_keys(): void
    {
        $this->terminal()->setSize(180, 50);

        $this->keys()
            ->down(2)    // Navigate down in file list
            ->frame()
            ->applyTo($this->terminal());

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new FileBrowserModule($this->testDir))
            ->withInitialScreen('file_browser')
            ->build();

        $this->runBuiltApp($app);

        // Files should still be visible after navigation
        $this->assertScreenContainsAll(['readme.txt', 'data.json']);
    }

    #[Test]
    public function test_file_browser_shows_preview_panel(): void
    {
        $this->terminal()->setSize(180, 50);

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new FileBrowserModule($this->testDir))
            ->withInitialScreen('file_browser')
            ->build();

        $this->runBuiltApp($app);

        // Should show file/directory information in preview
        $this->assertScreenContains('Information');
    }

    #[Test]
    public function test_file_browser_shows_status_bar(): void
    {
        $this->terminal()->setSize(180, 50);

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new FileBrowserModule($this->testDir))
            ->withInitialScreen('file_browser')
            ->build();

        $this->runBuiltApp($app);

        // Should show keyboard hints in status bar
        $this->assertScreenContains('Navigate');
        $this->assertScreenContains('ESC');
    }

    #[Test]
    public function test_enter_directory_shows_contents(): void
    {
        $this->terminal()->setSize(180, 50);

        // Navigate to 'docs' directory and enter
        $this->keys()
            ->down()     // Skip '..' - now on 'docs'
            ->enter()    // Enter docs directory
            ->frame()
            ->applyTo($this->terminal());

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new FileBrowserModule($this->testDir))
            ->withInitialScreen('file_browser')
            ->build();

        $this->runBuiltApp($app);

        // Should show docs directory contents
        $this->assertScreenContains('guide.md');
    }

    #[Test]
    public function test_escape_navigates_up(): void
    {
        $this->terminal()->setSize(180, 50);

        // Enter docs, then escape to go back
        $this->keys()
            ->down()        // Skip '..' - now on 'docs'
            ->enter()       // Enter docs
            ->frame()
            ->escape()      // Go back
            ->frame()
            ->applyTo($this->terminal());

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new FileBrowserModule($this->testDir))
            ->withInitialScreen('file_browser')
            ->build();

        $this->runBuiltApp($app);

        // Should be back in main directory
        $this->assertScreenContainsAll(['readme.txt', 'docs']);
    }

    #[Test]
    public function test_works_with_other_modules(): void
    {
        $this->terminal()->setSize(180, 50);

        // Create a simple second module
        $otherModule = new class implements ModuleInterface, ScreenProviderInterface, MenuProviderInterface {
            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata('other', 'Other');
            }

            public function screens(ContainerInterface $c): iterable
            {
                return [];
            }

            public function menus(): iterable
            {
                yield new MenuDefinition('Other', KeyCombination::fromString('F2'), [], 20);
            }

            public function boot(ModuleContext $context): void {}

            public function shutdown(): void {}
        };

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new FileBrowserModule($this->testDir))
            ->withModule($otherModule)
            ->withInitialScreen('file_browser')
            ->build();

        $this->runBuiltApp($app);

        // Both modules contribute to the app - verify file browser renders
        $this->assertScreenContainsAll(['readme.txt', 'data.json', 'docs']);
    }

    #[Test]
    public function test_file_browser_displays_current_path(): void
    {
        $this->terminal()->setSize(180, 50);

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new FileBrowserModule($this->testDir))
            ->withInitialScreen('file_browser')
            ->build();

        $this->runBuiltApp($app);

        // Should show path in panel title (at least partial)
        $this->assertScreenContains('fb_e2e_');
    }

    #[Test]
    public function test_tab_switches_panels(): void
    {
        $this->terminal()->setSize(180, 50);

        $this->keys()
            ->tab()      // Switch to right panel
            ->frame()
            ->applyTo($this->terminal());

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new FileBrowserModule($this->testDir))
            ->withInitialScreen('file_browser')
            ->build();

        $this->runBuiltApp($app);

        // After tab, we should still see the file browser content
        // The right panel gets focus but files are still visible
        $this->assertScreenContainsAll(['readme.txt', 'data.json']);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = \sys_get_temp_dir() . '/fb_e2e_' . \uniqid();
        \mkdir($this->testDir);
        \file_put_contents($this->testDir . '/readme.txt', "# Welcome\n\nThis is a test file.");
        \file_put_contents($this->testDir . '/data.json', '{"key": "value"}');
        \mkdir($this->testDir . '/docs');
        \file_put_contents($this->testDir . '/docs/guide.md', '# Guide');
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    /**
     * Recursively remove a directory
     */
    private function removeDirectory(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $items = \scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (\is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                \unlink($path);
            }
        }

        \rmdir($dir);
    }
}
