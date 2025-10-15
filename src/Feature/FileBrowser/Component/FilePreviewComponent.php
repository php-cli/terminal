<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\FileBrowser\Component;

use Butschster\Commander\Feature\FileBrowser\Service\FileSystemService;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Component\ComponentInterface;
use Butschster\Commander\UI\Component\Decorator\Padding;
use Butschster\Commander\UI\Component\Display\TextDisplay;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * File preview component - shows file/directory metadata
 *
 * Uses composition:
 * - TextDisplay with feature components (FileInfoSection, DirectoryInfoSection)
 * - Padding decorator for spacing
 *
 * Note: Full file content viewing is handled by FileViewerScreen (Ctrl+R key)
 */
final class FilePreviewComponent extends AbstractComponent
{
    private ?string $currentPath = null;
    private ?array $metadata = null;
    private readonly TextDisplay $innerDisplay;
    private readonly ComponentInterface $paddedDisplay;

    public function __construct(
        private readonly FileSystemService $fileSystem,
    ) {
        // Create text display component
        $this->innerDisplay = new TextDisplay();

        // Wrap with padding
        $this->paddedDisplay = Padding::symmetric($this->innerDisplay, horizontal: 2, vertical: 1);
        $this->addChild($this->paddedDisplay);
    }

    /**
     * Set file/directory to preview (shows metadata only)
     */
    public function setFileInfo(?string $path): void
    {
        $this->currentPath = $path;

        if ($path === null) {
            $this->metadata = null;
            $this->innerDisplay->clear();
            return;
        }

        // Get metadata
        $this->metadata = $this->fileSystem->getFileMetadata($path);

        if ($this->metadata === null) {
            $this->innerDisplay->setText('Error: Unable to read file metadata');
            return;
        }

        // Show appropriate info based on type
        if (\is_dir($path)) {
            $this->showDirectoryInfo($path);
        } else {
            $this->showFileInfo($path);
        }
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);

        // Render header
        $this->renderHeader($renderer, $x, $y, $width);

        if ($this->currentPath === null) {
            $this->renderEmptyState($renderer, $x, $y + 3, $width, $height - 3);
            return;
        }

        // Render metadata bar
        $this->renderMetadata($renderer, $x, $y + 1, $width);

        // Render separator
        $separator = \str_repeat('─', $width);
        $renderer->writeAt($x, $y + 2, $separator, ColorScheme::$INACTIVE_BORDER);

        // Render text display content
        $contentY = $y + 3;
        $contentHeight = $height - 3;
        $this->paddedDisplay->render($renderer, $x, $contentY, $width, $contentHeight);
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        // Delegate to inner text display
        return $this->innerDisplay->handleInput($key);
    }

    #[\Override]
    public function setFocused(bool $focused): void
    {
        parent::setFocused($focused);
        $this->innerDisplay->setFocused($focused);
    }

    #[\Override]
    public function getMinSize(): array
    {
        return ['width' => 40, 'height' => 10];
    }

    /**
     * Show file information (metadata)
     */
    private function showFileInfo(string $path): void
    {
        // Use feature component for file info
        $content = FileInfoSection::create($this->metadata, $this->fileSystem);
        $this->innerDisplay->setText($content);
    }

    /**
     * Show directory information
     */
    private function showDirectoryInfo(string $path): void
    {
        // Get directory contents for statistics
        $items = $this->fileSystem->listDirectory($path, true);

        // Use feature component for directory info
        $content = DirectoryInfoSection::create($path, $this->metadata, $items, $this->fileSystem);
        $this->innerDisplay->setText($content);
    }

    /**
     * Render header with file name
     */
    private function renderHeader(Renderer $renderer, int $x, int $y, int $width): void
    {
        $title = $this->currentPath !== null ? \basename($this->currentPath) : 'File Preview';
        $headerText = ' ' . \mb_substr($title, 0, $width - 2) . ' ';

        $renderer->writeAt(
            $x,
            $y,
            \str_pad($headerText, $width),
            ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_YELLOW, ColorScheme::BOLD),
        );
    }

    /**
     * Render metadata bar
     */
    private function renderMetadata(Renderer $renderer, int $x, int $y, int $width): void
    {
        if ($this->metadata === null) {
            return;
        }

        $metaText = \sprintf(
            '%s | %s | %s',
            $this->metadata['type'],
            $this->fileSystem->formatSize($this->metadata['size']),
            $this->metadata['permissions'],
        );

        if ($this->metadata['lines'] > 0) {
            $metaText .= " | {$this->metadata['lines']} lines";
        }

        $metaText = ' ' . \mb_substr($metaText, 0, $width - 2) . ' ';

        $renderer->writeAt(
            $x,
            $y,
            \str_pad($metaText, $width),
            ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_GRAY),
        );
    }

    /**
     * Render empty state
     */
    private function renderEmptyState(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $emptyText = 'Select a file to preview';
        $emptyX = $x + (int) (($width - \mb_strlen($emptyText)) / 2);
        $emptyY = $y + (int) ($height / 2);

        $renderer->writeAt($emptyX, $emptyY, $emptyText, ColorScheme::$NORMAL_TEXT);
    }
}
