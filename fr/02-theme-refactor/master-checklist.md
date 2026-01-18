# Feature: Theme System Refactoring (Remove Static State)

## Overview

Refactor the theme system to use dependency injection instead of static state. Currently, `ThemeManager` uses static
properties and `ColorScheme` has static mutable state, making testing difficult and preventing multiple theme contexts.

## Stage Dependencies

```
Stage 1 (ThemeContext) → Stage 2 (Update Components) → Stage 3 (Update Screens)
                                                     → Stage 4 (Update Application)
                                                     → Stage 5 (Cleanup & BC)
```

## Development Progress

### Stage 1: Create ThemeContext and ColorSet Value Objects

- [x] Substep 1.1: Create `ColorSet` value object for bg/fg pairs
- [x] Substep 1.2: Create `BorderColorSet` for active/inactive borders
- [x] Substep 1.3: Create `SemanticColorSet` for error/warning/highlight
- [x] Substep 1.4: Create `ThemeContext` class with all color getters
- [ ] Substep 1.5: Update `ThemeInterface` to return value objects (optional grouping)

**Notes**: Value objects and ThemeContext created. Substep 1.5 skipped - not needed for initial implementation, ThemeInterface already provides all required methods.
**Status**: Complete
**Completed**: ColorSet.php, BorderColorSet.php, SemanticColorSet.php, ThemeContext.php

---

### Stage 2: Update Core Components to Accept ThemeContext

- [x] Substep 2.1: Add `ThemeContext` parameter to `Renderer` constructor
- [x] Substep 2.2: Update `AbstractComponent` to accept/propagate ThemeContext
- [x] Substep 2.3: Update `TableComponent` to use injected ThemeContext
- [x] Substep 2.4: Update `ListComponent` to use injected ThemeContext
- [x] Substep 2.5: Update `TextDisplay` to use injected ThemeContext
- [x] Substep 2.6: Update `Panel` to use injected ThemeContext

**Notes**: ThemeContext is accessed via `$renderer->getThemeContext()` in render methods. AbstractComponent doesn't need changes - components access theme through the Renderer passed to render(). ColorScheme constants (FG_*, BG_*, BOLD, etc.) are still used where needed.
**Status**: Complete
**Completed**: Renderer.php, TableComponent.php, ListComponent.php, TextDisplay.php, Panel.php

---

### Stage 3: Update Layout and Container Components

- [ ] Substep 3.1: Update `GridLayout` to propagate ThemeContext
- [ ] Substep 3.2: Update `StackLayout` to propagate ThemeContext
- [ ] Substep 3.3: Update `TabContainer` to propagate ThemeContext
- [ ] Substep 3.4: Update `MenuSystem` to use ThemeContext
- [ ] Substep 3.5: Update `StatusBar` to use ThemeContext

**Notes**:
**Status**: Not Started
**Completed**:

---

### Stage 4: Update Application and Screens

- [ ] Substep 4.1: Update `Application` to create and hold ThemeContext
- [ ] Substep 4.2: Update `ScreenInterface` to receive ThemeContext in render()
- [ ] Substep 4.3: Update `FileBrowserScreen` to use ThemeContext
- [ ] Substep 4.4: Update `ComposerManagerScreen` components
- [ ] Substep 4.5: Update `CommandBrowser` components

**Notes**:
**Status**: Not Started
**Completed**:

---

### Stage 5: Cleanup Static State and Add BC Layer

- [ ] Substep 5.1: Deprecate static properties in ColorScheme
- [ ] Substep 5.2: Add BC layer that delegates to default ThemeContext
- [ ] Substep 5.3: Update ThemeManager to be non-static (optional)
- [ ] Substep 5.4: Add documentation for new theme injection pattern
- [ ] Substep 5.5: Remove static `applyTheme()` calls from codebase

**Notes**:
**Status**: Not Started
**Completed**:

---

## Codebase References

- `src/UI/Theme/ThemeManager.php` - Current static implementation
- `src/UI/Theme/ColorScheme.php` - Static color properties to remove
- `src/UI/Theme/ThemeInterface.php` - 25+ methods to potentially simplify
- `src/UI/Theme/AbstractTheme.php` - Base theme with combined methods
- `src/UI/Component/Display/TableComponent.php` - Uses ColorScheme::$SELECTED_TEXT etc.
- `src/Infrastructure/Terminal/Renderer.php` - Uses ColorScheme::$NORMAL_TEXT

## Architecture

### Before (Static)

```php
// Static state - hard to test
ColorScheme::applyTheme($theme);
$color = ColorScheme::$SELECTED_TEXT;  // Global mutable state
```

### After (Dependency Injection)

```php
// Injected context - testable
$context = new ThemeContext($theme);
$color = $context->getSelectedText();  // Instance state

// Or with value objects
$colors = $context->getSelectionColors();
$color = $colors->combined();
```

## Usage Instructions

⚠️ Keep this checklist updated:

- Mark completed substeps immediately with [x]
- Add notes about deviations or challenges
- Document decisions differing from plan
- Update status when starting/completing stages
- This is a breaking change - document migration path
