# Problem Analysis: Keyboard Binding Architecture

## Executive Summary

Current keyboard handling implementation suffers from scattered magic strings, 
terminal conflicts, and lack of centralized management. This document provides
a comprehensive analysis of all keyboard-related code in the codebase.

---

## 1. Magic Strings Distribution

### 1.1 Files with `switch ($key)` Statements (11 files, 71+ case statements)

| File | Lines | Keys Used |
|------|-------|-----------|
| `CommandsScreen.php` | 254, 276 | F10, F1, CTRL_E, TAB, ESCAPE |
| `FileBrowserScreen.php` | 82 | F10, CTRL_C, CTRL_R, CTRL_G, TAB, ESCAPE |
| `FileViewerScreen.php` | 71 | ESCAPE, F10 |
| `FileContentViewer.php` | 100 | UP, DOWN, PAGE_UP, PAGE_DOWN, HOME, END |
| `ListComponent.php` | 148 | UP, DOWN, PAGE_UP, PAGE_DOWN, HOME, END, ENTER |
| `TableComponent.php` | 222 | UP, DOWN, PAGE_UP, PAGE_DOWN, HOME, END, ENTER |
| `TextDisplay.php` | 164 | UP, DOWN, PAGE_UP, PAGE_DOWN, HOME, END |
| `FormComponent.php` | 151 | UP, DOWN, TAB, F2, ENTER, ESCAPE |
| `TextField.php` | 111 | LEFT, RIGHT, HOME, END, BACKSPACE, DELETE |
| `MenuDropdown.php` | 113 | UP, DOWN, ENTER, SPACE, ESCAPE |
| `Modal.php` | 195 | LEFT, RIGHT, TAB, ENTER, SPACE, ESCAPE, 1-9 |

### 1.2 Files with Inline Key Comparisons

| File | Pattern | Keys |
|------|---------|------|
| `InstalledPackagesTab.php` | `$key === 'CTRL_R'` | CTRL_R |
| `OutdatedPackagesTab.php` | `$key === 'CTRL_R'` | CTRL_R |
| `ScriptsTab.php` | `$key === 'CTRL_C'`, `$key === 'CTRL_R'` | CTRL_C, CTRL_R |
| `SecurityAuditTab.php` | `$key === 'CTRL_R'` | CTRL_R |
| `TabContainer.php` | `$key === 'CTRL_LEFT'`, `$key === 'CTRL_RIGHT'` | CTRL_LEFT, CTRL_RIGHT |
| `MenuSystem.php` | `$key === 'F10'` | F10 |
| `CheckboxField.php` | `$key === ' '`, `$key === 'ENTER'` | SPACE, ENTER |

### 1.3 Hardcoded Configuration

| File | Constant/Variable | Content |
|------|-------------------|---------|
| `MenuBuilder.php:20-25` | `DEFAULT_FKEY_MAP` | help→F1, tools→F2, files→F3, system→F4 |
| `MenuBuilder.php:89` | Hardcoded | `'F10'` for Quit menu |
| `KeyboardHandler.php:17-85` | `KEY_MAPPINGS` | All terminal escape sequences |

---

## 2. Terminal Conflicts

### 2.1 Known Conflicts

| Key | Conflict | Terminal |
|-----|----------|----------|
| **F10** | Opens terminal menu | GNOME Terminal, Konsole, MATE Terminal |
| **F1** | Opens terminal help | Some terminals |
| **F11** | Toggle fullscreen | Most terminals |
| **Ctrl+Shift+C/V** | Copy/paste in terminal | All modern terminals |
| **Alt+F4** | Close window | Window managers |

### 2.2 Impact

- **F10 for Quit** is unusable in Ubuntu's default terminal
- Users in GNOME-based environments cannot quit the application with F10
- No alternative quit key is configured

---

## 3. Categories of Key Bindings

### 3.1 Global Actions (Application-level)

| Current Key | Action | Location |
|-------------|--------|----------|
| F10 | Quit | Application, MenuSystem, Screens |
| CTRL_C | Quit/Cancel | Application, Screens, ScriptsTab |
| F1 | Help | CommandsScreen |

### 3.2 Menu Navigation

| Current Key | Action | Location |
|-------------|--------|----------|
| F1 | Help menu | MenuBuilder |
| F2 | Tools menu | MenuBuilder |
| F3 | Files menu | MenuBuilder |
| F4 | System menu | MenuBuilder |
| F5 | Composer menu | MenuBuilder (implicit) |

### 3.3 List/Table Navigation

| Key | Action | Components |
|-----|--------|------------|
| UP | Move selection up | ListComponent, TableComponent, TextDisplay, FileContentViewer |
| DOWN | Move selection down | Same |
| PAGE_UP | Page up | Same |
| PAGE_DOWN | Page down | Same |
| HOME | Go to start | Same |
| END | Go to end | Same |
| ENTER | Select item | ListComponent, TableComponent |

### 3.4 Text Editing

| Key | Action | Component |
|-----|--------|-----------|
| LEFT | Move cursor left | TextField |
| RIGHT | Move cursor right | TextField |
| HOME | Move to line start | TextField |
| END | Move to line end | TextField |
| BACKSPACE | Delete before cursor | TextField |
| DELETE | Delete after cursor | TextField |

### 3.5 Panel/Focus Navigation

| Key | Action | Location |
|-----|--------|----------|
| TAB | Switch panel/next field | CommandsScreen, FileBrowserScreen, FormComponent, Modal |
| ESCAPE | Close/back | Multiple screens, Modal, MenuDropdown |

### 3.6 Tab Navigation

| Key | Action | Component |
|-----|--------|-----------|
| CTRL_LEFT | Previous tab | TabContainer |
| CTRL_RIGHT | Next tab | TabContainer |

### 3.7 Context-Specific Actions

| Key | Action | Location |
|-----|--------|----------|
| CTRL_E | Execute command | CommandsScreen |
| CTRL_R | Refresh/View file | FileBrowserScreen, Composer tabs |
| CTRL_G | Go to initial directory | FileBrowserScreen |
| SPACE | Toggle/select | Modal, MenuDropdown, CheckboxField |
| 1-9 | Quick button select | Modal |

### 3.8 Menu Dropdown

| Key | Action | Component |
|-----|--------|-----------|
| UP | Previous item | MenuDropdown |
| DOWN | Next item | MenuDropdown |
| ENTER | Select item | MenuDropdown |
| SPACE | Select item | MenuDropdown |
| ESCAPE | Close dropdown | MenuDropdown |
| a-z | Hotkey match | MenuDropdown |

---

## 4. Help Text Maintenance Issue

Current help text in `CommandsScreen.php:669-705` is hardcoded:

```php
$helpText = <<<'HELP'
    Command Browser - Keyboard Shortcuts
    
    Navigation:
      ↑/↓         Navigate through command list
      Tab         Switch between panels
    ...
      F10         Exit application
HELP;
```

**Problems:**
- Not synchronized with actual key bindings
- Must be manually updated when bindings change
- Different screens may have different help texts
- No way to generate help dynamically

---

## 5. Component Inheritance & Key Handling

### 5.1 handleInput() Chain

```
Application::handleInput()
    ↓
MenuSystem::handleInput()
    ↓ (if not handled)
ScreenManager::handleInput()
    ↓
Screen::handleInput() (CommandsScreen, FileBrowserScreen, etc.)
    ↓
Component::handleInput() (Panels, Lists, Tables, etc.)
    ↓
AbstractComponent::handleInput() (propagates to children)
```

### 5.2 Key Priority Issues

Currently, key handling priority is:
1. Menu system (F-keys when dropdown open)
2. Global shortcuts (hardcoded in Application)
3. Current screen
4. Fallback ESCAPE handling

**Problem:** Screens can intercept keys that should be global (e.g., `FileBrowserScreen` intercepts F10)

---

## 6. Total Key Binding Count

| Category | Count |
|----------|-------|
| Function keys (F1-F12) | 12 |
| Navigation keys | 8 (UP, DOWN, LEFT, RIGHT, HOME, END, PAGE_UP, PAGE_DOWN) |
| Ctrl combinations | 12 (CTRL_A through CTRL_Z, excluding reserved) |
| Ctrl+Arrow | 4 (CTRL_UP, CTRL_DOWN, CTRL_LEFT, CTRL_RIGHT) |
| Special keys | 7 (ENTER, ESCAPE, TAB, SPACE, BACKSPACE, DELETE, INSERT) |
| **Total unique bindings** | ~43 |

---

## 7. Recommendations Summary

1. **Change F10 to F12 for Quit** — Resolves GNOME Terminal conflict
2. **Add Ctrl+Q as quit alternative** — Universal cross-platform quit
3. **Centralize all bindings in registry** — Single source of truth
4. **Use Key enum instead of strings** — Type safety, IDE support
5. **Auto-generate help from registry** — Always synchronized
6. **Remove screen-level quit interception** — Let Application handle global keys
7. **Document binding contexts** — Which bindings apply where

---

## 8. Files Requiring Modification

### High Priority (Core refactoring)
1. `src/Infrastructure/Terminal/KeyboardHandler.php`
2. `src/Application.php`
3. `src/UI/Menu/MenuBuilder.php`
4. `src/UI/Component/Layout/MenuSystem.php`

### Medium Priority (Screen refactoring)
5. `src/Feature/CommandBrowser/Screen/CommandsScreen.php`
6. `src/Feature/FileBrowser/Screen/FileBrowserScreen.php`
7. `src/Feature/FileBrowser/Screen/FileViewerScreen.php`

### Lower Priority (Component refactoring)
8. `src/UI/Component/Display/ListComponent.php`
9. `src/UI/Component/Display/TableComponent.php`
10. `src/UI/Component/Display/TextDisplay.php`
11. `src/UI/Component/Input/TextField.php`
12. `src/UI/Component/Input/FormComponent.php`
13. `src/UI/Component/Input/CheckboxField.php`
14. `src/UI/Component/Layout/Modal.php`
15. `src/UI/Component/Layout/MenuDropdown.php`
16. `src/UI/Component/Container/TabContainer.php`
17. `src/Feature/FileBrowser/Component/FileContentViewer.php`
18. `src/Feature/ComposerManager/Tab/InstalledPackagesTab.php`
19. `src/Feature/ComposerManager/Tab/OutdatedPackagesTab.php`
20. `src/Feature/ComposerManager/Tab/ScriptsTab.php`
21. `src/Feature/ComposerManager/Tab/SecurityAuditTab.php`
