<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\FileBrowser\Component;

use Butschster\Commander\Feature\FileBrowser\Service\FileSystemService;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Component\Display\TableColumn;
use Butschster\Commander\UI\Component\Display\TableComponent;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * File list component showing directory contents with metadata
 *
 * Now uses the generic TableComponent for rendering, providing:
 * - Name column (with icon, flexible width)
 * - Size column (right-aligned, fixed width)
 * - Modified date column (right-aligned, fixed width)
 */
final class FileListComponent extends AbstractComponent
{
    /** @var array<array{name: string, path: string, type: string, size: int, modified: int, isDir: bool}> */
    private array $items = [];

    private TableComponent $table;

    /** @var callable|null Callback when item is selected (Enter pressed) */
    private $onSelect = null;

    /** @var callable|null Callback when selection changes */
    private $onChange = null;

    public function __construct(
        private readonly FileSystemService $fileSystem,
    ) {
        // Create table with column definitions
        $this->table = new TableComponent([
            new TableColumn(
                key: 'name',
                label: 'Name',
                width: '*', // Flex - takes remaining space
                align: TableColumn::ALIGN_LEFT,
                formatter: fn($value, $row) => $this->formatName($row),
                colorizer: fn($value, $row, $selected) => $this->getNameColor($row, $selected),
            ),
            new TableColumn(
                key: 'size',
                label: 'Size',
                width: 18,
                align: TableColumn::ALIGN_RIGHT,
                formatter: fn($value, $row) => $this->formatSize($row),
            ),
            new TableColumn(
                key: 'modified',
                label: 'Modified',
                width: 25,
                align: TableColumn::ALIGN_RIGHT,
                formatter: fn($value) => date('Y-m-d H:i', $value),
            ),
        ], showHeader: true);

        $this->table->setFocused(true);

        // Wire up table callbacks to our callbacks
        $this->table->onSelect(function (array $row, int $index): void {
            if ($this->onSelect !== null) {
                ($this->onSelect)($row);
            }
        });

        $this->table->onChange(function (array $row, int $index): void {
            if ($this->onChange !== null) {
                ($this->onChange)($row);
            }
        });

        $this->addChild($this->table);
    }

    /**
     * Set current directory
     */
    public function setDirectory(string $path): void
    {
        $this->items = $this->fileSystem->listDirectory($path, false);
        $this->table->setRows($this->items);
    }

    /**
     * Get selected item
     *
     * @return array{name: string, path: string, type: string, size: int, modified: int, isDir: bool}|null
     */
    public function getSelectedItem(): ?array
    {
        return $this->table->getSelectedRow();
    }

    /**
     * Get selected index
     */
    public function getSelectedIndex(): int
    {
        return $this->table->getSelectedIndex();
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

        // Delegate rendering to table
        $this->table->render($renderer, $x, $y, $width, $height);
    }

    public function handleInput(string $key): bool
    {
        // Delegate input handling to table
        return $this->table->handleInput($key);
    }

    public function setFocused(bool $focused): void
    {
        parent::setFocused($focused);
        $this->table->setFocused($focused);
    }

    public function getMinSize(): array
    {
        return $this->table->getMinSize();
    }

    /**
     * Format name column with icon
     *
     * @param array{name: string, path: string, type: string, size: int, modified: int, isDir: bool} $row
     */
    private function formatName(array $row): string
    {
        $icon = $this->getAsciiIcon($row);
        return $icon . ' ' . $row['name'];
    }

    /**
     * Format size column
     *
     * @param array{name: string, path: string, type: string, size: int, modified: int, isDir: bool} $row
     */
    private function formatSize(array $row): string
    {
        return $row['isDir'] ? '<DIR>' : $this->fileSystem->formatSize($row['size']);
    }

    /**
     * Get color for name column based on file type
     *
     * @param array{name: string, path: string, type: string, size: int, modified: int, isDir: bool} $row
     */
    private function getNameColor(array $row, bool $selected): string
    {
        if ($selected && $this->isFocused()) {
            return ColorScheme::SELECTED_TEXT;
        }

        if ($row['isDir']) {
            // Directories: bold bright white on blue
            return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_BRIGHT_WHITE);
        }

        // Files: normal white on blue
        return ColorScheme::NORMAL_TEXT;
    }

    /**
     * Get ASCII icon for item (reliable single-column character)
     *
     * @param array{name: string, path: string, type: string, size: int, modified: int, isDir: bool} $row
     */
    private function getAsciiIcon(array $row): string
    {
        if ($row['name'] === '..') {
            return '/';
        }

        if ($row['isDir']) {
            return '/';
        }

        // No icon for files - just space
        return ' ';
    }
}
