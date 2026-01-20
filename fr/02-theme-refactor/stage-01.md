# Stage 1: Create ThemeContext and Value Objects

## Overview

Create the foundational value objects and ThemeContext class that will replace static ColorScheme properties. This
provides a testable, injectable alternative to the current global state.

## Files

CREATE:

- `src/UI/Theme/ColorSet.php` - Background/foreground pair value object
- `src/UI/Theme/BorderColorSet.php` - Active/inactive border colors
- `src/UI/Theme/SemanticColorSet.php` - Error/warning/highlight colors
- `src/UI/Theme/ThemeContext.php` - Main injectable context class

MODIFY:

- `src/UI/Theme/ThemeInterface.php` - Add optional value object methods

## Code References

- `src/UI/Theme/ColorScheme.php:85-115` - Static properties to replace
- `src/UI/Theme/AbstractTheme.php:10-30` - Combined color methods pattern

## Implementation Details

### ColorSet Value Object

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

/**
 * Immutable value object for background/foreground color pairs
 */
final readonly class ColorSet
{
    public function __construct(
        public string $background,
        public string $foreground,
    ) {}
    
    /**
     * Get combined ANSI code (bg + fg)
     */
    public function combined(): string
    {
        return $this->background . $this->foreground;
    }
    
    /**
     * Create with additional style (bold, italic, etc.)
     */
    public function withStyle(string $style): string
    {
        return $this->background . $this->foreground . $style;
    }
}
```

### BorderColorSet Value Object

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

final readonly class BorderColorSet
{
    public function __construct(
        public string $active,
        public string $inactive,
    ) {}
}
```

### SemanticColorSet Value Object

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

final readonly class SemanticColorSet
{
    public function __construct(
        public string $error,
        public string $warning,
        public string $highlight,
        public string $scrollbar,
    ) {}
}
```

### ThemeContext Class

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

/**
 * Injectable theme context - replaces static ColorScheme properties
 */
final class ThemeContext
{
    private readonly ColorSet $normal;
    private readonly ColorSet $menu;
    private readonly ColorSet $status;
    private readonly ColorSet $selected;
    private readonly ColorSet $input;
    private readonly BorderColorSet $borders;
    private readonly SemanticColorSet $semantic;
    
    public function __construct(
        private readonly ThemeInterface $theme,
    ) {
        // Pre-compute color sets for efficiency
        $this->normal = new ColorSet($theme->getNormalBg(), $theme->getNormalFg());
        $this->menu = new ColorSet($theme->getMenuBg(), $theme->getMenuFg());
        $this->status = new ColorSet($theme->getStatusBg(), $theme->getStatusFg());
        $this->selected = new ColorSet($theme->getSelectedBg(), $theme->getSelectedFg());
        $this->input = new ColorSet($theme->getInputBg(), $theme->getInputFg());
        $this->borders = new BorderColorSet($theme->getActiveBorder(), $theme->getInactiveBorder());
        $this->semantic = new SemanticColorSet(
            $theme->getErrorText(),
            $theme->getWarningText(),
            $theme->getHighlightText(),
            $theme->getScrollbar(),
        );
    }
    
    public function getTheme(): ThemeInterface
    {
        return $this->theme;
    }
    
    // Direct accessors (for backward compatibility / convenience)
    public function getNormalText(): string { return $this->normal->combined(); }
    public function getSelectedText(): string { return $this->selected->combined(); }
    public function getMenuText(): string { return $this->menu->combined(); }
    public function getStatusText(): string { return $this->status->combined(); }
    public function getInputText(): string { return $this->input->combined(); }
    
    // Value object accessors
    public function getNormalColors(): ColorSet { return $this->normal; }
    public function getMenuColors(): ColorSet { return $this->menu; }
    public function getStatusColors(): ColorSet { return $this->status; }
    public function getSelectedColors(): ColorSet { return $this->selected; }
    public function getInputColors(): ColorSet { return $this->input; }
    public function getBorderColors(): BorderColorSet { return $this->borders; }
    public function getSemanticColors(): SemanticColorSet { return $this->semantic; }
    
    // Additional convenience methods
    public function getActiveBorder(): string { return $this->borders->active; }
    public function getInactiveBorder(): string { return $this->borders->inactive; }
    public function getErrorText(): string { return $this->semantic->error; }
    public function getWarningText(): string { return $this->semantic->warning; }
    public function getHighlightText(): string { return $this->semantic->highlight; }
    public function getScrollbar(): string { return $this->semantic->scrollbar; }
    
    // Special accessors from theme
    public function getMenuHotkey(): string { return $this->theme->getMenuHotkey(); }
    public function getStatusKey(): string { return $this->theme->getStatusKey(); }
    public function getInputCursor(): string { return $this->theme->getInputCursor(); }
    public function getNormalBg(): string { return $this->normal->background; }
}
```

## Definition of Done

- [ ] `ColorSet` value object created with `combined()` and `withStyle()` methods
- [ ] `BorderColorSet` value object created
- [ ] `SemanticColorSet` value object created
- [ ] `ThemeContext` class created with all color accessors
- [ ] All value objects are `readonly`
- [ ] Unit tests created for ThemeContext
- [ ] No changes to existing components yet (pure addition)

## Dependencies

**Requires**: None (first stage)
**Enables**: Stage 2 (components can start using ThemeContext)
