# Feature: Simplify ThemeInterface (Reduce Method Count)

## Overview

The current `ThemeInterface` has 25+ methods, making it tedious to implement new themes. Refactor to use grouped value
objects while maintaining backward compatibility.

## Stage Dependencies

```
Stage 1 (Value Objects) → Stage 2 (New Interface) → Stage 3 (Update Themes) → Stage 4 (BC Layer)
```

## Development Progress

### Stage 1: Create Color Group Value Objects

- [ ] Substep 1.1: Create `ColorPair` (bg + fg + combined)
- [ ] Substep 1.2: Create `ThemeColors` containing all color pairs
- [ ] Substep 1.3: Create `ThemeBorders` for border colors
- [ ] Substep 1.4: Create `ThemeSemantics` for error/warning/highlight
- [ ] Substep 1.5: Ensure all are readonly/immutable

**Notes**:
**Status**: Not Started
**Completed**:

---

### Stage 2: Create Simplified Theme Interface

- [ ] Substep 2.1: Create `SimpleThemeInterface` with grouped methods
- [ ] Substep 2.2: Interface should have ~8 methods instead of 25+
- [ ] Substep 2.3: Create `AbstractSimpleTheme` base implementation
- [ ] Substep 2.4: Add static factory for creating from arrays

**Notes**:
**Status**: Not Started
**Completed**:

---

### Stage 3: Update Existing Themes

- [ ] Substep 3.1: Update `MidnightTheme` to use new structure
- [ ] Substep 3.2: Update `DarkTheme` to use new structure
- [ ] Substep 3.3: Update `LightTheme` to use new structure
- [ ] Substep 3.4: Verify all themes still work correctly

**Notes**:
**Status**: Not Started
**Completed**:

---

### Stage 4: Backward Compatibility Layer

- [ ] Substep 4.1: Keep old `ThemeInterface` as deprecated
- [ ] Substep 4.2: Create adapter from old to new interface
- [ ] Substep 4.3: Update `ThemeManager` to support both
- [ ] Substep 4.4: Document migration path for custom themes

**Notes**:
**Status**: Not Started
**Completed**:

---

## Codebase References

- `src/UI/Theme/ThemeInterface.php` - Current 25+ method interface
- `src/UI/Theme/AbstractTheme.php` - Base with combined methods
- `src/UI/Theme/MidnightTheme.php` - Example implementation
- `src/UI/Theme/DarkTheme.php` - Another implementation
- `src/UI/Theme/LightTheme.php` - Light theme implementation

## Current Interface (25+ methods)

```php
interface ThemeInterface
{
    public function getName(): string;
    public function getNormalBg(): string;
    public function getNormalFg(): string;
    public function getNormalText(): string;
    public function getMenuBg(): string;
    public function getMenuFg(): string;
    public function getMenuText(): string;
    public function getMenuHotkey(): string;
    public function getStatusBg(): string;
    public function getStatusFg(): string;
    public function getStatusText(): string;
    public function getStatusKey(): string;
    public function getSelectedBg(): string;
    public function getSelectedFg(): string;
    public function getSelectedText(): string;
    public function getActiveBorder(): string;
    public function getInactiveBorder(): string;
    public function getInputBg(): string;
    public function getInputFg(): string;
    public function getInputText(): string;
    public function getInputCursor(): string;
    public function getScrollbar(): string;
    public function getErrorText(): string;
    public function getWarningText(): string;
    public function getHighlightText(): string;
}
```

## Proposed Simplified Interface (~8 methods)

```php
interface SimpleThemeInterface
{
    public function getName(): string;
    
    public function getNormalColors(): ColorPair;
    public function getMenuColors(): MenuColors;      // includes hotkey
    public function getStatusColors(): StatusColors;  // includes key highlight
    public function getSelectionColors(): ColorPair;
    public function getInputColors(): InputColors;    // includes cursor
    public function getBorderColors(): BorderColors;
    public function getSemanticColors(): SemanticColors;
}
```

## Value Objects

### ColorPair

```php
final readonly class ColorPair
{
    public function __construct(
        public string $background,
        public string $foreground,
    ) {}
    
    public function combined(): string
    {
        return $this->background . $this->foreground;
    }
    
    public function withStyle(string ...$styles): string
    {
        return $this->background . $this->foreground . implode('', $styles);
    }
}
```

### MenuColors

```php
final readonly class MenuColors
{
    public function __construct(
        public string $background,
        public string $foreground,
        public string $hotkey,
    ) {}
    
    public function combined(): string
    {
        return $this->background . $this->foreground;
    }
}
```

### SemanticColors

```php
final readonly class SemanticColors
{
    public function __construct(
        public string $error,
        public string $warning,
        public string $highlight,
        public string $scrollbar,
    ) {}
}
```

## Migration Example

### Before (verbose)

```php
class MyTheme extends AbstractTheme
{
    public function getName(): string { return 'my'; }
    public function getNormalBg(): string { return ColorScheme::BG_BLUE; }
    public function getNormalFg(): string { return ColorScheme::FG_WHITE; }
    public function getMenuBg(): string { return ColorScheme::BG_CYAN; }
    // ... 20+ more methods
}
```

### After (concise)

```php
class MyTheme implements SimpleThemeInterface
{
    public function getName(): string { return 'my'; }
    
    public function getNormalColors(): ColorPair
    {
        return new ColorPair(ColorScheme::BG_BLUE, ColorScheme::FG_WHITE);
    }
    
    public function getMenuColors(): MenuColors
    {
        return new MenuColors(
            ColorScheme::BG_CYAN,
            ColorScheme::FG_WHITE,
            ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::FG_YELLOW),
        );
    }
    // ... only 6 more methods
}
```

## Usage Instructions

⚠️ Keep this checklist updated:

- Mark completed substeps immediately with [x]
- Add notes about deviations or challenges
- Document decisions differing from plan
- Update status when starting/completing stages
- Maintain BC for existing custom themes
