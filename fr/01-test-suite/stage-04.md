# Stage 4: Unit Tests - Layout System

## Overview

Create unit tests for the layout system: GridLayout, StackLayout, SplitLayout, and TabContainer. These components handle
complex sizing calculations and child component arrangement.

## Files

CREATE:

- `tests/Unit/Component/Container/GridLayoutTest.php`
- `tests/Unit/Component/Container/StackLayoutTest.php`
- `tests/Unit/Component/Container/SplitLayoutTest.php`
- `tests/Unit/Component/Container/TabContainerTest.php`

## Code References

- `src/UI/Component/Container/GridLayout.php:1-250` - Grid with columns/rows
- `src/UI/Component/Container/StackLayout.php:1-200` - Vertical/horizontal stacking
- `src/UI/Component/Container/SplitLayout.php:1-80` - Two-panel splits
- `src/UI/Component/Container/TabContainer.php:1-180` - Tab management

## Implementation Details

### GridLayoutTest Key Tests

```php
public function test_creates_column_based_layout(): void
public function test_creates_row_based_layout(): void
public function test_throws_when_both_columns_and_rows_specified(): void
public function test_throws_when_neither_columns_nor_rows_specified(): void
public function test_setColumn_assigns_component(): void
public function test_setRow_assigns_component(): void
public function test_calculates_fixed_track_sizes(): void
public function test_calculates_percentage_track_sizes(): void
public function test_calculates_flex_track_sizes(): void
public function test_distributes_gap_between_tracks(): void
public function test_renders_components_at_correct_positions(): void
public function test_propagates_input_to_focused_component(): void
public function test_measure_returns_combined_dimensions(): void
public function test_layout_applies_sizes_to_children(): void
```

### StackLayoutTest Key Tests

```php
public function test_creates_vertical_stack(): void
public function test_creates_horizontal_stack(): void
public function test_adds_children_with_fixed_size(): void
public function test_adds_children_with_percentage_size(): void
public function test_adds_children_with_flex_size(): void
public function test_calculates_child_sizes_correctly(): void
public function test_applies_gap_between_children(): void
public function test_renders_children_in_sequence(): void
public function test_handles_input_for_focused_child(): void
public function test_measure_sums_main_axis(): void
public function test_measure_takes_max_cross_axis(): void
```

### SplitLayoutTest Key Tests

```php
public function test_creates_horizontal_split(): void
public function test_creates_vertical_split(): void
public function test_applies_ratio_correctly(): void
public function test_renders_both_panels(): void
public function test_propagates_input(): void
public function test_applies_gap_between_panels(): void
```

### TabContainerTest Key Tests

```php
public function test_adds_tabs(): void
public function test_gets_active_tab(): void
public function test_switches_to_tab_by_index(): void
public function test_next_tab_cycles(): void
public function test_previous_tab_cycles(): void
public function test_renders_tab_headers(): void
public function test_renders_active_tab_content(): void
public function test_handles_ctrl_left_right_for_navigation(): void
public function test_delegates_input_to_active_tab(): void
public function test_calls_onActivate_when_switching(): void
public function test_calls_onDeactivate_when_switching(): void
public function test_propagates_focus_to_active_tab(): void
```

### Testing Patterns

```php
// Pattern for testing layout calculations
public function test_calculates_track_sizes(): void
{
    $grid = new GridLayout(columns: ['100', '50%', '*']);
    
    // Mock components
    $comp1 = $this->createMock(ComponentInterface::class);
    $comp2 = $this->createMock(ComponentInterface::class);
    $comp3 = $this->createMock(ComponentInterface::class);
    
    $grid->setColumn(0, $comp1);
    $grid->setColumn(1, $comp2);
    $grid->setColumn(2, $comp3);
    
    $measured = $grid->measure(400, 100);
    
    // 100 fixed + 200 (50%) + 100 (remaining) = 400
    $this->assertEquals(400, $measured['width']);
}

// Pattern for testing component positioning
public function test_renders_at_correct_positions(): void
{
    $renderer = $this->createMockRenderer(100, 50);
    
    $stack = new StackLayout(Direction::VERTICAL);
    
    $header = new TextDisplay('Header');
    $content = new TextDisplay('Content');
    
    $stack->addChild($header, size: 5);
    $stack->addChild($content);
    
    $stack->render($renderer, 0, 0, 100, 50);
    
    // Header should be at y=0
    $this->assertRenderedAt($renderer, 0, 0, 'Header');
    // Content should be at y=5
    $this->assertRenderedAt($renderer, 0, 5, 'Content');
}

// Pattern for testing tab switching
public function test_switches_tabs_correctly(): void
{
    $tab1 = $this->createMock(TabInterface::class);
    $tab1->expects($this->once())->method('onDeactivate');
    
    $tab2 = $this->createMock(TabInterface::class);
    $tab2->expects($this->once())->method('onActivate');
    
    $container = new TabContainer([$tab1, $tab2]);
    
    $container->switchToTab(1);
    
    $this->assertEquals(1, $container->getActiveTabIndex());
}
```

## Definition of Done

- [ ] GridLayoutTest covers column and row modes with all size types
- [ ] StackLayoutTest covers both directions with various sizes
- [ ] SplitLayoutTest covers horizontal and vertical splits
- [ ] TabContainerTest covers navigation and lifecycle callbacks
- [ ] All tests pass with `vendor/bin/phpunit tests/Unit/Component/Container`
- [ ] Tests verify positioning calculations
- [ ] Tests verify input propagation

## Dependencies

**Requires**: Stage 1 (test infrastructure), Stage 3 (SizeUnit tests)
**Enables**: Stage 5 (integration tests for screens using layouts)
