<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\CommandBrowser;

use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;
use Butschster\Commander\Module\CommandBrowser\Screen\CommandsScreen;
use Butschster\Commander\Module\CommandBrowser\Service\CommandDiscovery;
use Butschster\Commander\Module\CommandBrowser\Service\CommandExecutor;
use Butschster\Commander\SDK\Container\ContainerInterface;
use Butschster\Commander\SDK\Container\ServiceDefinition;
use Butschster\Commander\SDK\Module\ModuleContext;
use Butschster\Commander\SDK\Module\ModuleInterface;
use Butschster\Commander\SDK\Module\ModuleMetadata;
use Butschster\Commander\SDK\Provider\MenuProviderInterface;
use Butschster\Commander\SDK\Provider\ScreenProviderInterface;
use Butschster\Commander\SDK\Provider\ServiceProviderInterface;
use Butschster\Commander\UI\Menu\MenuDefinition;
use Butschster\Commander\UI\Menu\ScreenMenuItem;
use Symfony\Component\Console\Application as SymfonyApplication;

/**
 * Command Browser Module
 *
 * Provides UI for browsing and executing Symfony Console commands.
 *
 * Screens:
 * - command_browser: Command list and execution
 *
 * Services:
 * - CommandDiscovery: Finds available commands
 * - CommandExecutor: Executes commands
 */
final readonly class CommandBrowserModule implements
    ModuleInterface,
    ServiceProviderInterface,
    ScreenProviderInterface,
    MenuProviderInterface
{
    public function __construct(
        private SymfonyApplication $symfonyApp,
    ) {}

    #[\Override]
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata(
            name: 'command_browser',
            title: 'Command Browser',
            version: '1.0.0',
        );
    }

    #[\Override]
    public function services(): iterable
    {
        yield ServiceDefinition::singleton(
            CommandDiscovery::class,
            fn() => new CommandDiscovery($this->symfonyApp),
        );

        yield ServiceDefinition::singleton(
            CommandExecutor::class,
            fn() => new CommandExecutor($this->symfonyApp),
        );
    }

    #[\Override]
    public function screens(ContainerInterface $container): iterable
    {
        yield new CommandsScreen(
            $container->get(CommandDiscovery::class),
            $container->get(CommandExecutor::class),
        );
    }

    #[\Override]
    public function menus(): iterable
    {
        yield new MenuDefinition(
            label: 'Commands',
            fkey: KeyCombination::fromString('F2'),
            items: [
                ScreenMenuItem::create('Command Browser', 'command_browser', 'c'),
            ],
            priority: 20,
        );
    }

    #[\Override]
    public function boot(ModuleContext $context): void
    {
        // Module is ready - screens and services are registered via provider interfaces
    }

    #[\Override]
    public function shutdown(): void
    {
        // No cleanup needed
    }
}
