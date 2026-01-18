<?php

declare(strict_types=1);

namespace Tests\Integration\Module;

use Butschster\Commander\Module\ComposerManager\ComposerModule;
use Butschster\Commander\Module\ComposerManager\Screen\ComposerManagerScreen;
use Butschster\Commander\Module\ComposerManager\Service\ComposerService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Testing\ModuleTestCase;

#[CoversClass(ComposerModule::class)]
final class ComposerModuleTest extends ModuleTestCase
{
    private string $testDir;

    #[Test]
    public function test_module_metadata(): void
    {
        $module = new ComposerModule($this->testDir);
        $metadata = $module->metadata();

        self::assertSame('composer_manager', $metadata->name);
        self::assertSame('Composer Manager', $metadata->title);
        self::assertSame('1.0.0', $metadata->version);
        self::assertEmpty($metadata->dependencies);
    }

    #[Test]
    public function test_provides_composer_service(): void
    {
        $module = new ComposerModule($this->testDir);
        $services = \iterator_to_array($module->services());

        self::assertCount(1, $services);
        self::assertSame(ComposerService::class, $services[0]->id);
        self::assertTrue($services[0]->singleton);
    }

    #[Test]
    public function test_provides_composer_manager_screen(): void
    {
        $module = new ComposerModule($this->testDir);
        $this->bootModule($module);

        $screens = \iterator_to_array($module->screens($this->container));

        self::assertCount(1, $screens);
        self::assertInstanceOf(ComposerManagerScreen::class, $screens[0]);
    }

    #[Test]
    public function test_provides_composer_menu(): void
    {
        $module = new ComposerModule($this->testDir);
        $menus = \iterator_to_array($module->menus());

        self::assertCount(1, $menus);
        self::assertSame('Composer', $menus[0]->label);
        self::assertSame('F3', (string) $menus[0]->fkey);
        self::assertSame(30, $menus[0]->priority);
    }

    #[Test]
    public function test_composer_service_is_singleton(): void
    {
        $module = new ComposerModule($this->testDir);
        $container = $this->bootModule($module);

        $first = $container->get(ComposerService::class);
        $second = $container->get(ComposerService::class);

        self::assertSame($first, $second);
    }

    #[Test]
    public function test_screen_receives_correct_dependencies(): void
    {
        $module = new ComposerModule($this->testDir);
        $container = $this->bootModule($module);

        $screens = \iterator_to_array($module->screens($container));
        $screen = $screens[0];

        // Verify screen is functional
        self::assertSame('Composer Manager', $screen->getTitle());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = \sys_get_temp_dir() . '/composer_module_test_' . \uniqid();
        \mkdir($this->testDir);

        // Create minimal composer.json
        \file_put_contents($this->testDir . '/composer.json', \json_encode([
            'name' => 'test/project',
            'require' => ['php' => '^8.3'],
        ]));
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
