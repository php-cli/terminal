# Feature: Extract Scrollbar Component (DRY Refactoring)

## Overview

Extract duplicated scrollbar rendering logic from TableComponent, ListComponent, and TextDisplay into a reusable `Scrollbar` class.
This follows composition over inheritance principle - Scrollbar is a standalone rendering helper, not a trait.

## Architecture Decision

**Chosen**: Separate `Scrollbar` class (not a trait)

**Why**:
- Testable in isolation
- Single Responsibility - dedicated to scrollbar rendering
- Extensible - can add horizontal scrollbar, different styles later
- Explicit dependencies - clear what each component uses
- Follows existing patterns - similar to how `Renderer` is a helper, not a component

## Stage Dependencies

```
Stage 1 (Create Scrollbar) → Stage 2 (Apply to Components) → Stage 3 (Tests)
```

## Development Progress

### Stage 1: Create Scrollbar Class

- [x] Substep 1.1: Create `src/UI/Component/Display/Scrollbar.php`
- [x] Substep 1.2: Implement `render()` method with all required parameters
- [x] Substep 1.3: Add `needsScrollbar()` static helper method
- [x] Substep 1.4: Add PHPDoc with usage example

**Notes**: Created as final class with const chars for thumb/track
**Status**: Completed
**Completed**: 2025-01-18

---

### Stage 2: Apply Scrollbar to Existing Components

- [x] Substep 2.1: Update `TableComponent` - add Scrollbar instance, replace drawScrollbar()
- [x] Substep 2.2: Update `ListComponent` - add Scrollbar instance, replace drawScrollbar()
- [x] Substep 2.3: Update `TextDisplay` - add Scrollbar instance, replace drawScrollbar()
- [x] Substep 2.4: Remove all duplicate `drawScrollbar()` private methods
- [x] Substep 2.5: Verify all three components render scrollbars correctly

**Notes**: All components now use Scrollbar class via composition
**Status**: Completed
**Completed**: 2025-01-18

---

### Stage 3: Add Unit Tests

- [ ] Substep 3.1: Create `tests/Unit/UI/Component/Display/ScrollbarTest.php`
- [ ] Substep 3.2: Test `needsScrollbar()` - true/false cases
- [ ] Substep 3.3: Test thumb size calculation (few items, many items, exact fit)
- [ ] Substep 3.4: Test thumb position calculation (top, middle, bottom)
- [ ] Substep 3.5: Test edge cases (1 item, 0 items, items == visible)

**Notes**:
**Status**: Not Started
**Completed**:

---

## Codebase References

### Current Duplicate Implementations
- `src/UI/Component/Display/TableComponent.php:320-340` - drawScrollbar() method
- `src/UI/Component/Display/ListComponent.php:180-200` - drawScrollbar() method
- `src/UI/Component/Display/TextDisplay.php:200-220` - drawScrollbar() method

### Related Classes
- `src/UI/Theme/ThemeContext.php:95` - getScrollbar() method for color
- `src/Infrastructure/Terminal/Renderer.php` - writeAt() method for rendering

### Test Patterns
- `tests/Unit/` - existing test structure to follow

## New Class Design

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Display;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Theme\ThemeContext;

/**
 * Scrollbar rendering helper for scrollable components.
 *
 * Usage:
 * ```php
 * $scrollbar = new Scrollbar();
 * 
 * if (Scrollbar::needsScrollbar($totalItems, $visibleItems)) {
 *     $scrollbar->render($renderer, $x, $y, $height, $theme, $totalItems, $visibleItems, $scrollOffset);
 * }
 * ```
 */
final class Scrollbar
{
    private const string THUMB_CHAR = '█';
    private const string TRACK_CHAR = '░';

    /**
     * Check if scrollbar is needed
     */
    public static function needsScrollbar(int $totalItems, int $visibleItems): bool
    {
        return $totalItems > $visibleItems;
    }

    /**
     * Render vertical scrollbar at specified position
     */
    public function render(
        Renderer $renderer,
        int $x,
        int $y,
        int $height,
        ThemeContext $theme,
        int $totalItems,
        int $visibleItems,
        int $scrollOffset,
    ): void {
        if (!self::needsScrollbar($totalItems, $visibleItems)) {
            return;
        }

        $thumbHeight = \max(1, (int) ($height * $visibleItems / $totalItems));
        $thumbPosition = (int) ($height * $scrollOffset / $totalItems);

        for ($i = 0; $i < $height; $i++) {
            $isThumb = $i >= $thumbPosition && $i < $thumbPosition + $thumbHeight;
            $char = $isThumb ? self::THUMB_CHAR : self::TRACK_CHAR;
            $renderer->writeAt($x, $y + $i, $char, $theme->getScrollbar());
        }
    }
}
```

## Usage in Components (After Refactoring)

```php
final class TableComponent extends AbstractComponent
{
    private readonly Scrollbar $scrollbar;

    public function __construct(...)
    {
        $this->scrollbar = new Scrollbar();
    }

    public function render(...): void
    {
        $needsScrollbar = Scrollbar::needsScrollbar(count($this->rows), $this->visibleRows);
        $contentWidth = $needsScrollbar ? $width - 1 : $width;
        
        // ... render content ...
        
        if ($needsScrollbar) {
            $this->scrollbar->render(
                $renderer,
                x: $x + $contentWidth,
                y: $currentY,
                height: $this->visibleRows,
                theme: $theme,
                totalItems: count($this->rows),
                visibleItems: $this->visibleRows,
                scrollOffset: $this->scrollOffset,
            );
        }
    }
}
```

## Usage Instructions

⚠️ Keep this checklist updated:

- Mark completed substeps immediately with [x]
- Add notes about deviations or challenges
- Document decisions differing from plan
- Update status when starting/completing stages
- Test scrollbar rendering manually in terminal after Stage 2
