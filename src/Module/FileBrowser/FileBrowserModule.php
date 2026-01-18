<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\FileBrowser;

use Butschster\Commander\Infrastructure\Keyboard\KeyBinding;
use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;
use Butschster\Commander\Module\FileBrowser\Screen\FileBrowserScreen;
use Butschster\Commander\Module\FileBrowser\Screen\FileViewerScreen;
use Butschster\Commander\Module\FileBrowser\Service\FileSystemService;
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
 * File Browser Module
 *
 * Provides file system browsing and viewing capabilities.
 *
 * Screens:
 * - file_browser: Main dual-panel file browser (MC-style)
 * - file_viewer: Full-screen file content viewer
 *
 * Services:
 * - FileSystemService: File system operations
 */
final readonly class FileBrowserModule implements
    ModuleInterface,
    ServiceProviderInterface,
    ScreenProviderInterface,
    MenuProviderInterface,
    KeyBindingProviderInterface
{
    /**
     * @param string|null $initialPath Initial directory path (defaults to cwd)
     */
    public function __construct(
        private ?string $initialPath = null,
    ) {}

    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata(
            name: 'file_browser',
            title: 'File Browser',
            version: '1.0.0',
        );
    }

    public function services(): iterable
    {
        yield ServiceDefinition::singleton(
            FileSystemService::class,
            static fn() => new FileSystemService(),
        );
    }

    public function screens(ContainerInterface $container): iterable
    {
        $fileSystem = $container->get(FileSystemService::class);
        $screenManager = $container->get(ScreenManager::class);

        // File Browser Screen (main screen)
        yield new FileBrowserScreen(
            fileSystem: $fileSystem,
            screenManager: $screenManager,
            initialPath: $this->initialPath ?? \getcwd(),
        );

        // File Viewer Screen (opened via Ctrl+E from browser)
        // Note: FileViewerScreen is typically created dynamically when opening a file
        // We register a "template" instance here for the registry
        yield new FileViewerScreen(
            fileSystem: $fileSystem,
            screenManager: $screenManager,
            filePath: '', // Placeholder - actual path set when opened
        );
    }

    public function menus(): iterable
    {
        yield new MenuDefinition(
            label: 'Files',
            fkey: KeyCombination::fromString('F1'),
            items: [
                ScreenMenuItem::create('File Browser', 'file_browser', 'b'),
                // Note: FileViewerScreen is not in menu - it's opened via Ctrl+E from file browser
            ],
            priority: 10,
        );
    }

    public function keyBindings(): iterable
    {
        yield new KeyBinding(
            combination: KeyCombination::fromString('Ctrl+O'),
            actionId: 'files.open_browser',
            description: 'Open file browser',
            category: 'files',
        );
    }

    public function boot(ModuleContext $context): void
    {
        // No additional initialization needed
        // Services are registered via services()
        // Screens are registered via screens()
    }

    public function shutdown(): void
    {
        // No cleanup needed
    }
}
