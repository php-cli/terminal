<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Display;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Component\Concerns\HandlesInput;
use Butschster\Commander\UI\Theme\ColorScheme;
use Butschster\Commander\UI\Theme\ThemeContext;

/**
 * Generic table component with configurable columns
 *
 * Features:
 * - Configurable columns with flexible width specifications (fixed, %, flex)
 * - Column alignment (left, right, center)
 * - Custom formatters and colorizers per column
 * - Scrollable content with keyboard navigation
 * - Auto-scrolling to keep selection visible
 * - Optional header row
 * - Scrollbar indicator when content exceeds height
 *
 * Example:
 * ```php
 * $table = new TableComponent([
 *     new TableColumn('name', 'Name', '*', TableColumn::ALIGN_LEFT),
 *     new TableColumn('size', 'Size', 15, TableColumn::ALIGN_RIGHT, fn($v) => formatSize($v)),
 *     new TableColumn('date', 'Date', 20, TableColumn::ALIGN_RIGHT),
 * ]);
 *
 * $table->setRows([
 *     ['name' => 'file1.txt', 'size' => 1024, 'date' => '2025-01-01'],
 *     ['name' => 'file2.txt', 'size' => 2048, 'date' => '2025-01-02'],
 * ]);
 * ```
 */
final class TableComponent extends AbstractComponent
{
    use HandlesInput;

    private readonly Scrollbar $scrollbar;

    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    private int $selectedIndex = 0;
    private int $scrollOffset = 0;
    private int $visibleRows = 0;

    /** @var array<int, int> Calculated column widths in characters */
    private array $calculatedWidths = [];

    /** @var callable|null Callback when item is selected (Enter pressed) */
    private $onSelect = null;

    /** @var callable|null Callback when selection changes (arrow keys) */
    private $onChange = null;

    /**
     * @param array<TableColumn> $columns Column definitions
     * @param bool $showHeader Whether to show header row
     */
    public function __construct(private array $columns = [], private bool $showHeader = true)
    {
        $this->scrollbar = new Scrollbar();
    }

    /**
     * Set table columns
     *
     * @param array<TableColumn> $columns
     */
    public function setColumns(array $columns): void
    {
        $this->columns = $columns;
        $this->calculatedWidths = [];
    }

    /**
     * Set table rows
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function setRows(array $rows): void
    {
        $this->rows = $rows;
        $this->selectedIndex = 0;
        $this->scrollOffset = 0;

        // Trigger change callback
        if ($this->onChange !== null && !empty($this->rows)) {
            ($this->onChange)($this->rows[$this->selectedIndex], $this->selectedIndex);
        }
    }

    /**
     * Set whether to show header row
     */
    public function setShowHeader(bool $show): void
    {
        $this->showHeader = $show;
    }

    /**
     * Get selected row
     *
     * @return array<string, mixed>|null
     */
    public function getSelectedRow(): ?array
    {
        return $this->rows[$this->selectedIndex] ?? null;
    }

    /**
     * Get selected index
     */
    public function getSelectedIndex(): int
    {
        return $this->selectedIndex;
    }

    /**
     * Set selected index programmatically
     */
    public function setSelectedIndex(int $index): void
    {
        if ($index >= 0 && $index < \count($this->rows)) {
            $this->selectedIndex = $index;
            $this->adjustScroll();

            if ($this->onChange !== null && !empty($this->rows)) {
                ($this->onChange)($this->rows[$this->selectedIndex], $this->selectedIndex);
            }
        }
    }

    /**
     * Set callback for when row is selected (Enter pressed)
     *
     * @param callable(array<string, mixed>, int): void $callback
     */
    public function onSelect(callable $callback): void
    {
        $this->onSelect = $callback;
    }

    /**
     * Set callback for when selection changes (arrow keys)
     *
     * @param callable(array<string, mixed>, int): void $callback
     */
    public function onChange(callable $callback): void
    {
        $this->onChange = $callback;
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);
        $theme = $renderer->getThemeContext();

        // Calculate visible rows accounting for header
        $this->visibleRows = $this->showHeader ? $height - 2 : $height;

        // Check if scrollbar is needed
        $needsScrollbar = Scrollbar::needsScrollbar(\count($this->rows), $this->visibleRows);

        // Reserve space for scrollbar if needed
        $contentWidth = $needsScrollbar ? $width - 1 : $width;

        // Calculate column widths if not done yet or if width changed
        if (empty($this->calculatedWidths) || \array_sum($this->calculatedWidths) !== $contentWidth) {
            $this->calculateColumnWidths($contentWidth);
        }

        $currentY = $y;

        // Render header if enabled
        if ($this->showHeader) {
            $this->renderHeader($renderer, $x, $currentY, $contentWidth, $theme);
            $currentY += 1;

            // Render separator line
            $separator = \str_repeat('─', $contentWidth);
            $renderer->writeAt($x, $currentY, $separator, $theme->getInactiveBorder());
            $currentY += 1;
        }

        if (empty($this->rows)) {
            // Show empty state
            $this->renderEmptyState($renderer, $x, $currentY, $contentWidth, $this->visibleRows, $theme);
            return;
        }

        // Calculate visible range
        $startIndex = $this->scrollOffset;
        $endIndex = \min(
            $this->scrollOffset + $this->visibleRows,
            \count($this->rows),
        );

        // Render rows
        for ($i = $startIndex; $i < $endIndex; $i++) {
            $rowY = $currentY + ($i - $this->scrollOffset);
            $row = $this->rows[$i];
            $selected = ($i === $this->selectedIndex);

            $this->renderRow($renderer, $x, $rowY, $contentWidth, $row, $selected, $theme);
        }

        // Draw scrollbar if needed
        if ($needsScrollbar) {
            $this->scrollbar->render(
                $renderer,
                x: $x + $contentWidth,
                y: $currentY,
                height: $this->visibleRows,
                theme: $theme,
                totalItems: \count($this->rows),
                visibleItems: $this->visibleRows,
                scrollOffset: $this->scrollOffset,
            );
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        if (!$this->isFocused() || empty($this->rows)) {
            return false;
        }

        $input = KeyInput::from($key);
        $oldIndex = $this->selectedIndex;

        // Handle vertical navigation using trait
        $handled = $this->handleVerticalNavigation(
            $input,
            $this->selectedIndex,
            \count($this->rows),
            $this->visibleRows,
        );

        if ($handled !== null) {
            $this->adjustScroll();
            if ($oldIndex !== $this->selectedIndex && $this->onChange !== null) {
                ($this->onChange)($this->rows[$this->selectedIndex], $this->selectedIndex);
            }
            return true;
        }

        // Handle Enter key
        if ($input->is(Key::ENTER)) {
            return $this->handleEnter();
        }

        return false;
    }

    #[\Override]
    public function getMinSize(): array
    {
        $minWidth = \array_sum(
            \array_map(
                static fn($col) => \is_int($col->getWidth()) ? $col->getWidth() : 10,
                $this->columns,
            ),
        );

        return ['width' => \max($minWidth, 60), 'height' => 10];
    }

    private function handleEnter(): bool
    {
        if ($this->onSelect !== null) {
            ($this->onSelect)($this->rows[$this->selectedIndex], $this->selectedIndex);
        }
        return true;
    }

    /**
     * Calculate actual column widths based on specifications
     */
    private function calculateColumnWidths(int $totalWidth): void
    {
        $this->calculatedWidths = [];
        $remainingWidth = $totalWidth;
        $flexColumns = [];

        // First pass: allocate fixed and percentage widths
        foreach ($this->columns as $index => $column) {
            $width = $column->getWidth();

            if (\is_int($width)) {
                // Fixed width
                $this->calculatedWidths[$index] = \min($width, $remainingWidth);
                $remainingWidth -= $this->calculatedWidths[$index];
            } elseif (\str_ends_with($width, '%')) {
                // Percentage width
                $percentage = (float) \rtrim($width, '%');
                $calculatedWidth = (int) ($totalWidth * $percentage / 100);
                $this->calculatedWidths[$index] = \min($calculatedWidth, $remainingWidth);
                $remainingWidth -= $this->calculatedWidths[$index];
            } elseif ($width === '*') {
                // Flex column - will be calculated in second pass
                $flexColumns[] = $index;
            }
        }

        // Second pass: distribute remaining width to flex columns
        if (!empty($flexColumns)) {
            $widthPerFlex = \max(1, (int) ($remainingWidth / \count($flexColumns)));

            foreach ($flexColumns as $index) {
                $this->calculatedWidths[$index] = $widthPerFlex;
            }
        }
    }

    /**
     * Render header row
     */
    private function renderHeader(Renderer $renderer, int $x, int $y, int $width, ThemeContext $theme): void
    {
        $headerParts = [];
        $currentX = 0;

        foreach ($this->columns as $index => $column) {
            $colWidth = $this->calculatedWidths[$index] ?? 10;
            $label = $column->getLabel();

            // Align header text
            $headerText = $this->alignText($label, $colWidth, $column->getAlign());
            $headerParts[] = $headerText;
            $currentX += $colWidth;
        }

        $headerLine = \implode('', $headerParts);
        $headerLine = \mb_substr($headerLine, 0, $width);
        $headerLine = \str_pad($headerLine, $width);

        $renderer->writeAt(
            $x,
            $y,
            $headerLine,
            $theme->getNormalColors()->withStyle(ColorScheme::FG_YELLOW . ColorScheme::BOLD),
        );
    }

    /**
     * Render a single data row with per-column coloring
     *
     * @param array<string, mixed> $row
     */
    private function renderRow(
        Renderer $renderer,
        int $x,
        int $y,
        int $width,
        array $row,
        bool $selected,
        ThemeContext $theme,
    ): void {
        $currentX = $x;

        // Default color
        $defaultColor = $selected && $this->isFocused()
            ? $theme->getSelectedText()
            : $theme->getNormalText();

        foreach ($this->columns as $index => $column) {
            $colWidth = $this->calculatedWidths[$index] ?? 10;
            $key = $column->getKey();
            $value = $row[$key] ?? '';

            // Format value
            $formattedValue = $column->formatValue($value, $row);

            // Align text
            $cellText = $this->alignText($formattedValue, $colWidth, $column->getAlign());

            // Determine cell color - check for custom colorizer
            $customColor = $column->getColor($value, $row, $selected);
            $cellColor = $customColor ?? $defaultColor;

            // Render this cell
            $renderer->writeAt($currentX, $y, $cellText, $cellColor);

            $currentX += $colWidth;
        }
    }

    /**
     * Render empty state message
     */
    private function renderEmptyState(Renderer $renderer, int $x, int $y, int $width, int $height, ThemeContext $theme): void
    {
        $emptyText = '(No data)';
        $emptyX = $x + (int) (($width - \mb_strlen($emptyText)) / 2);
        $emptyY = $y + (int) ($height / 2);

        $renderer->writeAt($emptyX, $emptyY, $emptyText, $theme->getNormalText());
    }

    /**
     * Align text within a given width
     */
    private function alignText(string $text, int $width, string $align): string
    {
        $textLength = \mb_strlen($text);

        // Truncate if too long
        if ($textLength > $width) {
            return \mb_substr($text, 0, $width - 3) . '...';
        }

        // Pad according to alignment
        return match ($align) {
            TableColumn::ALIGN_RIGHT => \str_pad($text, $width, ' ', STR_PAD_LEFT),
            TableColumn::ALIGN_CENTER => \str_pad(
                \str_pad($text, $textLength + (int) (($width - $textLength) / 2), ' ', STR_PAD_LEFT),
                $width,
                ' ',
                STR_PAD_RIGHT,
            ),
            default => \str_pad($text, $width, ' ', STR_PAD_RIGHT), // ALIGN_LEFT
        };
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
}
