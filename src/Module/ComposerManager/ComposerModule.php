<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\ComposerManager;

use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;
use Butschster\Commander\Module\ComposerManager\Screen\ComposerManagerScreen;
use Butschster\Commander\Module\ComposerManager\Service\ComposerService;
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

/**
 * Composer Manager Module
 *
 * Provides Composer package management UI.
 *
 * Screens:
 * - composer_manager: Package list, outdated, security audit
 *
 * Services:
 * - ComposerService: Composer operations
 */
final readonly class ComposerModule implements
    ModuleInterface,
    ServiceProviderInterface,
    ScreenProviderInterface,
    MenuProviderInterface
{
    /**
     * @param string $projectPath Path to project with composer.json
     */
    public function __construct(
        private string $projectPath,
    ) {}

    #[\Override]
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata(
            name: 'composer_manager',
            title: 'Composer Manager',
            version: '1.0.0',
        );
    }

    #[\Override]
    public function services(): iterable
    {
        yield ServiceDefinition::singleton(
            ComposerService::class,
            fn() => new ComposerService($this->projectPath),
        );
    }

    #[\Override]
    public function screens(ContainerInterface $container): iterable
    {
        yield new ComposerManagerScreen(
            $container->get(ComposerService::class),
        );
    }

    #[\Override]
    public function menus(): iterable
    {
        yield new MenuDefinition(
            label: 'Composer',
            fkey: KeyCombination::fromString('F3'),
            items: [
                ScreenMenuItem::create('Package Manager', 'composer_manager', 'p'),
            ],
            priority: 30,
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
