# Feature: Keyboard Binding Architecture Refactoring

## Overview

Refactor the keyboard handling system from scattered magic strings to a centralized, 
type-safe binding registry. This enables easy key reassignment, better IDE support, 
and resolves terminal conflicts (like F10 in GNOME Terminal).

## Key Changes Summary

- **F10 → F12** for Quit (resolves GNOME Terminal conflict)
- **Ctrl+Q** added as universal quit alternative
- Central `KeyBindingRegistry` replaces hardcoded shortcuts
- `Key` enum replaces magic strings in 21 files
- Help screen auto-generates from registry

## Stage Dependencies

```
Stage 1 (Core Types)
    ↓
Stage 2 (Binding System)
    ↓
Stage 3 (KeyboardHandler Integration)
    ↓
Stage 4 (MenuBuilder Refactoring)
    ↓
Stage 5 (Application Refactoring)
    ↓
Stage 6 (Screen & Component Refactoring)
    ↓
Stage 7 (Documentation & Cleanup)
```

---

## Development Progress

### Stage 1: Core Types
**Files to create:** `Key.php`, `Modifier.php`, `KeyCombination.php`

- [x] 1.1: Create `Key` enum with all key constants (navigation, F1-F12, A-Z, digits, special)
- [x] 1.2: Create `Modifier` enum (CTRL, ALT, SHIFT)
- [x] 1.3: Create `KeyCombination` value object with factory methods
- [x] 1.4: Implement `Stringable` for display formatting ("Ctrl+C", "F12")
- [x] 1.5: Implement `matches(string $rawKey)` for input comparison
- [x] 1.6: Implement `toRawKey()` for KeyboardHandler-compatible output
- [x] 1.7: Implement `fromString()` parser ("Ctrl+C" → KeyCombination)

**Status**: ✅ Complete

---

### Stage 2: Binding System
**Files to create:** `KeyBinding.php`, `KeyBindingRegistryInterface.php`, `KeyBindingRegistry.php`, `DefaultBindings.php`

- [x] 2.1: Create `KeyBinding` DTO (combination, actionId, description, context, priority)
- [x] 2.2: Create `KeyBindingRegistryInterface` contract
- [x] 2.3: Implement `KeyBindingRegistry` with register/match/getByActionId/getByContext
- [x] 2.4: Create `DefaultBindings` with F12 for quit, F1-F5 for menus, Ctrl+Q/C alternatives
- [x] 2.5: Define action ID naming convention (app.*, menu.*, nav.*, action.*, tabs.*)

**Status**: ✅ Complete

---

### Stage 3: KeyboardHandler Integration
**Files to modify:** `src/Infrastructure/Terminal/KeyboardHandler.php`

- [x] 3.1: Add `parseToKey(string $rawKey): ?Key` method
- [x] 3.2: Add `parseToCombination(string $rawKey): ?KeyCombination` method
- [x] 3.3: Add `getKeyCombination(): ?KeyCombination` convenience method
- [x] 3.4: Keep existing `getKey()` unchanged for backward compatibility

**Status**: ✅ Complete

---

### Stage 4: MenuBuilder Refactoring
**Files to modify:** `MenuBuilder.php`, `MenuDefinition.php`, `MenuSystem.php`

- [x] 4.1: Inject `KeyBindingRegistryInterface` into `MenuBuilder` constructor
- [x] 4.2: Remove `DEFAULT_FKEY_MAP` constant from `MenuBuilder`
- [x] 4.3: Update `MenuBuilder::build()` to get F-keys from registry
- [x] 4.4: Change `MenuDefinition::$fkey` from `?string` to `?KeyCombination`
- [x] 4.5: Update `MenuSystem` to use `KeyCombination::toRawKey()` for matching
- [x] 4.6: Remove hardcoded 'F10' from `MenuSystem::handleInput()`
- [x] 4.7: Verify menu bar displays F12 for Quit

**Status**: ✅ Complete

---

### Stage 5: Application Refactoring
**Files to modify:** `src/Application.php`

- [x] 5.1: Add `KeyBindingRegistryInterface` to constructor (optional, with default)
- [x] 5.2: Add `onAction(string $actionId, callable)` method for handler registration
- [x] 5.3: Add private `executeAction(string $actionId)` method
- [x] 5.4: Update `handleInput()` to use `registry->match()` for global shortcuts
- [x] 5.5: Register core action handlers in constructor (`app.quit` → `$this->stop()`)
- [x] 5.6: Deprecate `registerGlobalShortcut()` (keep working via legacy bindings)
- [x] 5.7: Remove `$globalShortcuts` array
- [x] 5.8: Remove `registerMenuShortcut()` private method
- [x] 5.9: Verify F12, Ctrl+Q, Ctrl+C all quit the application

**Status**: ✅ Complete

---

### Stage 6: Screen & Component Refactoring
**21 files to modify** — replace magic strings with `Key` enum

#### 6.1 Screens (high priority)
- [x] `CommandsScreen.php` — F1, CTRL_E, TAB, ESCAPE → KeyInput
- [x] `FileBrowserScreen.php` — CTRL_R, CTRL_G, TAB, ESCAPE → KeyInput
- [x] `FileViewerScreen.php` — ESCAPE → KeyInput

#### 6.2 Display Components
- [x] `ListComponent.php` — UP, DOWN, PAGE_UP, PAGE_DOWN, HOME, END, ENTER (already using KeyInput)
- [x] `TableComponent.php` — UP, DOWN, PAGE_UP, PAGE_DOWN, HOME, END, ENTER (already using KeyInput)
- [x] `TextDisplay.php` — UP, DOWN, PAGE_UP, PAGE_DOWN, HOME, END (already using KeyInput)

#### 6.3 Input Components
- [x] `TextField.php` — LEFT, RIGHT, HOME, END, BACKSPACE, DELETE → KeyInput
- [x] `FormComponent.php` — UP, DOWN, TAB, F2, ENTER, ESCAPE (already using KeyInput)
- [x] `CheckboxField.php` — SPACE, ENTER (already using KeyInput)

#### 6.4 Layout Components
- [x] `Modal.php` — LEFT, RIGHT, TAB, ENTER, SPACE, ESCAPE, 1-9 → KeyInput
- [x] `MenuDropdown.php` — UP, DOWN, ENTER, SPACE, ESCAPE, hotkeys → KeyInput

#### 6.5 Container Components
- [x] `TabContainer.php` — CTRL_LEFT, CTRL_RIGHT (already using KeyInput)

#### 6.6 Feature Components
- [x] `FileContentViewer.php` — UP, DOWN, PAGE_UP, PAGE_DOWN, HOME, END → KeyInput

#### 6.7 Composer Tabs
- [x] `InstalledPackagesTab.php` — CTRL_R, TAB → KeyInput
- [x] `OutdatedPackagesTab.php` — CTRL_R, TAB → KeyInput
- [x] `ScriptsTab.php` — CTRL_C, CTRL_R, TAB, ESCAPE → KeyInput
- [x] `SecurityAuditTab.php` — CTRL_R, TAB → KeyInput

#### 6.8 Final Verification
- [x] Search for remaining `Key::tryFrom` in UI layer (should be zero - only in infrastructure)
- [x] Search for remaining `'CTRL_` raw string comparisons in UI layer (should be zero)
- [x] All components migrated to use `KeyInput::from($key)` pattern

**Status**: ✅ Complete

---

### Stage 7: Documentation & Cleanup
**Files to modify:** `CommandsScreen.php` (help), cleanup across codebase

- [ ] 7.1: Update `showHelpModal()` to generate help from registry
- [ ] 7.2: Inject `KeyBindingRegistryInterface` into screens that show help
- [ ] 7.3: Remove any dead code from refactoring
- [ ] 7.4: Add PHPDoc to all new Keyboard classes
- [ ] 7.5: Final grep verification — no magic key strings remain
- [ ] 7.6: Test in GNOME Terminal — verify no F10 conflict
- [ ] 7.7: Test all key bindings manually

**Status**: Not Started

---

## Files Summary

### New Files (8)
```
src/Infrastructure/Keyboard/
├── Key.php
├── Modifier.php
├── KeyCombination.php
├── KeyBinding.php
├── KeyBindingRegistryInterface.php
├── KeyBindingRegistry.php
└── DefaultBindings.php
```

### Modified Files (21)
```
Core:
├── src/Infrastructure/Terminal/KeyboardHandler.php
├── src/Application.php
├── src/UI/Menu/MenuBuilder.php
├── src/UI/Menu/MenuDefinition.php
└── src/UI/Component/Layout/MenuSystem.php

Screens:
├── src/Feature/CommandBrowser/Screen/CommandsScreen.php
├── src/Feature/FileBrowser/Screen/FileBrowserScreen.php
└── src/Feature/FileBrowser/Screen/FileViewerScreen.php

Components:
├── src/UI/Component/Display/ListComponent.php
├── src/UI/Component/Display/TableComponent.php
├── src/UI/Component/Display/TextDisplay.php
├── src/UI/Component/Input/TextField.php
├── src/UI/Component/Input/FormComponent.php
├── src/UI/Component/Input/CheckboxField.php
├── src/UI/Component/Layout/Modal.php
├── src/UI/Component/Layout/MenuDropdown.php
├── src/UI/Component/Container/TabContainer.php
└── src/Feature/FileBrowser/Component/FileContentViewer.php

Composer Tabs:
├── src/Feature/ComposerManager/Tab/InstalledPackagesTab.php
├── src/Feature/ComposerManager/Tab/OutdatedPackagesTab.php
├── src/Feature/ComposerManager/Tab/ScriptsTab.php
└── src/Feature/ComposerManager/Tab/SecurityAuditTab.php
```

---

## Codebase References

### Current Key Constants
- `src/Infrastructure/Terminal/KeyboardHandler.php:17-95` — KEY_MAPPINGS array

### Current Global Shortcuts
- `src/Application.php:36` — `$globalShortcuts` array
- `src/Application.php:119-122` — `registerGlobalShortcut()` method
- `src/Application.php:208-240` — input handling with shortcuts

### Current Menu Configuration
- `src/UI/Menu/MenuBuilder.php:20-25` — `DEFAULT_FKEY_MAP`
- `src/UI/Menu/MenuBuilder.php:85-91` — Hardcoded F10 for Quit
- `src/UI/Component/Layout/MenuSystem.php:100-103` — F10 special handling

### Current Help Text
- `src/Feature/CommandBrowser/Screen/CommandsScreen.php:669-705` — Hardcoded help

---

## Post-Implementation Verification

After all stages complete:
- [ ] F12 quits application
- [ ] Ctrl+Q quits application  
- [ ] Ctrl+C quits application
- [ ] F1-F5 open corresponding menus
- [ ] Menu bar shows "F12 Quit" (not F10)
- [ ] Help screen displays current bindings from registry
- [ ] No 'F10' strings in codebase (except comments)
- [ ] Application works in GNOME Terminal without menu conflict
- [ ] All navigation keys work in lists/tables
- [ ] All text editing keys work in TextField
- [ ] Tab switching works in TabContainer (Ctrl+Left/Right)
