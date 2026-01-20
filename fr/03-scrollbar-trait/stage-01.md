# Stage 1: Create Scrollbar Class

## Overview

Create a standalone `Scrollbar` class that encapsulates scrollbar rendering logic. This class is not a UI component (doesn't implement `ComponentInterface`), but a rendering helper - similar to how `Renderer` itself is a tool, not a component.

## Files

CREATE:
- `src/UI/Component/Display/Scrollbar.php` - Scrollbar rendering helper

## Code References

- `src/UI/Component/Display/TableComponent.php:315-330` - Current scrollbar implementation to extract
- `src/Infrastructure/Terminal/Renderer.php:16` - Renderer class pattern (helper, not component)
- `src/UI/Theme/ThemeContext.php:100` - getScrollbar() for color access

## Implementation Details

### Class Structure

```php
final class Scrollbar
{
    private const string THUMB_CHAR = '█';
    private const string TRACK_CHAR = '░';

    public static function needsScrollbar(int $totalItems, int $visibleItems): bool
    public function render(Renderer, int $x, int $y, int $height, ThemeContext, int $totalItems, int $visibleItems, int $scrollOffset): void
}
```

### Design Decisions

1. **Static `needsScrollbar()`** - Pure function, no state needed, convenient for checking before reserving space
2. **Instance `render()`** - Allows future extension (custom chars, horizontal mode) without breaking API
3. **Constants for chars** - Single source of truth, easy to customize later
4. **All params explicit** - No magic property access, clear contract

### Thumb Calculation Algorithm

```php
$thumbHeight = max(1, (int)($height * $visibleItems / $totalItems));
$thumbPosition = (int)($height * $scrollOffset / $totalItems);
```

- `thumbHeight`: Proportional to visible/total ratio, minimum 1 char
- `thumbPosition`: Proportional to scroll offset position

## Definition of Done

- [ ] `Scrollbar.php` created in correct namespace
- [ ] `needsScrollbar()` returns correct bool for edge cases
- [ ] `render()` draws thumb and track chars at correct positions
- [ ] PHPDoc with usage example included
- [ ] No dependencies on specific component internals
- [ ] File follows project coding standards (strict types, final class)

## Dependencies

**Requires**: None (foundation stage)
**Enables**: Stage 2 (component integration)
