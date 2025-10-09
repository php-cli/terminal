<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\FileBrowser\Component;

use Butschster\Commander\Feature\FileBrowser\Service\FileSystemService;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * File preview component showing file contents or metadata
 */
final class FilePreviewComponent extends AbstractComponent
{
    /** @var array<string> */
    private array $lines = [];

    private int $scrollOffset = 0;
    private int $visibleLines = 0;
    private ?string $currentPath = null;
    private ?array $metadata = null;

    /** Flag to indicate if we're showing full file contents or just metadata */
    private bool $showingFullContents = false;

    public function __construct(
        private readonly FileSystemService $fileSystem,
    ) {}

    /**
     * Set file to preview (reads file contents)
     */
    public function setFile(?string $path): void
    {
        $this->currentPath = $path;
        $this->scrollOffset = 0;
        $this->showingFullContents = true; // We're showing full contents

        if ($path === null) {
            $this->lines = [];
            $this->metadata = null;
            $this->showingFullContents = false;
            return;
        }

        // Get metadata
        $this->metadata = $this->fileSystem->getFileMetadata($path);

        // If it's a directory, show directory info
        if (\is_dir($path)) {
            $this->lines = $this->getDirectoryInfo($path);
            $this->showingFullContents = false; // Directory info is not file contents
            return;
        }

        // If it's a file, show contents
        $contents = $this->fileSystem->readFileContents($path, 1000);
        $this->lines = \explode("\n", $contents);
    }

    /**
     * Show file metadata only (without reading contents)
     */
    public function setFileInfo(?string $path): void
    {
        $this->currentPath = $path;
        $this->scrollOffset = 0;
        $this->showingFullContents = false; // We're showing only metadata

        if ($path === null) {
            $this->lines = [];
            $this->metadata = null;
            return;
        }

        // Get metadata
        $this->metadata = $this->fileSystem->getFileMetadata($path);

        // If it's a directory, show directory info
        if (\is_dir($path)) {
            $this->lines = $this->getDirectoryInfo($path);
            return;
        }

        // For files, show metadata only
        $this->lines = $this->getFileInfo($path);
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);
        $this->visibleLines = $height - 3; // Reserve lines for header and metadata

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

        // Render content
        $this->renderContent($renderer, $x, $y + 3, $width, $height - 3);
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        if (!$this->isFocused() || empty($this->lines)) {
            return false;
        }

        switch ($key) {
            case 'UP':
                if ($this->scrollOffset > 0) {
                    $this->scrollOffset--;
                }
                return true;

            case 'DOWN':
                if ($this->scrollOffset < \count($this->lines) - $this->visibleLines) {
                    $this->scrollOffset++;
                }
                return true;

            case 'PAGE_UP':
                $this->scrollOffset = \max(0, $this->scrollOffset - $this->visibleLines);
                return true;

            case 'PAGE_DOWN':
                $this->scrollOffset = \min(
                    \max(0, \count($this->lines) - $this->visibleLines),
                    $this->scrollOffset + $this->visibleLines,
                );
                return true;

            case 'HOME':
                $this->scrollOffset = 0;
                return true;

            case 'END':
                $this->scrollOffset = \max(0, \count($this->lines) - $this->visibleLines);
                return true;
        }

        return false;
    }

    #[\Override]
    public function getMinSize(): array
    {
        return ['width' => 40, 'height' => 10];
    }

    /**
     * Get file information (metadata only, no contents)
     *
     * @return array<string>
     */
    private function getFileInfo(string $path): array
    {
        $lines = [
            '',
            '  File Information',
            '  ================',
            '',
            "  Name: " . \basename($path),
            "  Path: " . \dirname($path),
            '',
        ];

        if ($this->metadata !== null) {
            $lines[] = '  Details:';
            $lines[] = "    Type: {$this->metadata['type']}";
            $lines[] = "    Size: " . $this->fileSystem->formatSize($this->metadata['size']);
            $lines[] = "    Permissions: {$this->metadata['permissions']}";
            $lines[] = "    Owner: {$this->metadata['owner']}";
            $lines[] = "    Group: {$this->metadata['group']}";
            $lines[] = "    Modified: " . $this->fileSystem->formatDate($this->metadata['modified']);

            if ($this->metadata['lines'] > 0) {
                $lines[] = "    Lines: {$this->metadata['lines']}";
            }

            $lines[] = '';
            $lines[] = '';
            $lines[] = '  ─────────────────────────────────────────';
            $lines[] = '';
            $lines[] = '    Press [F4] to view file contents';
            $lines[] = '';
        }

        return $lines;
    }

    /**
     * Get directory information
     *
     * @return array<string>
     */
    private function getDirectoryInfo(string $path): array
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
            '',
            '  Directory Information',
            '  ====================',
            '',
            "  Path: {$path}",
            '',
            '  Contents:',
            "    Directories: {$dirCount}",
            "    Files: {$fileCount}",
            "    Total size: " . $this->fileSystem->formatSize($totalSize),
            '',
        ];

        if ($this->metadata !== null) {
            $lines[] = '  Metadata:';
            $lines[] = "    Permissions: {$this->metadata['permissions']}";
            $lines[] = "    Owner: {$this->metadata['owner']}";
            $lines[] = "    Group: {$this->metadata['group']}";
            $lines[] = "    Modified: " . $this->fileSystem->formatDate($this->metadata['modified']);
        }

        return $lines;
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

    /**
     * Render file contents
     */
    private function renderContent(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        if (empty($this->lines)) {
            return;
        }

        $endIndex = \min(
            $this->scrollOffset + $height,
            \count($this->lines),
        );

        // Render lines
        for ($i = $this->scrollOffset; $i < $endIndex; $i++) {
            $rowY = $y + ($i - $this->scrollOffset);
            $line = $this->lines[$i];

            // Add line numbers ONLY when viewing actual file contents
            $lineNumber = '';
            if ($this->showingFullContents) {
                // Line numbers with right padding: "   1 │ "
                $lineNumber = ' ' . \str_pad((string) ($i + 1), 4, ' ', STR_PAD_LEFT) . ' │ ';
            }

            // Combine line number and content
            $displayText = $lineNumber . $line;

            // Truncate or pad line to fit width
            $displayText = \mb_substr($displayText, 0, $width);
            $displayText = \str_pad($displayText, $width);

            // Syntax highlighting based on file type
            $color = $this->getLineColor($line);

            $renderer->writeAt($x, $rowY, $displayText, $color);
        }

        // Draw scrollbar if needed
        if (\count($this->lines) > $height) {
            $this->drawScrollbar($renderer, $x + $width - 1, $y, $height);
        }

        // Show scroll position indicator
        if (\count($this->lines) > $height) {
            $position = \sprintf(
                '%d-%d/%d',
                $this->scrollOffset + 1,
                \min($this->scrollOffset + $height, \count($this->lines)),
                \count($this->lines),
            );
            $renderer->writeAt(
                $x + $width - \mb_strlen($position) - 1,
                $y + $height - 1,
                $position,
                ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::FG_BLACK),
            );
        }
    }

    /**
     * Get color for a line (no syntax highlighting)
     */
    private function getLineColor(string $line): string
    {
        return ColorScheme::NORMAL_TEXT;
    }

    /**
     * Draw scrollbar indicator
     */
    private function drawScrollbar(Renderer $renderer, int $x, int $y, int $height): void
    {
        $totalLines = \count($this->lines);

        if ($totalLines <= $this->visibleLines) {
            return;
        }

        // Calculate thumb size and position
        $thumbHeight = \max(1, (int) ($height * $this->visibleLines / $totalLines));
        $thumbPosition = (int) ($height * $this->scrollOffset / $totalLines);

        for ($i = 0; $i < $height; $i++) {
            $char = ($i >= $thumbPosition && $i < $thumbPosition + $thumbHeight) ? '█' : '░';
            $renderer->writeAt($x, $y + $i, $char, ColorScheme::SCROLLBAR);
        }
    }
}
