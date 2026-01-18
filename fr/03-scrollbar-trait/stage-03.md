# Stage 3: Add Unit Tests

## Overview

Create comprehensive unit tests for the Scrollbar class. Since Scrollbar is now a standalone class (not a trait), it can be tested in isolation without instantiating UI components.

## Files

CREATE:
- `tests/Unit/UI/Component/Display/ScrollbarTest.php` - Unit tests for Scrollbar

## Code References

- `tests/Unit/` - Existing test structure and patterns
- `src/UI/Component/Display/Scrollbar.php` - Class under test

## Implementation Details

### Test Structure

```php
final class ScrollbarTest extends TestCase
{
    // needsScrollbar() tests
    public function test_needs_scrollbar_when_items_exceed_visible(): void
    public function test_no_scrollbar_when_items_fit(): void
    public function test_no_scrollbar_when_items_equal_visible(): void
    
    // render() thumb size tests
    public function test_thumb_height_proportional_to_visible_ratio(): void
    public function test_thumb_minimum_height_is_one(): void
    
    // render() position tests
    public function test_thumb_at_top_when_scroll_offset_zero(): void
    public function test_thumb_at_bottom_when_scrolled_to_end(): void
    public function test_thumb_position_in_middle(): void
    
    // Edge cases
    public function test_renders_nothing_when_no_scrollbar_needed(): void
    public function test_single_item_list(): void
}
```

### Mock Setup

Need to mock:
- `Renderer` - capture `writeAt()` calls to verify output
- `ThemeContext` - return test color for `getScrollbar()`

```php
private function createRendererMock(): Renderer&MockObject
{
    $renderer = $this->createMock(Renderer::class);
    $renderer->method('getThemeContext')->willReturn($this->createThemeMock());
    return $renderer;
}

private function createThemeMock(): ThemeContext&MockObject
{
    $theme = $this->createMock(ThemeContext::class);
    $theme->method('getScrollbar')->willReturn("\e[37m");
    return $theme;
}
```

### Key Test Cases

| Scenario | totalItems | visibleItems | scrollOffset | Expected thumbPos | Expected thumbHeight |
|----------|------------|--------------|--------------|-------------------|---------------------|
| Top position | 100 | 10 | 0 | 0 | 1 |
| Middle position | 100 | 10 | 45 | 4-5 | 1 |
| Bottom position | 100 | 10 | 90 | 9 | 1 |
| Large thumb | 20 | 10 | 0 | 0 | 5 |
| Exact fit | 10 | 10 | 0 | N/A (no render) | N/A |

### Verification Strategy

Capture all `writeAt()` calls and verify:
1. Correct number of calls (= height)
2. Correct x, y coordinates
3. Correct characters (█ for thumb, ░ for track)
4. Thumb characters at expected positions

## Definition of Done

- [ ] ScrollbarTest.php created with all test methods
- [ ] `needsScrollbar()` tested for true/false/edge cases
- [ ] Thumb height calculation verified
- [ ] Thumb position calculation verified for top/middle/bottom
- [ ] Edge cases covered (0 items, 1 item, exact fit)
- [ ] All tests pass
- [ ] Tests follow project conventions

## Dependencies

**Requires**: Stage 1 (Scrollbar class), Stage 2 (verify implementation is correct)
**Enables**: Confidence in refactoring, regression prevention
