<?php

declare(strict_types=1);

namespace Tests\Integration\Module;

use Butschster\Commander\Module\CommandBrowser\CommandBrowserModule;
use Butschster\Commander\Module\CommandBrowser\Screen\CommandsScreen;
use Butschster\Commander\Module\CommandBrowser\Service\CommandDiscovery;
use Butschster\Commander\Module\CommandBrowser\Service\CommandExecutor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Application as SymfonyApplication;
use Tests\Testing\ModuleTestCase;

#[CoversClass(CommandBrowserModule::class)]
final class CommandBrowserModuleTest extends ModuleTestCase
{
    private SymfonyApplication $symfonyApp;

    #[Test]
    public function test_module_metadata(): void
    {
        $module = new CommandBrowserModule($this->symfonyApp);
        $metadata = $module->metadata();

        self::assertSame('command_browser', $metadata->name);
        self::assertSame('Command Browser', $metadata->title);
        self::assertSame('1.0.0', $metadata->version);
        self::assertEmpty($metadata->dependencies);
    }

    #[Test]
    public function test_provides_command_services(): void
    {
        $module = new CommandBrowserModule($this->symfonyApp);
        $services = \iterator_to_array($module->services());

        self::assertCount(2, $services);

        $ids = \array_map(static fn($s) => $s->id, $services);
        self::assertContains(CommandDiscovery::class, $ids);
        self::assertContains(CommandExecutor::class, $ids);
    }

    #[Test]
    public function test_provides_commands_screen(): void
    {
        $module = new CommandBrowserModule($this->symfonyApp);
        $this->bootModule($module);

        $screens = \iterator_to_array($module->screens($this->container));

        self::assertCount(1, $screens);
        self::assertInstanceOf(CommandsScreen::class, $screens[0]);
    }

    #[Test]
    public function test_provides_commands_menu(): void
    {
        $module = new CommandBrowserModule($this->symfonyApp);
        $menus = \iterator_to_array($module->menus());

        self::assertCount(1, $menus);
        self::assertSame('Commands', $menus[0]->label);
        self::assertSame('F2', (string) $menus[0]->fkey);
        self::assertSame(20, $menus[0]->priority);
    }

    #[Test]
    public function test_command_discovery_is_singleton(): void
    {
        $module = new CommandBrowserModule($this->symfonyApp);
        $container = $this->bootModule($module);

        $first = $container->get(CommandDiscovery::class);
        $second = $container->get(CommandDiscovery::class);

        self::assertSame($first, $second);
    }

    #[Test]
    public function test_command_executor_is_singleton(): void
    {
        $module = new CommandBrowserModule($this->symfonyApp);
        $container = $this->bootModule($module);

        $first = $container->get(CommandExecutor::class);
        $second = $container->get(CommandExecutor::class);

        self::assertSame($first, $second);
    }

    #[Test]
    public function test_screen_receives_correct_dependencies(): void
    {
        $module = new CommandBrowserModule($this->symfonyApp);
        $container = $this->bootModule($module);

        $screens = \iterator_to_array($module->screens($container));
        $screen = $screens[0];

        // Verify screen is functional
        self::assertSame('Command Browser', $screen->getTitle());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->symfonyApp = new SymfonyApplication('Test', '1.0.0');
    }
}
