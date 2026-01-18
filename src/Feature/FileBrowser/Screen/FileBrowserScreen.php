<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\FileBrowser\Screen;

use Butschster\Commander\Feature\FileBrowser\Component\FileListComponent;
use Butschster\Commander\Feature\FileBrowser\Component\FilePreviewComponent;
use Butschster\Commander\Feature\FileBrowser\Service\FileSystemService;
use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Layout\Panel;
use Butschster\Commander\UI\Component\Layout\StatusBar;
use Butschster\Commander\UI\Component\Container\SplitLayout;
use Butschster\Commander\UI\Component\Container\StackLayout;
use Butschster\Commander\UI\Component\Container\Direction;
use Butschster\Commander\UI\Screen\Attribute\Metadata;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\UI\Screen\ScreenManager;

/**
 * File browser screen with MC-style dual-panel layout
 *
 * Left panel: File list with metadata (name, size, date)
 * Right panel: File preview or directory information
 */
#[Metadata(
    name: 'file_browser',
    title: 'File Browser',
    description: 'Browse and manage files and directories',
    category: 'files',
    priority: 10,
)]
final class FileBrowserScreen implements ScreenInterface
{
    private string $currentPath;
    private readonly string $initialPath;
    private bool $leftPanelFocused = true;
    private StatusBar $statusBar;
    private StackLayout $rootLayout;
    private Panel $leftPanel;
    private Panel $rightPanel;
    private FileListComponent $fileList;
    private FilePreviewComponent $filePreview;

    public function __construct(
        private readonly FileSystemService $fileSystem,
        private readonly ScreenManager $screenManager,
        ?string $initialPath = null,
    ) {
        $this->initialPath = $initialPath ?? \getcwd();
        $this->currentPath = $this->initialPath;

        $this->initializeComponents();
    }

    #[\Override]
    public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = 120, ?int $height = 64): void
    {
        // Get actual size if not provided
        if ($width === null || $height === null) {
            $size = $renderer->getSize();
            $width ??= $size['width'] - $x;
            $height ??= $size['height'] - $y;
        }

        // Update focus state
        $this->leftPanel->setFocused($this->leftPanelFocused);
        $this->rightPanel->setFocused(!$this->leftPanelFocused);

        // Render layout at provided position
        $this->rootLayout->render(
            $renderer,
            $x,
            $y,
            $width,
            $height,
        );
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        $input = KeyInput::from($key);

        // Handle Tab - switch panels
        if ($input->is(Key::TAB)) {
            $this->switchPanel();
            return true;
        }

        // Handle Escape
        if ($input->is(Key::ESCAPE)) {
            return $this->handleEscape();
        }

        // Handle Ctrl+E - View/open file in full-screen viewer
        if ($input->isCtrl(Key::E)) {
            if ($this->leftPanelFocused) {
                $selectedItem = $this->fileList->getSelectedItem();
                if ($selectedItem !== null && !$selectedItem['isDir']) {
                    $this->openFileViewer($selectedItem['path']);
                }
            }
            return true;
        }

        // Handle Ctrl+G - Return to initial working directory
        if ($input->isCtrl(Key::G)) {
            if ($this->currentPath !== $this->initialPath) {
                $this->setCurrentPath($this->initialPath);
            }
            return true;
        }

        // Delegate to focused panel
        if ($this->leftPanelFocused) {
            return $this->fileList->handleInput($key);
        }

        return $this->filePreview->handleInput($key);
    }

    #[\Override]
    public function onActivate(): void
    {
        // Refresh directory listing when screen becomes active
        $this->fileList->setDirectory($this->currentPath);
    }

    #[\Override]
    public function onDeactivate(): void
    {
        // Nothing to do on deactivate
    }

    #[\Override]
    public function update(): void
    {
        // Update components if needed
        $this->fileList->update();
        $this->filePreview->update();
    }

    #[\Override]
    public function getTitle(): string
    {
        return 'File Browser';
    }

    /**
     * Get current directory path
     */
    public function getCurrentPath(): string
    {
        return $this->currentPath;
    }

    /**
     * Set current directory path
     */
    public function setCurrentPath(string $path): void
    {
        if (\is_dir($path)) {
            $this->currentPath = $path;
            $this->fileList->setDirectory($this->currentPath);
            $this->leftPanel->setTitle($this->getCurrentPathDisplay());

            $selectedItem = $this->fileList->getSelectedItem();
            if ($selectedItem !== null) {
                $this->filePreview->setFileInfo($selectedItem['path']);
            }
        }
    }

    private function handleEscape(): bool
    {
        // If right panel (preview) is focused, return focus to left panel
        if (!$this->leftPanelFocused) {
            $this->leftPanelFocused = true;
            $this->leftPanel->setFocused(true);
            $this->fileList->setFocused(true);
            $this->rightPanel->setFocused(false);
            $this->filePreview->setFocused(false);
            $this->updateStatusBar();
            return true;
        }

        // If left panel is focused, navigate up or exit
        // Go back (if not at root)
        if ($this->currentPath !== '/' && \dirname($this->currentPath) !== $this->currentPath) {
            $this->currentPath = \dirname($this->currentPath);
            $this->fileList->setDirectory($this->currentPath);
            $this->leftPanel->setTitle($this->getCurrentPathDisplay());

            $selectedItem = $this->fileList->getSelectedItem();
            if ($selectedItem !== null) {
                $this->filePreview->setFileInfo($selectedItem['path']);
            }
            return true;
        }

        // If at root, exit screen
        $this->screenManager->popScreen();
        return true;
    }

    /**
     * Initialize all UI components
     */
    private function initializeComponents(): void
    {
        // Status bar - will be updated after components are initialized
        $this->statusBar = new StatusBar([]);

        // File list component
        $this->fileList = new FileListComponent($this->fileSystem);
        $this->fileList->setDirectory($this->currentPath);
        $this->fileList->setFocused(true);

        // Set up file list callbacks
        $this->fileList->onSelect(function (array $item): void {
            $this->handleFileSelect($item);
        });

        $this->fileList->onChange(function (array $item): void {
            $this->handleSelectionChange($item);
        });

        // File preview component
        $this->filePreview = new FilePreviewComponent($this->fileSystem);
        $this->filePreview->setFocused(false);

        // Update preview for initially selected item
        $selectedItem = $this->fileList->getSelectedItem();
        if ($selectedItem !== null) {
            $this->filePreview->setFileInfo($selectedItem['path']);
        }

        // Left panel (file list)
        $this->leftPanel = new Panel($this->getCurrentPathDisplay(), $this->fileList);
        $this->leftPanel->setFocused(true);

        // Right panel (file preview) - padding is now handled internally by the component
        $this->rightPanel = new Panel('Preview', $this->filePreview);
        $this->rightPanel->setFocused(false);

        // Now that all components are initialized, set initial status bar hints
        $this->updateStatusBar();

        // NEW: Build layout structure using SplitLayout (50/50 split)
        $mainArea = SplitLayout::horizontal(
            left: $this->leftPanel,
            right: $this->rightPanel,
            ratio: 0.5,  // 50/50 split
        );

        // Root layout: vertical stack (content + status bar)
        $this->rootLayout = new StackLayout(Direction::VERTICAL);
        $this->rootLayout->addChild($mainArea);  // Takes remaining space
        $this->rootLayout->addChild($this->statusBar, size: 1);
    }

    /**
     * Update status bar based on current screen state
     */
    private function updateStatusBar(): void
    {
        $hints = [];

        if ($this->leftPanelFocused) {
            // Left panel (file list) is focused
            $selectedItem = $this->fileList->getSelectedItem();
            if ($selectedItem !== null && !$selectedItem['isDir']) {
                // File selected - Enter opens viewer
                $hints = [
                    '↑↓' => ' Navigate',
                    'Enter' => ' View',
                    'Ctrl+G' => ' Home',
                    'Tab' => ' Switch',
                    'ESC' => ' Back',
                ];
            } else {
                // Directory selected - Enter opens directory
                $hints = [
                    '↑↓' => ' Navigate',
                    'Enter' => ' Open',
                    'Ctrl+G' => ' Home',
                    'Tab' => ' Switch',
                    'ESC' => ' Back',
                ];
            }
        } else {
            // Right panel (preview) is focused
            $hints = [
                '↑↓' => ' Scroll',
                'PgUp/Dn' => ' Page',
                'Ctrl+G' => ' Home',
                'Tab' => ' Switch',
                'ESC' => ' Back',
            ];
        }

        $this->statusBar->setHints($hints);
    }

    /**
     * Get current path for display (truncated if needed)
     */
    private function getCurrentPathDisplay(): string
    {
        $maxLength = 40;
        if (\mb_strlen($this->currentPath) <= $maxLength) {
            return $this->currentPath;
        }

        return '...' . \mb_substr($this->currentPath, -($maxLength - 3));
    }

    /**
     * Handle file/directory selection (Enter key)
     */
    private function handleFileSelect(array $item): void
    {
        if ($item['isDir']) {
            // Navigate into directory
            $this->currentPath = $item['path'];
            $this->fileList->setDirectory($this->currentPath);
            $this->leftPanel->setTitle($this->getCurrentPathDisplay());

            // Update preview with info only
            $selectedItem = $this->fileList->getSelectedItem();
            if ($selectedItem !== null) {
                $this->filePreview->setFileInfo($selectedItem['path']);
            }
        } else {
            // Open file in viewer when Enter is pressed on a file
            $this->openFileViewer($item['path']);
        }
    }

    /**
     * Handle selection change (arrow keys)
     */
    private function handleSelectionChange(array $item): void
    {
        // Update preview panel with info only (don't read file contents)
        $this->filePreview->setFileInfo($item['path']);

        // Update status bar based on whether item is file or directory
        $this->updateStatusBar();
    }

    /**
     * Open file in full-screen viewer
     */
    private function openFileViewer(string $filePath): void
    {
        $viewerScreen = new FileViewerScreen($this->fileSystem, $this->screenManager, $filePath);
        $this->screenManager->pushScreen($viewerScreen);
    }

    /**
     * Switch focus between left and right panels
     */
    private function switchPanel(): void
    {
        $this->leftPanelFocused = !$this->leftPanelFocused;

        $this->leftPanel->setFocused($this->leftPanelFocused);
        $this->fileList->setFocused($this->leftPanelFocused);

        $this->rightPanel->setFocused(!$this->leftPanelFocused);
        $this->filePreview->setFocused(!$this->leftPanelFocused);

        // Update status bar to reflect new context
        $this->updateStatusBar();
    }
}
