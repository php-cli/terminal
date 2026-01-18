# Feature: Extract Scrollbar Trait (DRY Refactoring)

## Overview

Extract duplicated scrollbar rendering logic from TableComponent, ListComponent, and TextDisplay into a reusable trait.
Currently, nearly identical 15-line `drawScrollbar()` methods exist in 3+ components.

## Stage Dependencies

```
Stage 1 (Create Trait) → Stage 2 (Apply to Components) → Stage 3 (Tests)
```

## Development Progress

### Stage 1: Create ScrollbarTrait

- [ ] Substep 1.1: Create `src/UI/Component/Trait/ScrollbarTrait.php`
- [ ] Substep 1.2: Implement `drawScrollbar()` method with configurable parameters
- [ ] Substep 1.3: Add `calculateScrollbarMetrics()` helper method
- [ ] Substep 1.4: Add scrollbar style constants (characters, colors)
- [ ] Substep 1.5: Document trait usage with examples

**Notes**:
**Status**: Not Started
**Completed**:

---

### Stage 2: Apply Trait to Existing Components

- [ ] Substep 2.1: Update `TableComponent` to use ScrollbarTrait
- [ ] Substep 2.2: Update `ListComponent` to use ScrollbarTrait
- [ ] Substep 2.3: Update `TextDisplay` to use ScrollbarTrait
- [ ] Substep 2.4: Remove duplicate `drawScrollbar()` methods
- [ ] Substep 2.5: Verify all three components render scrollbars correctly

**Notes**:
**Status**: Not Started
**Completed**:

---

### Stage 3: Add Tests and Documentation

- [ ] Substep 3.1: Create `tests/Unit/Component/Trait/ScrollbarTraitTest.php`
- [ ] Substep 3.2: Test scrollbar thumb size calculation
- [ ] Substep 3.3: Test scrollbar position calculation
- [ ] Substep 3.4: Test edge cases (few items, many items, exact fit)
- [ ] Substep 3.5: Update component tests to verify scrollbar rendering

**Notes**:
**Status**: Not Started
**Completed**:

---

## Codebase References

- `src/UI/Component/Display/TableComponent.php:320-340` - Current scrollbar implementation
- `src/UI/Component/Display/ListComponent.php:180-200` - Duplicate scrollbar code
- `src/UI/Component/Display/TextDisplay.php:160-180` - Another duplicate
- `src/UI/Theme/ColorScheme.php:95` - $SCROLLBAR color constant

## Current Duplicate Code Analysis

### TableComponent (lines ~320-340)

```php
private function drawScrollbar(Renderer $renderer, int $x, int $y, int $height): void
{
    $totalItems = \count($this->rows);
    $thumbHeight = \max(1, (int) ($height * $this->visibleRows / $totalItems));
    $thumbPosition = (int) ($height * $this->scrollOffset / $totalItems);

    for ($i = 0; $i < $height; $i++) {
        $char = ($i >= $thumbPosition && $i < $thumbPosition + $thumbHeight) ? '█' : '░';
        $renderer->writeAt($x, $y + $i, $char, ColorScheme::$SCROLLBAR);
    }
}
```

### ListComponent (lines ~180-200)

```php
private function drawScrollbar(Renderer $renderer, int $x, int $y, int $height): void
{
    $totalItems = \count($this->items);
    $thumbHeight = \max(1, (int) ($height * $this->visibleRows / $totalItems));
    $thumbPosition = (int) ($height * $this->scrollOffset / $totalItems);

    for ($i = 0; $i < $height; $i++) {
        $char = ($i >= $thumbPosition && $i < $thumbPosition + $thumbHeight) ? '█' : '░';
        $renderer->writeAt($x, $y + $i, $char, ColorScheme::$SCROLLBAR);
    }
}
```

### TextDisplay (lines ~160-180)

```php
private function drawScrollbar(Renderer $renderer, int $x, int $y, int $height): void
{
    $totalLines = \count($this->lines);
    if ($totalLines <= $this->visibleLines) {
        return;
    }
    $thumbHeight = \max(1, (int) ($height * $this->visibleLines / $totalLines));
    $thumbPosition = (int) ($height * $this->scrollOffset / $totalLines);

    for ($i = 0; $i < $height; $i++) {
        $char = ($i >= $thumbPosition && $i < $thumbPosition + $thumbHeight) ? '█' : '░';
        $renderer->writeAt($x, $y + $i, $char, ColorScheme::$SCROLLBAR);
    }
}
```

## New Trait Design

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Trait;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Provides scrollbar rendering for scrollable components
 */
trait ScrollbarTrait
{
    protected string $scrollbarThumbChar = '█';
    protected string $scrollbarTrackChar = '░';
    
    /**
     * Calculate scrollbar metrics
     *
     * @return array{thumbHeight: int, thumbPosition: int}
     */
    protected function calculateScrollbarMetrics(
        int $totalItems,
        int $visibleItems,
        int $scrollOffset,
        int $trackHeight,
    ): array {
        if ($totalItems <= $visibleItems) {
            return ['thumbHeight' => $trackHeight, 'thumbPosition' => 0];
        }
        
        $thumbHeight = \max(1, (int) ($trackHeight * $visibleItems / $totalItems));
        $thumbPosition = (int) ($trackHeight * $scrollOffset / $totalItems);
        
        return [
            'thumbHeight' => $thumbHeight,
            'thumbPosition' => $thumbPosition,
        ];
    }
    
    /**
     * Draw scrollbar at specified position
     */
    protected function drawScrollbar(
        Renderer $renderer,
        int $x,
        int $y,
        int $height,
        int $totalItems,
        int $visibleItems,
        int $scrollOffset,
        ?string $color = null,
    ): void {
        if ($totalItems <= $visibleItems) {
            return; // No scrollbar needed
        }
        
        $metrics = $this->calculateScrollbarMetrics(
            $totalItems,
            $visibleItems,
            $scrollOffset,
            $height,
        );
        
        $color ??= ColorScheme::$SCROLLBAR;
        
        for ($i = 0; $i < $height; $i++) {
            $isThumb = $i >= $metrics['thumbPosition'] 
                    && $i < $metrics['thumbPosition'] + $metrics['thumbHeight'];
            $char = $isThumb ? $this->scrollbarThumbChar : $this->scrollbarTrackChar;
            $renderer->writeAt($x, $y + $i, $char, $color);
        }
    }
    
    /**
     * Check if scrollbar is needed
     */
    protected function needsScrollbar(int $totalItems, int $visibleItems): bool
    {
        return $totalItems > $visibleItems;
    }
}
```

## Usage Instructions

⚠️ Keep this checklist updated:

- Mark completed substeps immediately with [x]
- Add notes about deviations or challenges
- Document decisions differing from plan
- Update status when starting/completing stages
- Test scrollbar rendering manually in terminal after changes
