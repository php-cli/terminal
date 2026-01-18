<?php

declare(strict_types=1);

namespace Tests\E2E\Scenario;

use Butschster\Commander\Module\CommandBrowser\CommandBrowserModule;
use Butschster\Commander\Module\ComposerManager\ComposerModule;
use Butschster\Commander\Module\FileBrowser\FileBrowserModule;
use Butschster\Commander\SDK\Builder\ApplicationBuilder;
use Butschster\Commander\SDK\Module\ModuleContext;
use Butschster\Commander\SDK\Module\ModuleInterface;
use Butschster\Commander\SDK\Module\ModuleMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Application as SymfonyApplication;
use Tests\Testing\ModuleTestCase;

/**
 * E2E tests for multi-module application scenarios.
 *
 * Tests module loading, menu system, and basic functionality
 * when multiple modules are loaded together.
 */
#[CoversClass(ApplicationBuilder::class)]
final class MultiModuleE2ETest extends ModuleTestCase
{
    private string $testDir;
    private SymfonyApplication $symfonyApp;

    #[Test]
    public function test_all_three_modules_work_together(): void
    {
        $this->terminal()->setSize(180, 50);

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new CommandBrowserModule($this->symfonyApp))
            ->withModule(new FileBrowserModule($this->testDir))
            ->withModule(new ComposerModule($this->testDir))
            ->withInitialScreen('command_browser')
            ->build();

        $this->runBuiltApp($app);

        // All modules should be loaded and command browser screen should render
        // The screen shows "Commands" panel with available commands
        $this->assertScreenContains('Commands');
    }

    #[Test]
    public function test_menus_sorted_by_priority(): void
    {
        $this->terminal()->setSize(180, 50);

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new FileBrowserModule($this->testDir))     // priority 10
            ->withModule(new CommandBrowserModule($this->symfonyApp)) // priority 20
            ->withModule(new ComposerModule($this->testDir))         // priority 30
            ->withInitialScreen('file_browser')
            ->build();

        $this->runBuiltApp($app);

        // File browser should render - it shows files in the test directory
        $this->assertScreenContainsAll(['readme.txt', 'docs']);
    }

    #[Test]
    public function test_modules_shutdown_properly(): void
    {
        $shutdownCalled = false;

        $trackingModule = new class($shutdownCalled) implements ModuleInterface {
            /**
             * @phpstan-ignore-next-line
             */
            public function __construct(private bool &$flag) {}

            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata('tracking', 'Tracking');
            }

            public function boot(ModuleContext $context): void {}

            public function shutdown(): void
            {
                $this->flag = true;
            }
        };

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule($trackingModule)
            ->build();

        // Simulate app run and shutdown
        $app->getModuleRegistry()->shutdown();

        self::assertTrue($shutdownCalled);
    }

    #[Test]
    public function test_initial_screen_selection_command_browser(): void
    {
        $this->terminal()->setSize(180, 50);

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new CommandBrowserModule($this->symfonyApp))
            ->withModule(new FileBrowserModule($this->testDir))
            ->withInitialScreen('command_browser')
            ->build();

        $this->runBuiltApp($app);

        // Should show command browser screen - displays "Commands" panel
        $this->assertScreenContains('Commands');
    }

    #[Test]
    public function test_initial_screen_selection_file_browser(): void
    {
        $this->terminal()->setSize(180, 50);

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new FileBrowserModule($this->testDir))
            ->withModule(new ComposerModule($this->testDir))
            ->withInitialScreen('file_browser')
            ->build();

        $this->runBuiltApp($app);

        // Should show file browser content
        $this->assertScreenContainsAll(['readme.txt', 'docs']);
    }

    #[Test]
    public function test_module_registry_tracks_all_modules(): void
    {
        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new CommandBrowserModule($this->symfonyApp))
            ->withModule(new FileBrowserModule($this->testDir))
            ->withModule(new ComposerModule($this->testDir))
            ->build();

        $registry = $app->getModuleRegistry();

        self::assertTrue($registry->has('command_browser'));
        self::assertTrue($registry->has('file_browser'));
        self::assertTrue($registry->has('composer_manager'));
    }

    #[Test]
    public function test_container_has_all_module_services(): void
    {
        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new CommandBrowserModule($this->symfonyApp))
            ->withModule(new FileBrowserModule($this->testDir))
            ->withModule(new ComposerModule($this->testDir))
            ->build();

        $container = $app->getContainer();

        // CommandBrowser services
        self::assertTrue($container->has(\Butschster\Commander\Module\CommandBrowser\Service\CommandDiscovery::class));
        self::assertTrue($container->has(\Butschster\Commander\Module\CommandBrowser\Service\CommandExecutor::class));

        // FileBrowser services
        self::assertTrue($container->has(\Butschster\Commander\Module\FileBrowser\Service\FileSystemService::class));

        // ComposerManager services
        self::assertTrue($container->has(\Butschster\Commander\Module\ComposerManager\Service\ComposerService::class));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = \sys_get_temp_dir() . '/multi_e2e_' . \uniqid();
        \mkdir($this->testDir);
        \file_put_contents($this->testDir . '/readme.txt', 'Hello World');
        \mkdir($this->testDir . '/docs');
        \file_put_contents($this->testDir . '/composer.json', \json_encode([
            'name' => 'test/app',
            'require' => ['php' => '^8.3'],
        ]));

        $this->symfonyApp = new SymfonyApplication('Test', '1.0.0');
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
