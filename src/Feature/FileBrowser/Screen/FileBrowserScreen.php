<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\FileBrowser\Screen;

use Butschster\Commander\Feature\FileBrowser\Component\FileListComponent;
use Butschster\Commander\Feature\FileBrowser\Component\FilePreviewComponent;
use Butschster\Commander\Feature\FileBrowser\Service\FileSystemService;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Layout\Panel;
use Butschster\Commander\UI\Component\Layout\StatusBar;
use Butschster\Commander\UI\Component\Container\SplitLayout;
use Butschster\Commander\UI\Component\Container\StackLayout;
use Butschster\Commander\UI\Component\Container\Direction;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\UI\Screen\ScreenManager;

/**
 * File browser screen with MC-style dual-panel layout
 *
 * Left panel: File list with metadata (name, size, date)
 * Right panel: File preview or directory information
 */
final class FileBrowserScreen implements ScreenInterface
{
    private string $currentPath;
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
        $this->currentPath = $initialPath ?? \getcwd();

        $this->initializeComponents();
    }

    public function render(Renderer $renderer): void
    {
        $size = $renderer->getSize();
        $width = $size['width'];
        $height = $size['height'];

        // Update focus state
        $this->leftPanel->setFocused($this->leftPanelFocused);
        $this->rightPanel->setFocused(!$this->leftPanelFocused);

        // NEW: Single render call! Layout system handles all positioning
        $this->rootLayout->render(
            $renderer,
            0,
            1,  // x, y (account for global menu bar at top)
            $width,
            $height - 1,  // Account for global menu bar
        );
    }

    public function handleInput(string $key): bool
    {
        // Global shortcuts
        switch ($key) {
            case 'F10':
            case 'CTRL_C':
                // Quit
                $this->screenManager->popScreen();
                return true;

            case 'F4':
                // View/open file
                if ($this->leftPanelFocused) {
                    $selectedItem = $this->fileList->getSelectedItem();
                    if ($selectedItem !== null) {
                        $this->handleFileView($selectedItem);

                        // Switch focus to right panel
                        $this->leftPanelFocused = false;
                        $this->leftPanel->setFocused(false);
                        $this->fileList->setFocused(false);
                        $this->rightPanel->setFocused(true);
                        $this->filePreview->setFocused(true);
                        $this->updateStatusBar();
                    }
                }
                return true;

            case 'TAB':
                // Switch between panels
                $this->switchPanel();
                return true;

            case 'ESCAPE':
                // If right panel (preview) is focused, close preview and return to left panel
                if (!$this->leftPanelFocused) {
                    // Close preview: show info only instead of full contents
                    $selectedItem = $this->fileList->getSelectedItem();
                    if ($selectedItem !== null) {
                        $this->filePreview->setFileInfo($selectedItem['path']);
                    }

                    // Return focus to left panel
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

        // Delegate to focused panel
        if ($this->leftPanelFocused) {
            return $this->fileList->handleInput($key);
        }

        return $this->filePreview->handleInput($key);
    }

    public function onActivate(): void
    {
        // Refresh directory listing when screen becomes active
        $this->fileList->setDirectory($this->currentPath);
    }

    public function onDeactivate(): void
    {
        // Nothing to do on deactivate
    }

    public function update(): void
    {
        // Update components if needed
        $this->fileList->update();
        $this->filePreview->update();
    }

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

        // Right panel (file preview)
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
                $hints = [
                    '↑↓' => ' Navigate',
                    'Enter' => ' Open Dir',
                    'F4' => ' View File',
                    'Tab' => ' Switch',
                    'ESC' => ' Back',
                ];
            } else {
                $hints = [
                    '↑↓' => ' Navigate',
                    'Enter' => ' Open',
                    'Tab' => ' Switch',
                    'ESC' => ' Back',
                ];
            }
        } else {
            // Right panel (preview) is focused
            $hints = [
                '↑↓' => ' Scroll',
                'PgUp/Dn' => ' Page',
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
        }
        // For files, just show metadata (already shown in right panel)
        // File content viewing is handled by F4 key
    }

    /**
     * Open/view file (F4 key)
     */
    private function handleFileView(array $item): void
    {
        if (!$item['isDir']) {
            // Open file for viewing - currently just updates preview
            // In the future, this could open a full-screen viewer
            $this->filePreview->setFile($item['path']);
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
