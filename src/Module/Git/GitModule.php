<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\Git;

use Butschster\Commander\Infrastructure\Keyboard\KeyBinding;
use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;
use Butschster\Commander\Module\Git\Screen\GitScreen;
use Butschster\Commander\Module\Git\Service\GitService;
use Butschster\Commander\SDK\Container\ContainerInterface;
use Butschster\Commander\SDK\Container\ServiceDefinition;
use Butschster\Commander\SDK\Module\ModuleContext;
use Butschster\Commander\SDK\Module\ModuleInterface;
use Butschster\Commander\SDK\Module\ModuleMetadata;
use Butschster\Commander\SDK\Provider\KeyBindingProviderInterface;
use Butschster\Commander\SDK\Provider\MenuProviderInterface;
use Butschster\Commander\SDK\Provider\ScreenProviderInterface;
use Butschster\Commander\SDK\Provider\ServiceProviderInterface;
use Butschster\Commander\UI\Menu\MenuDefinition;
use Butschster\Commander\UI\Menu\ScreenMenuItem;
use Butschster\Commander\UI\Screen\ScreenManager;

/**
 * Git Module
 *
 * Provides Git repository browsing and management UI.
 *
 * Screens:
 * - git: Main git screen with tabs for status, branches, tags
 *
 * Services:
 * - GitService: Git command operations
 *
 * Features:
 * - View repository status (staged, unstaged, untracked files)
 * - View file diffs
 * - Browse and switch branches
 * - View tags
 */
final readonly class GitModule implements
    ModuleInterface,
    ServiceProviderInterface,
    ScreenProviderInterface,
    MenuProviderInterface,
    KeyBindingProviderInterface
{
    /**
     * @param string|null $repositoryPath Path to git repository (defaults to cwd)
     */
    public function __construct(
        private ?string $repositoryPath = null,
    ) {}

    #[\Override]
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata(
            name: 'git',
            title: 'Git',
            version: '1.0.0',
        );
    }

    #[\Override]
    public function services(): iterable
    {
        yield ServiceDefinition::singleton(
            GitService::class,
            fn() => new GitService($this->repositoryPath ?? \getcwd()),
        );
    }

    #[\Override]
    public function screens(ContainerInterface $container): iterable
    {
        yield new GitScreen(
            $container->get(GitService::class),
            $container->get(ScreenManager::class),
        );
    }

    #[\Override]
    public function menus(): iterable
    {
        yield new MenuDefinition(
            label: 'Git',
            fkey: KeyCombination::fromString('F4'),
            items: [
                ScreenMenuItem::create('Repository', 'git', 'g'),
            ],
            priority: 40,
        );
    }

    #[\Override]
    public function keyBindings(): iterable
    {
        yield new KeyBinding(
            combination: KeyCombination::fromString('Ctrl+G'),
            actionId: 'git.open',
            description: 'Open Git repository',
            category: 'git',
        );
    }

    #[\Override]
    public function boot(ModuleContext $context): void
    {
        // Module is ready
    }

    #[\Override]
    public function shutdown(): void
    {
        // No cleanup needed
    }
}
