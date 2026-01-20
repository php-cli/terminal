<?php

declare(strict_types=1);

namespace Tests\Integration\Module;

use Butschster\Commander\Module\FileBrowser\FileBrowserModule;
use Butschster\Commander\Module\FileBrowser\Screen\FileBrowserScreen;
use Butschster\Commander\Module\FileBrowser\Screen\FileViewerScreen;
use Butschster\Commander\Module\FileBrowser\Service\FileSystemService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Testing\ModuleTestCase;

#[CoversClass(FileBrowserModule::class)]
final class FileBrowserModuleTest extends ModuleTestCase
{
    private string $testDir;

    #[Test]
    public function test_module_metadata(): void
    {
        $module = new FileBrowserModule();
        $metadata = $module->metadata();

        self::assertSame('file_browser', $metadata->name);
        self::assertSame('File Browser', $metadata->title);
        self::assertSame('1.0.0', $metadata->version);
        self::assertEmpty($metadata->dependencies);
    }

    #[Test]
    public function test_provides_filesystem_service(): void
    {
        $module = new FileBrowserModule();
        $services = \iterator_to_array($module->services());

        self::assertCount(1, $services);
        self::assertSame(FileSystemService::class, $services[0]->id);
        self::assertTrue($services[0]->singleton);
    }

    #[Test]
    public function test_provides_two_screens(): void
    {
        $module = new FileBrowserModule($this->testDir);
        $this->bootModule($module);

        $screens = \iterator_to_array($module->screens($this->container));

        self::assertCount(2, $screens);
        self::assertInstanceOf(FileBrowserScreen::class, $screens[0]);
        self::assertInstanceOf(FileViewerScreen::class, $screens[1]);
    }

    #[Test]
    public function test_provides_files_menu(): void
    {
        $module = new FileBrowserModule();
        $menus = \iterator_to_array($module->menus());

        self::assertCount(1, $menus);
        self::assertSame('Files', $menus[0]->label);
        self::assertSame('F1', (string) $menus[0]->fkey);
        self::assertSame(10, $menus[0]->priority);
    }

    #[Test]
    public function test_provides_key_bindings(): void
    {
        $module = new FileBrowserModule();
        $bindings = \iterator_to_array($module->keyBindings());

        self::assertCount(1, $bindings);
        self::assertSame('files.open_browser', $bindings[0]->actionId);
    }

    #[Test]
    public function test_filesystem_service_is_singleton(): void
    {
        $module = new FileBrowserModule();
        $container = $this->bootModule($module);

        $first = $container->get(FileSystemService::class);
        $second = $container->get(FileSystemService::class);

        self::assertSame($first, $second);
    }

    #[Test]
    public function test_screens_receive_correct_dependencies(): void
    {
        $module = new FileBrowserModule($this->testDir);
        $container = $this->bootModule($module);

        $screens = \iterator_to_array($module->screens($container));
        $browserScreen = $screens[0];

        // Verify screen is functional
        self::assertSame('File Browser', $browserScreen->getTitle());
    }

    #[Test]
    public function test_initial_path_is_used(): void
    {
        $module = new FileBrowserModule($this->testDir);
        $container = $this->bootModule($module);

        $screens = \iterator_to_array($module->screens($container));
        $browserScreen = $screens[0];

        self::assertSame($this->testDir, $browserScreen->getCurrentPath());
    }

    #[Test]
    public function test_defaults_to_cwd_when_no_path(): void
    {
        $module = new FileBrowserModule();
        $container = $this->bootModule($module);

        $screens = \iterator_to_array($module->screens($container));
        $browserScreen = $screens[0];

        self::assertSame(\getcwd(), $browserScreen->getCurrentPath());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = \sys_get_temp_dir() . '/fb_module_test_' . \uniqid();
        \mkdir($this->testDir);
        \file_put_contents($this->testDir . '/test.txt', 'Hello World');
        \mkdir($this->testDir . '/subdir');
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
