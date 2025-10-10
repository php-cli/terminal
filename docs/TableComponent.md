# TableComponent Documentation

## Overview

The `TableComponent` is a generic, reusable table component for displaying tabular data with:

- **Configurable columns** with flexible width specifications (fixed, percentage, flex)
- **Column alignment** (left, right, center)
- **Custom formatters** per column for data transformation
- **Custom colorizers** per column for styling individual cells
- **Keyboard navigation** with scrolling support
- **Optional header row** with separator line
- **Scrollbar indicator** when content exceeds visible area
- **Selection callbacks** for user interactions

---

## Basic Usage

### Simple Table

```php
use Butschster\Commander\UI\Component\Display\TableComponent;
use Butschster\Commander\UI\Component\Display\TableColumn;

// Create table with columns
$table = new TableComponent([
    new TableColumn('id', 'ID', 10, TableColumn::ALIGN_RIGHT),
    new TableColumn('name', 'Name', '*', TableColumn::ALIGN_LEFT),
    new TableColumn('status', 'Status', 15, TableColumn::ALIGN_CENTER),
]);

// Set data
$table->setRows([
    ['id' => 1, 'name' => 'Alice', 'status' => 'Active'],
    ['id' => 2, 'name' => 'Bob', 'status' => 'Inactive'],
    ['id' => 3, 'name' => 'Charlie', 'status' => 'Active'],
]);

// Set callbacks
$table->onSelect(function (array $row, int $index) {
    echo "Selected: {$row['name']}\n";
});

// Render
$table->render($renderer, 0, 0, 80, 20);
```

---

## Column Width Specifications

### 1. Fixed Width (int)

```php
new TableColumn('id', 'ID', 10) // Exactly 10 characters
```

### 2. Percentage Width (string)

```php
new TableColumn('name', 'Name', '30%') // 30% of table width
```

### 3. Flex Width (string)

```php
new TableColumn('description', 'Description', '*') // Takes remaining space
```

Multiple flex columns split remaining space equally:

```php
$table = new TableComponent([
    new TableColumn('id', 'ID', 10),          // 10 chars
    new TableColumn('name', 'Name', '30%'),   // 30% of width
    new TableColumn('col1', 'Col1', '*'),     // 50% of remaining space
    new TableColumn('col2', 'Col2', '*'),     // 50% of remaining space
]);
```

---

## Column Alignment

```php
// Left-aligned (default)
new TableColumn('name', 'Name', '*', TableColumn::ALIGN_LEFT)

// Right-aligned (good for numbers, dates, sizes)
new TableColumn('size', 'Size', 15, TableColumn::ALIGN_RIGHT)

// Center-aligned (good for status, icons)
new TableColumn('status', 'Status', 15, TableColumn::ALIGN_CENTER)
```

---

## Custom Formatters

Formatters transform raw data values for display:

```php
new TableColumn(
    key: 'size',
    label: 'Size',
    width: 15,
    align: TableColumn::ALIGN_RIGHT,
    formatter: fn($value, $row) => $this->formatSize($value),
)

private function formatSize(int $bytes): string {
    if ($bytes < 1024) {return "{$bytes} B";}
    if ($bytes < 1048576) {return round($bytes / 1024, 2) . " KB";}
    return round($bytes / 1048576, 2) . " MB";
}
```

### Formatter with Full Row Context

```php
new TableColumn(
    key: 'name',
    label: 'Name',
    width: '*',
    formatter: function ($value, $row) {
        // Access other columns in the row
        $icon = $row['isDir'] ? '📁' : '📄';
        return "{$icon} {$value}";
    },
)
```

---

## Custom Colorizers

Colorizers apply custom colors to cells based on data:

```php
new TableColumn(
    key: 'status',
    label: 'Status',
    width: 15,
    align: TableColumn::ALIGN_CENTER,
    colorizer: function ($value, $row, $selected) {
        if ($selected) {
            return ColorScheme::SELECTED_TEXT;
        }
        
        return match ($value) {
            'Active' => ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_GREEN),
            'Error' => ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_RED),
            default => ColorScheme::NORMAL_TEXT,
        };
    },
)
```

### Colorizer with Full Row Context

```php
new TableColumn(
    key: 'name',
    label: 'Name',
    width: '*',
    colorizer: function ($value, $row, $selected) {
        if ($selected && $this->isFocused()) {
            return ColorScheme::SELECTED_TEXT;
        }
        
        // Highlight directories in bold bright white
        if ($row['isDir']) {
            return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_BRIGHT_WHITE);
        }
        
        return ColorScheme::NORMAL_TEXT;
    },
)
```

---

## Callbacks

### Selection Changed (Arrow Keys)

```php
$table->onChange(function (array $row, int $index) {
    // Called when user navigates with arrow keys
    echo "Selected index: {$index}, Name: {$row['name']}\n";
});
```

### Selection Confirmed (Enter Key)

```php
$table->onSelect(function (array $row, int $index) {
    // Called when user presses Enter
    echo "Confirmed: {$row['name']}\n";
    // Open file, navigate to details, etc.
});
```

---

## API Reference

### Constructor

```php
public function __construct(
    array $columns = [],
    bool $showHeader = true,
)
```

### Methods

```php
// Set columns
$table->setColumns(array $columns): void

// Set data rows
$table->setRows(array $rows): void

// Show/hide header
$table->setShowHeader(bool $show): void

// Get selected row
$table->getSelectedRow(): ?array

// Get selected index
$table->getSelectedIndex(): int

// Set selected index programmatically
$table->setSelectedIndex(int $index): void

// Set callbacks
$table->onSelect(callable $callback): void
$table->onChange(callable $callback): void

// Focus management (inherited from AbstractComponent)
$table->setFocused(bool $focused): void
$table->isFocused(): bool
```

---

## Advanced Example: File List Table

```php
use Butschster\Commander\UI\Component\Display\TableComponent;
use Butschster\Commander\UI\Component\Display\TableColumn;
use Butschster\Commander\UI\Theme\ColorScheme;

class FileListComponent extends AbstractComponent
{
    private TableComponent $table;
    
    public function __construct(private FileSystemService $fileSystem)
    {
        $this->table = new TableComponent([
            // Name column: flex width, left-aligned, with icon formatter
            new TableColumn(
                key: 'name',
                label: 'Name',
                width: '*',
                align: TableColumn::ALIGN_LEFT,
                formatter: fn($value, $row) => $this->formatName($row),
                colorizer: fn($value, $row, $selected) => $this->getNameColor($row, $selected),
            ),
            // Size column: fixed width, right-aligned, with size formatter
            new TableColumn(
                key: 'size',
                label: 'Size',
                width: 18,
                align: TableColumn::ALIGN_RIGHT,
                formatter: fn($value, $row) => $row['isDir'] 
                    ? '<DIR>' 
                    : $this->fileSystem->formatSize($value),
            ),
            // Modified column: fixed width, right-aligned, with date formatter
            new TableColumn(
                key: 'modified',
                label: 'Modified',
                width: 21,
                align: TableColumn::ALIGN_RIGHT,
                formatter: fn($value) => date('Y-m-d H:i:s', $value),
            ),
        ], showHeader: true);
        
        // Wire up callbacks
        $this->table->onSelect(function (array $row, int $index): void {
            if ($row['isDir']) {
                $this->navigateToDirectory($row['path']);
            } else {
                $this->openFile($row['path']);
            }
        });
        
        $this->table->onChange(function (array $row, int $index): void {
            $this->updatePreview($row);
        });
    }
    
    private function formatName(array $row): string
    {
        $icon = $row['isDir'] ? '/' : ' ';
        return "{$icon} {$row['name']}";
    }
    
    private function getNameColor(array $row, bool $selected): string
    {
        if ($selected && $this->isFocused()) {
            return ColorScheme::SELECTED_TEXT;
        }
        
        if ($row['isDir']) {
            return ColorScheme::combine(
                ColorScheme::BG_BLUE, 
                ColorScheme::FG_BRIGHT_WHITE
            );
        }
        
        return ColorScheme::NORMAL_TEXT;
    }
}
```

---

## Advanced Example: Process List

```php
$table = new TableComponent([
    new TableColumn('pid', 'PID', 10, TableColumn::ALIGN_RIGHT),
    new TableColumn('name', 'Name', '*', TableColumn::ALIGN_LEFT),
    new TableColumn('cpu', 'CPU %', 10, TableColumn::ALIGN_RIGHT,
        formatter: fn($value) => number_format($value, 1) . '%',
        colorizer: function($value, $row, $selected) {
            if ($selected) {return ColorScheme::SELECTED_TEXT;}
            if ($value > 80) {return ColorScheme::ERROR_TEXT;}
            if ($value > 50) {return ColorScheme::WARNING_TEXT;}
            return ColorScheme::NORMAL_TEXT;
        }
    ),
    new TableColumn('memory', 'Memory', 12, TableColumn::ALIGN_RIGHT,
        formatter: fn($value) => $this->formatMemory($value)
    ),
    new TableColumn('status', 'Status', 12, TableColumn::ALIGN_CENTER,
        colorizer: function($value, $row, $selected) {
            if ($selected) {return ColorScheme::SELECTED_TEXT;}
            return match($value) {
                'Running' => ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_GREEN),
                'Stopped' => ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_RED),
                'Sleeping' => ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW),
                default => ColorScheme::NORMAL_TEXT,
            };
        }
    ),
]);

$table->setRows([
    ['pid' => 1234, 'name' => 'nginx', 'cpu' => 12.5, 'memory' => 1048576, 'status' => 'Running'],
    ['pid' => 5678, 'name' => 'php-fpm', 'cpu' => 85.2, 'memory' => 2097152, 'status' => 'Running'],
    ['pid' => 9012, 'name' => 'mysql', 'cpu' => 45.8, 'memory' => 4194304, 'status' => 'Running'],
]);
```

---

## Advanced Example: Command List

```php
$table = new TableComponent([
    new TableColumn('name', 'Command', '40%', TableColumn::ALIGN_LEFT,
        formatter: function($value, $row) {
            // Highlight namespace
            if (str_contains($value, ':')) {
                [$namespace, $command] = explode(':', $value, 2);
                return "{$namespace}:{$command}";
            }
            return $value;
        }
    ),
    new TableColumn('description', 'Description', '*', TableColumn::ALIGN_LEFT,
        formatter: fn($value) => mb_substr($value, 0, 50) . (mb_strlen($value) > 50 ? '...' : '')
    ),
    new TableColumn('args', 'Args', 8, TableColumn::ALIGN_CENTER,
        formatter: fn($value) => count($value),
        colorizer: function($value, $row, $selected) {
            if ($selected) {return ColorScheme::SELECTED_TEXT;}
            return count($value) > 0 
                ? ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW)
                : ColorScheme::NORMAL_TEXT;
        }
    ),
]);
```

---

## Keyboard Navigation

The table handles the following keys automatically:

- **↑/↓** - Navigate up/down
- **Page Up/Down** - Scroll by page
- **Home/End** - Jump to first/last row
- **Enter** - Trigger `onSelect` callback
- Changes trigger `onChange` callback

All navigation automatically scrolls to keep selection visible.

---

## Empty State

When no rows are set, the table displays a centered "(No data)" message.

```php
$table->setRows([]); // Shows empty state
```

---

## Header Toggle

```php
// With header (default)
$table = new TableComponent($columns, showHeader: true);

// Without header
$table = new TableComponent($columns, showHeader: false);

// Toggle at runtime
$table->setShowHeader(false);
```

---

## Scrollbar

A scrollbar automatically appears on the right edge when:

- Total rows > visible rows
- Shows thumb position and size proportional to content

---

## Performance Considerations

- **Formatter/Colorizer Calls**: Called once per visible row per frame
- **Keep formatters fast**: Avoid heavy computation in formatters
- **Cache computed values**: Store formatted values in row data if expensive
- **Lazy loading**: Only format/colorize visible rows (already handled)

```php
// ❌ Bad: Expensive operation in formatter
formatter: fn($value) => $this->callExpensiveAPI($value)

// ✅ Good: Pre-compute and store in row data
$rows = array_map(function($item) {
    $item['formatted_value'] = $this->callExpensiveAPI($item['value']);
    return $item;
}, $rawData);
```

---

## Comparison with ListComponent

| Feature        | ListComponent   | TableComponent                |
|----------------|-----------------|-------------------------------|
| Data Structure | `array<string>` | `array<array<string, mixed>>` |
| Columns        | Single column   | Multiple columns              |
| Width Control  | Full width      | Per-column width specs        |
| Formatting     | Simple text     | Custom formatters per column  |
| Coloring       | Row-based       | Per-column colorizers         |
| Header         | No header       | Optional header row           |
| Use Case       | Simple lists    | Tabular data                  |

**When to use ListComponent:**

- Simple string lists
- Single-column display
- Minimal formatting needs

**When to use TableComponent:**

- Multi-column data
- Need column alignment
- Per-column formatting/coloring
- Structured data display

---

## Migration Guide: ListComponent → TableComponent

```php
// Before: ListComponent
$list = new ListComponent(['Item 1', 'Item 2', 'Item 3']);

// After: TableComponent
$table = new TableComponent([
    new TableColumn('item', 'Item', '*'),
]);
$table->setRows([
    ['item' => 'Item 1'],
    ['item' => 'Item 2'],
    ['item' => 'Item 3'],
]);
```

---

## Best Practices

1. **Use meaningful column keys** - They're used for data access
2. **Right-align numbers and dates** - Improves readability
3. **Use flex (*) for main content columns** - Adapts to terminal size
4. **Keep formatters pure** - No side effects
5. **Cache expensive computations** - Don't format in real-time if slow
6. **Test with various terminal widths** - Ensure columns behave correctly
7. **Provide sensible empty states** - Custom messages when no data

---

## Common Patterns

### Pattern 1: Icon + Text Column

```php
new TableColumn(
    key: 'name',
    label: 'Name',
    width: '*',
    formatter: fn($value, $row) => "{$row['icon']} {$value}"
)
```

### Pattern 2: Conditional Coloring

```php
new TableColumn(
    key: 'status',
    label: 'Status',
    width: 15,
    colorizer: fn($value, $row, $selected) => 
        $selected ? ColorScheme::SELECTED_TEXT :
        ($row['error'] ? ColorScheme::ERROR_TEXT : ColorScheme::NORMAL_TEXT)
)
```

### Pattern 3: Percentage Bar

```php
new TableColumn(
    key: 'progress',
    label: 'Progress',
    width: 20,
    formatter: function($value, $row) {
        $bars = (int)($value / 5);
        return str_repeat('█', $bars) . str_repeat('░', 20 - $bars);
    }
)
```

### Pattern 4: Truncated Long Text

```php
new TableColumn(
    key: 'description',
    label: 'Description',
    width: '*',
    formatter: fn($value) => 
        mb_strlen($value) > 50 ? mb_substr($value, 0, 47) . '...' : $value
)
```

---

## Troubleshooting

### Issue: Columns Don't Align Properly

**Solution:** Ensure total width specifications don't exceed 100% or available space.

### Issue: Text Truncated Unexpectedly

**Solution:** Check column width. Use flex (`*`) for variable-length content.

### Issue: Custom Colors Not Showing

**Solution:** Ensure colorizer returns proper ANSI codes. Check focus state.

### Issue: Selection Not Visible

**Solution:** Ensure component has focus via `setFocused(true)`.

### Issue: Header Missing

**Solution:** Check `showHeader` parameter in constructor or call `setShowHeader(true)`.

---

## Future Enhancements

Potential future features:

- Column sorting (click header to sort)
- Column resizing (drag column borders)
- Multi-select mode (Ctrl+Click)
- Cell editing (inline editing)
- Column reordering
- Fixed columns (freeze first N columns)
- Row grouping/hierarchy
- Export to CSV/JSON

---

## Summary

The `TableComponent` provides a powerful, flexible foundation for displaying structured data in terminal UIs. Its
combination of:

- Flexible column widths
- Custom formatters and colorizers
- Built-in keyboard navigation
- Scrolling support

...makes it suitable for a wide range of use cases from file browsers to process monitors to command lists.

**Key Takeaway:** Extract table logic into reusable components = cleaner code, easier maintenance, and consistent UX
across your application.
