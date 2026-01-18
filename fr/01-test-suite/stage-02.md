# Stage 2: Unit Tests - Core Components

## Overview

Create unit tests for the core display components: TableComponent, ListComponent, and TextDisplay. These are the most
complex and frequently used components, requiring thorough testing of rendering, navigation, and callbacks.

## Files

CREATE:

- `tests/Unit/Component/Display/TableComponentTest.php`
- `tests/Unit/Component/Display/ListComponentTest.php`
- `tests/Unit/Component/Display/TextDisplayTest.php`
- `tests/Unit/Component/Layout/PanelTest.php`

## Code References

- `src/UI/Component/Display/TableComponent.php:1-350` - Full component implementation
- `src/UI/Component/Display/ListComponent.php:1-200` - Simpler list component
- `src/UI/Component/Display/TextDisplay.php:1-180` - Text with scrolling
- `src/UI/Component/Layout/Panel.php:1-120` - Border and title rendering

## Implementation Details

### TableComponentTest Key Tests

```php
public function test_renders_header_row(): void
public function test_renders_rows_with_columns(): void
public function test_handles_up_down_navigation(): void
public function test_handles_page_up_page_down(): void
public function test_handles_home_end_keys(): void
public function test_triggers_onSelect_callback_on_enter(): void
public function test_triggers_onChange_callback_on_navigation(): void
public function test_calculates_fixed_column_widths(): void
public function test_calculates_percentage_column_widths(): void
public function test_calculates_flex_column_widths(): void
public function test_renders_scrollbar_when_needed(): void
public function test_adjusts_scroll_to_keep_selection_visible(): void
public function test_handles_empty_rows(): void
public function test_applies_custom_formatter(): void
public function test_applies_custom_colorizer(): void
```

### ListComponentTest Key Tests

```php
public function test_renders_items(): void
public function test_highlights_selected_item_when_focused(): void
public function test_navigation_updates_selection(): void
public function test_triggers_callbacks(): void
public function test_renders_empty_state(): void
public function test_renders_scrollbar_for_long_lists(): void
```

### TextDisplayTest Key Tests

```php
public function test_renders_text_lines(): void
public function test_wraps_long_lines(): void
public function test_scrolls_with_arrow_keys(): void
public function test_auto_scroll_to_bottom(): void
public function test_appends_text(): void
public function test_clears_text(): void
```

### PanelTest Key Tests

```php
public function test_renders_border(): void
public function test_renders_title(): void
public function test_renders_content_inside(): void
public function test_focused_state_changes_border_color(): void
public function test_propagates_focus_to_content(): void
```

### Testing Patterns

```php
// Pattern for testing rendering
public function test_renders_header_row(): void
{
    $renderer = $this->createMockRenderer(80, 24);
    
    $table = new TableComponent([
        new TableColumn('name', 'Name', '*'),
        new TableColumn('value', 'Value', 20),
    ]);
    $table->setRows([['name' => 'Item 1', 'value' => '100']]);
    
    $table->render($renderer, 0, 0, 80, 10);
    
    $this->assertRenderedAt($renderer, 0, 0, 'Name');
    $this->assertRenderedAt($renderer, 60, 0, 'Value');
}

// Pattern for testing input handling
public function test_handles_down_navigation(): void
{
    $table = new TableComponent([...]);
    $table->setRows([['name' => 'A'], ['name' => 'B'], ['name' => 'C']]);
    $table->setFocused(true);
    
    $this->assertEquals(0, $table->getSelectedIndex());
    
    $handled = $table->handleInput('DOWN');
    
    $this->assertTrue($handled);
    $this->assertEquals(1, $table->getSelectedIndex());
}

// Pattern for testing callbacks
public function test_triggers_onSelect_callback(): void
{
    $table = new TableComponent([...]);
    $table->setRows([['name' => 'Test']]);
    $table->setFocused(true);
    
    $called = false;
    $receivedRow = null;
    $table->onSelect(function ($row, $index) use (&$called, &$receivedRow) {
        $called = true;
        $receivedRow = $row;
    });
    
    $table->handleInput('ENTER');
    
    $this->assertTrue($called);
    $this->assertEquals(['name' => 'Test'], $receivedRow);
}
```

## Definition of Done

- [ ] TableComponentTest has 15+ test methods covering all functionality
- [ ] ListComponentTest has 6+ test methods
- [ ] TextDisplayTest has 6+ test methods
- [ ] PanelTest has 5+ test methods
- [ ] All tests pass with `vendor/bin/phpunit tests/Unit/Component`
- [ ] Tests cover edge cases (empty data, boundary conditions)
- [ ] Tests verify both rendering output and state changes

## Dependencies

**Requires**: Stage 1 (test infrastructure)
**Enables**: Stage 4 (integration tests use these components)
