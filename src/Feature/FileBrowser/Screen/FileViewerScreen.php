<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\FileBrowser\Screen;

use Butschster\Commander\Feature\FileBrowser\Component\FileContentViewer;
use Butschster\Commander\Feature\FileBrowser\Service\FileSystemService;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Container\StackLayout;
use Butschster\Commander\UI\Component\Container\Direction;
use Butschster\Commander\UI\Component\Layout\StatusBar;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\UI\Screen\ScreenManager;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Full-screen file viewer
 *
 * Opens files in a dedicated full-screen view with:
 * - Line numbers (dynamically sized based on total lines)
 * - Smooth scrolling
 * - File metadata header
 * - ESC to close and return to browser
 */
final class FileViewerScreen implements ScreenInterface
{
    private StackLayout $rootLayout;
    private FileContentViewer $contentViewer;
    private StatusBar $statusBar;
    private ?array $metadata = null;

    public function __construct(
        private readonly FileSystemService $fileSystem,
        private readonly ScreenManager $screenManager,
        private readonly string $filePath,
    ) {
        $this->initializeComponents();
        $this->loadFile();
    }

    public function render(Renderer $renderer): void
    {
        $size = $renderer->getSize();
        $width = $size['width'];
        $height = $size['height'];

        // Render file info header (2 lines)
        $this->renderHeader($renderer, 0, 1, $width);

        // Render content viewer
        $contentY = 3;
        $contentHeight = $height - 4; // Account for: global menu (1) + header (2) + status bar (1)

        $this->contentViewer->render($renderer, 0, $contentY, $width, $contentHeight);

        // Render status bar at bottom
        $this->statusBar->render($renderer, 0, $height - 1, $width, 1);
    }

    public function handleInput(string $key): bool
    {
        switch ($key) {
            case 'ESCAPE':
            case 'F10':
                // Close viewer and return to browser
                $this->screenManager->popScreen();
                return true;

            default:
                // Delegate to content viewer for scrolling
                return $this->contentViewer->handleInput($key);
        }
    }

    public function onActivate(): void
    {
        $this->contentViewer->setFocused(true);
    }

    public function onDeactivate(): void
    {
        $this->contentViewer->setFocused(false);
    }

    public function update(): void
    {
        // Nothing to update
    }

    public function getTitle(): string
    {
        return 'File Viewer';
    }

    /**
     * Get current file path
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Initialize UI components
     */
    private function initializeComponents(): void
    {
        // Content viewer
        $this->contentViewer = new FileContentViewer();
        $this->contentViewer->setFocused(true);

        // Status bar with viewer shortcuts
        $this->statusBar = new StatusBar([
            '↑↓' => ' Scroll',
            'PgUp/Dn' => ' Page',
            'Home/End' => ' Top/Bottom',
            'ESC' => ' Close',
        ]);

        // Root layout: vertical stack (content + status bar)
        $this->rootLayout = new StackLayout(Direction::VERTICAL);
        $this->rootLayout->addChild($this->contentViewer); // Takes remaining space
        $this->rootLayout->addChild($this->statusBar, size: 1);
    }

    /**
     * Load file contents and metadata
     */
    private function loadFile(): void
    {
        // Get metadata
        $this->metadata = $this->fileSystem->getFileMetadata($this->filePath);

        // Check if file is readable
        if (!\is_readable($this->filePath)) {
            $this->contentViewer->setContent("Error: File is not readable");
            return;
        }

        // Check if file is binary
        if ($this->metadata !== null && \str_contains($this->metadata['mimeType'], 'octet-stream')) {
            $info = $this->getBinaryFileMessage();
            $this->contentViewer->setContent($info);
            return;
        }

        // Load file contents (no line limit for full viewer)
        $contents = $this->fileSystem->readFileContents($this->filePath, maxLines: 0);
        $this->contentViewer->setContent($contents);
    }

    /**
     * Render file information header (2 lines)
     */
    private function renderHeader(Renderer $renderer, int $x, int $y, int $width): void
    {
        // Line 1: File name
        $fileName = \basename($this->filePath);
        $line1 = ' File: ' . $fileName;
        $line1 = \str_pad(\mb_substr($line1, 0, $width), $width);
        $renderer->writeAt(
            $x,
            $y,
            $line1,
            ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::FG_BLACK, ColorScheme::BOLD),
        );

        // Line 2: File metadata
        if ($this->metadata !== null) {
            $metaParts = [
                $this->metadata['type'],
                $this->fileSystem->formatSize($this->metadata['size']),
                $this->metadata['permissions'],
            ];

            if ($this->metadata['lines'] > 0) {
                $metaParts[] = "{$this->metadata['lines']} lines";
            }

            $line2 = ' ' . \implode(' | ', $metaParts);
            $line2 = \str_pad(\mb_substr($line2, 0, $width), $width);
            $renderer->writeAt(
                $x,
                $y + 1,
                $line2,
                ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_GRAY),
            );
        }
    }

    /**
     * Get binary file message
     */
    private function getBinaryFileMessage(): string
    {
        if ($this->metadata === null) {
            return "Binary file (cannot be displayed)";
        }

        return \sprintf(
            "Binary file\n" .
            "───────────\n" .
            "\n" .
            "File: %s\n" .
            "Type: %s\n" .
            "MIME: %s\n" .
            "Size: %s\n" .
            "\n" .
            "(Binary files cannot be displayed as text)",
            \basename($this->filePath),
            $this->metadata['type'],
            $this->metadata['mimeType'],
            $this->fileSystem->formatSize($this->metadata['size']),
        );
    }
}
