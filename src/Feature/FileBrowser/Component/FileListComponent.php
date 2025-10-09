<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\FileBrowser\Component;

use Butschster\Commander\Feature\FileBrowser\Service\FileSystemService;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * File list component showing directory contents with metadata
 */
final class FileListComponent extends AbstractComponent
{
    /** @var array<array{name: string, path: string, type: string, size: int, modified: int, isDir: bool}> */
    private array $items = [];

    private int $selectedIndex = 0;
    private int $scrollOffset = 0;
    private int $visibleRows = 0;

    /** @var callable|null Callback when item is selected (Enter pressed) */
    private $onSelect = null;

    /** @var callable|null Callback when selection changes */
    private $onChange = null;

    public function __construct(
        private readonly FileSystemService $fileSystem,
    ) {}

    /**
     * Set current directory
     */
    public function setDirectory(string $path): void
    {
        $this->items = $this->fileSystem->listDirectory($path, false);
        $this->selectedIndex = 0;
        $this->scrollOffset = 0;

        // Trigger change callback
        if ($this->onChange !== null && !empty($this->items)) {
            ($this->onChange)($this->items[$this->selectedIndex]);
        }
    }

    /**
     * Get selected item
     *
     * @return array{name: string, path: string, type: string, size: int, modified: int, isDir: bool}|null
     */
    public function getSelectedItem(): ?array
    {
        return $this->items[$this->selectedIndex] ?? null;
    }

    /**
     * Get selected index
     */
    public function getSelectedIndex(): int
    {
        return $this->selectedIndex;
    }

    /**
     * Set callback for when item is selected (Enter pressed)
     *
     * @param callable(array): void $callback
     */
    public function onSelect(callable $callback): void
    {
        $this->onSelect = $callback;
    }

    /**
     * Set callback for when selection changes (arrow keys)
     *
     * @param callable(array): void $callback
     */
    public function onChange(callable $callback): void
    {
        $this->onChange = $callback;
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);
        $this->visibleRows = $height - 2; // Reserve 2 lines for header

        if (empty($this->items)) {
            // Show empty state
            $emptyText = '(Empty directory)';
            $emptyX = $x + (int) (($width - mb_strlen($emptyText)) / 2);
            $emptyY = $y + (int) ($height / 2);

            $renderer->writeAt($emptyX, $emptyY, $emptyText, ColorScheme::NORMAL_TEXT);
            return;
        }

        // Render header
        $this->renderHeader($renderer, $x, $y, $width);

        // Calculate visible range
        $startIndex = $this->scrollOffset;
        $endIndex = min(
            $this->scrollOffset + $this->visibleRows,
            count($this->items),
        );

        // Render items
        for ($i = $startIndex; $i < $endIndex; $i++) {
            $rowY = $y + 2 + ($i - $this->scrollOffset);
            $item = $this->items[$i];

            $this->renderItem($renderer, $x, $rowY, $width, $item, $i === $this->selectedIndex);
        }

        // Draw scrollbar if needed
        if (count($this->items) > $this->visibleRows) {
            $this->drawScrollbar($renderer, $x + $width - 1, $y + 2, $this->visibleRows);
        }
    }

    /**
     * Render header with column labels
     */
    private function renderHeader(Renderer $renderer, int $x, int $y, int $width): void
    {
        // Calculate column widths (must match renderItem exactly)
        $sizeWidth = 18;  // Increased from 15 to 18 for more spacing
        $dateWidth = 21;  // Increased from 19 to 21 for more spacing
        $nameWidth = $width - $sizeWidth - $dateWidth;

        // Build header parts with proper padding
        $namePart = str_pad('Name', $nameWidth, ' ');
        $sizePart = str_pad('Size', $sizeWidth - 2, ' ', STR_PAD_LEFT) . '  '; // 2 spaces after
        $datePart = str_pad('Modified', $dateWidth, ' ', STR_PAD_LEFT);

        $header = $namePart . $sizePart . $datePart;

        $renderer->writeAt(
            $x,
            $y,
            $header,
            ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW, ColorScheme::BOLD),
        );

        // Separator line
        $separator = str_repeat('─', $width);
        $renderer->writeAt($x, $y + 1, $separator, ColorScheme::INACTIVE_BORDER);
    }

    /**
     * Render individual item
     *
     * @param array{name: string, path: string, type: string, size: int, modified: int, isDir: bool} $item
     */
    private function renderItem(
        Renderer $renderer,
        int $x,
        int $y,
        int $width,
        array $item,
        bool $selected,
    ): void {
        // Calculate column widths (same as header)
        $sizeWidth = 18;  // Increased from 15 to 18 for more spacing
        $dateWidth = 21;  // Increased from 19 to 21 for more spacing
        $nameWidth = $width - $sizeWidth - $dateWidth;

        // Use ASCII characters for reliable alignment (no emoji width issues)
        $icon = $this->getAsciiIcon($item);
        $name = $item['name'];
        $size = $item['isDir'] ? '<DIR>' : $this->fileSystem->formatSize($item['size']);
        $date = date('Y-m-d H:i:s', $item['modified']);

        // Icon is 1 char + 1 space = 2 chars total
        $iconPart = $icon . ' ';
        $maxNameLength = $nameWidth - 2;

        // Truncate name if too long
        if (mb_strlen($name) > $maxNameLength) {
            $name = mb_substr($name, 0, $maxNameLength - 3) . '...';
        }

        // Calculate padding to fill nameWidth exactly
        $padding = $nameWidth - 2 - mb_strlen($name);
        $namePart = $iconPart . $name . str_repeat(' ', $padding);

        // Build size and date parts with spacing between columns
        $sizePart = str_pad($size, $sizeWidth - 2, ' ', STR_PAD_LEFT) . '  '; // 2 spaces after
        $datePart = str_pad($date, $dateWidth, ' ', STR_PAD_LEFT);

        $itemText = $namePart . $sizePart . $datePart;

        // Color based on selection and file type
        $color = $this->getItemColor($item, $selected);

        $renderer->writeAt($x, $y, $itemText, $color);
    }

    /**
     * Get ASCII icon for item (reliable single-column character)
     */
    private function getAsciiIcon(array $item): string
    {
        if ($item['name'] === '..') {
            return '/';
        }

        if ($item['isDir']) {
            return '/';
        }

        // No icon for files - just space
        return ' ';
    }

    /**
     * Get item color based on type and selection
     */
    private function getItemColor(array $item, bool $selected): string
    {
        if ($selected && $this->isFocused()) {
            return ColorScheme::SELECTED_TEXT;
        }

        if ($item['isDir']) {
            // Directories: bold bright white on blue
            // FG_BRIGHT_WHITE already contains BOLD (\033[1;37m)
            return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_BRIGHT_WHITE);
        }

        // Files: normal white on blue (slightly dimmer than directories)
        return ColorScheme::NORMAL_TEXT;
    }

    public function handleInput(string $key): bool
    {
        $oldIndex = $this->selectedIndex;

        switch ($key) {
            case 'UP':
                if ($this->selectedIndex > 0) {
                    $this->selectedIndex--;
                    $this->adjustScroll();
                }
                break;

            case 'DOWN':
                if ($this->selectedIndex < count($this->items) - 1) {
                    $this->selectedIndex++;
                    $this->adjustScroll();
                }
                break;

            case 'PAGE_UP':
                $this->selectedIndex = max(0, $this->selectedIndex - $this->visibleRows);
                $this->adjustScroll();
                break;

            case 'PAGE_DOWN':
                $this->selectedIndex = min(
                    count($this->items) - 1,
                    $this->selectedIndex + $this->visibleRows,
                );
                $this->adjustScroll();
                break;

            case 'HOME':
                $this->selectedIndex = 0;
                $this->adjustScroll();
                break;

            case 'END':
                $this->selectedIndex = count($this->items) - 1;
                $this->adjustScroll();
                break;

            case 'ENTER':
                if ($this->onSelect !== null && !empty($this->items)) {
                    ($this->onSelect)($this->items[$this->selectedIndex]);
                }
                return true;

            default:
                return false;
        }

        // Trigger change callback if selection changed
        if ($oldIndex !== $this->selectedIndex && $this->onChange !== null && !empty($this->items)) {
            ($this->onChange)($this->items[$this->selectedIndex]);
        }

        return true;
    }

    /**
     * Adjust scroll offset to keep selected item visible
     */
    private function adjustScroll(): void
    {
        if ($this->selectedIndex < $this->scrollOffset) {
            $this->scrollOffset = $this->selectedIndex;
        } elseif ($this->selectedIndex >= $this->scrollOffset + $this->visibleRows) {
            $this->scrollOffset = $this->selectedIndex - $this->visibleRows + 1;
        }
    }

    /**
     * Draw scrollbar indicator
     */
    private function drawScrollbar(Renderer $renderer, int $x, int $y, int $height): void
    {
        $totalItems = count($this->items);

        // Calculate thumb size and position
        $thumbHeight = max(1, (int) ($height * $this->visibleRows / $totalItems));
        $thumbPosition = (int) ($height * $this->scrollOffset / $totalItems);

        for ($i = 0; $i < $height; $i++) {
            $char = ($i >= $thumbPosition && $i < $thumbPosition + $thumbHeight) ? '█' : '░';
            $renderer->writeAt($x, $y + $i, $char, ColorScheme::SCROLLBAR);
        }
    }

    public function getMinSize(): array
    {
        return ['width' => 60, 'height' => 10];
    }
}
