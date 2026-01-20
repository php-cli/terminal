# Stage 2: Apply Scrollbar to Existing Components

## Overview

Replace duplicate `drawScrollbar()` methods in TableComponent, ListComponent, and TextDisplay with the new `Scrollbar` class. Each component will create its own Scrollbar instance and delegate rendering to it.

## Files

MODIFY:
- `src/UI/Component/Display/TableComponent.php` - Use Scrollbar, remove drawScrollbar()
- `src/UI/Component/Display/ListComponent.php` - Use Scrollbar, remove drawScrollbar()
- `src/UI/Component/Display/TextDisplay.php` - Use Scrollbar, remove drawScrollbar()

## Code References

- `src/UI/Component/Display/TableComponent.php:315-330` - drawScrollbar() to remove
- `src/UI/Component/Display/TableComponent.php:150-155` - Where scrollbar is called in render()
- `src/UI/Component/Display/ListComponent.php:130-135` - Where scrollbar is called
- `src/UI/Component/Display/TextDisplay.php:115-120` - Where scrollbar is called

## Implementation Details

### Changes per Component

#### TableComponent

```php
// Add property
private readonly Scrollbar $scrollbar;

// In constructor
$this->scrollbar = new Scrollbar();

// In render() - replace:
$needsScrollbar = \count($this->rows) > ($this->showHeader ? $height - 2 : $height);
// With:
$needsScrollbar = Scrollbar::needsScrollbar(\count($this->rows), $this->visibleRows);

// Replace drawScrollbar() call:
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

// DELETE: private function drawScrollbar()
```

#### ListComponent

```php
// Add property
private readonly Scrollbar $scrollbar;

// In constructor  
$this->scrollbar = new Scrollbar();

// In render() - replace:
$needsScrollbar = \count($this->items) > $this->visibleRows;
// With:
$needsScrollbar = Scrollbar::needsScrollbar(\count($this->items), $this->visibleRows);

// Replace drawScrollbar() call with $this->scrollbar->render(...)

// DELETE: private function drawScrollbar()
```

#### TextDisplay

```php
// Add property
private readonly Scrollbar $scrollbar;

// In constructor
$this->scrollbar = new Scrollbar();

// In render() - replace:
$needsScrollbar = \count($this->lines) > $this->visibleLines;
// With:
$needsScrollbar = Scrollbar::needsScrollbar(\count($this->lines), $this->visibleLines);

// Replace drawScrollbar() call with $this->scrollbar->render(...)

// DELETE: private function drawScrollbar()
```

### Import Statement

Add to each file:
```php
use Butschster\Commander\UI\Component\Display\Scrollbar;
```

## Definition of Done

- [ ] TableComponent uses Scrollbar class
- [ ] ListComponent uses Scrollbar class
- [ ] TextDisplay uses Scrollbar class
- [ ] All `drawScrollbar()` private methods removed (3 total)
- [ ] No duplicate scrollbar logic remains in codebase
- [ ] Manual test: scrollbar renders correctly in all three components
- [ ] No breaking changes to public API

## Dependencies

**Requires**: Stage 1 (Scrollbar class must exist)
**Enables**: Stage 3 (tests can verify behavior)
