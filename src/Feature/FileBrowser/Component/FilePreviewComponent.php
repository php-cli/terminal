<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\FileBrowser\Component;

use Butschster\Commander\Feature\FileBrowser\Service\FileSystemService;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Component\ComponentInterface;
use Butschster\Commander\UI\Component\Decorator\Padding;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * File preview component - shows file/directory metadata
 *
 * Uses composition:
 * - TextInfoComponent for file/directory metadata (with padding)
 * 
 * Note: Full file content viewing is handled by FileViewerScreen (Ctrl+R key)
 */
final class FilePreviewComponent extends AbstractComponent
{
    private ?string $currentPath = null;
    private ?array $metadata = null;
    private ?ComponentInterface $currentContent = null;

    public function __construct(
        private readonly FileSystemService $fileSystem,
    ) {}

    /**
     * Set file/directory to preview (shows metadata only)
     */
    public function setFileInfo(?string $path): void
    {
        $this->currentPath = $path;

        if ($path === null) {
            $this->currentContent = null;
            $this->metadata = null;
            return;
        }

        // Get metadata
        $this->metadata = $this->fileSystem->getFileMetadata($path);

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
        $renderer->writeAt($x, $y + 2, $separator, ColorScheme::INACTIVE_BORDER);

        // Render current content (info or file viewer)
        if ($this->currentContent !== null) {
            $contentY = $y + 3;
            $contentHeight = $height - 3;

            $this->currentContent->render($renderer, $x, $contentY, $width, $contentHeight);
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        // Delegate to current content if it exists
        if ($this->currentContent !== null && $this->currentContent->isFocused()) {
            return $this->currentContent->handleInput($key);
        }

        return false;
    }

    #[\Override]
    public function setFocused(bool $focused): void
    {
        parent::setFocused($focused);

        // Propagate focus to current content
        if ($this->currentContent !== null) {
            $this->currentContent->setFocused($focused);
        }
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
        $lines = [
            'File Information',
            '================',
            '',
            "Name: " . \basename($path),
            "Path: " . \dirname($path),
            '',
        ];

        if ($this->metadata !== null) {
            $lines[] = 'Details:';
            $lines[] = "  Type: {$this->metadata['type']}";
            $lines[] = "  Size: " . $this->fileSystem->formatSize($this->metadata['size']);
            $lines[] = "  Permissions: {$this->metadata['permissions']}";
            $lines[] = "  Owner: {$this->metadata['owner']}";
            $lines[] = "  Group: {$this->metadata['group']}";
            $lines[] = "  Modified: " . $this->fileSystem->formatDate($this->metadata['modified']);

            if ($this->metadata['lines'] > 0) {
                $lines[] = "  Lines: {$this->metadata['lines']}";
            }

            $lines[] = '';
            $lines[] = '';
            $lines[] = '─────────────────────────────────────────';
            $lines[] = '';
            $lines[] = 'Press [Ctrl+R] to view file contents';
            $lines[] = '';
        }

        $infoComponent = new TextInfoComponent($lines);

        // Add padding around info
        $paddedInfo = Padding::symmetric($infoComponent, horizontal: 2, vertical: 1);

        // Remove old content
        if ($this->currentContent !== null) {
            $this->removeChild($this->currentContent);
        }

        $this->currentContent = $paddedInfo;
        $this->addChild($paddedInfo);
    }

    /**
     * Show directory information
     */
    private function showDirectoryInfo(string $path): void
    {
        $items = $this->fileSystem->listDirectory($path, true);

        $dirCount = 0;
        $fileCount = 0;
        $totalSize = 0;

        foreach ($items as $item) {
            if ($item['name'] === '..') {
                continue;
            }

            if ($item['isDir']) {
                $dirCount++;
            } else {
                $fileCount++;
                $totalSize += $item['size'];
            }
        }

        $lines = [
            'Directory Information',
            '====================',
            '',
            "Path: {$path}",
            '',
            'Contents:',
            "  Directories: {$dirCount}",
            "  Files: {$fileCount}",
            "  Total size: " . $this->fileSystem->formatSize($totalSize),
            '',
        ];

        if ($this->metadata !== null) {
            $lines[] = 'Metadata:';
            $lines[] = "  Permissions: {$this->metadata['permissions']}";
            $lines[] = "  Owner: {$this->metadata['owner']}";
            $lines[] = "  Group: {$this->metadata['group']}";
            $lines[] = "  Modified: " . $this->fileSystem->formatDate($this->metadata['modified']);
        }

        $infoComponent = new TextInfoComponent($lines);

        // Add padding around info
        $paddedInfo = Padding::symmetric($infoComponent, horizontal: 2, vertical: 1);

        // Remove old content
        if ($this->currentContent !== null) {
            $this->removeChild($this->currentContent);
        }

        $this->currentContent = $paddedInfo;
        $this->addChild($paddedInfo);
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
            ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW, ColorScheme::BOLD),
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
            ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_GRAY),
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

        $renderer->writeAt($emptyX, $emptyY, $emptyText, ColorScheme::NORMAL_TEXT);
    }
}
