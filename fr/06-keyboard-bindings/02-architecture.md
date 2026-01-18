# Architecture: Keyboard Binding System

## Overview

Centralized keyboard binding system replacing scattered magic strings with 
type-safe, registry-based key management.

---

## 1. Namespace Structure

```
src/Infrastructure/Keyboard/
├── Key.php                          # Enum: all key constants (UP, DOWN, F1-F12, A-Z, etc.)
├── Modifier.php                     # Enum: CTRL, ALT, SHIFT
├── KeyCombination.php               # Value object: key + modifiers, implements Stringable
├── KeyBinding.php                   # DTO: combination + actionId + description + context
├── KeyBindingRegistryInterface.php  # Contract for registry
├── KeyBindingRegistry.php           # Central registry implementation
└── DefaultBindings.php              # Default application bindings (F12 for quit!)
```

---

## 2. Component Responsibilities

### Key (enum)
- Contains all recognized keyboard keys
- Values match KeyboardHandler::getKey() output format
- Helper methods: `isNavigation()`, `isFunctionKey()`, `isLetter()`, `isDigit()`

### Modifier (enum)
- Three modifiers: CTRL, ALT, SHIFT
- Method `toRawPrefix()` returns "CTRL_", "ALT_", etc.

### KeyCombination (value object)
- Immutable, readonly
- Combines Key + modifiers (ctrl, alt, shift booleans)
- Factory methods: `key()`, `ctrl()`, `alt()`, `shift()`, `ctrlShift()`, `ctrlAlt()`
- `fromString()` parses "Ctrl+C", "F12", "Ctrl+Shift+A"
- `matches(string $rawKey)` compares with KeyboardHandler output
- `toRawKey()` returns KeyboardHandler-compatible string
- `__toString()` returns human-readable format for UI

### KeyBinding (DTO)
- Links KeyCombination to an action
- Properties: combination, actionId, description, context, priority
- `matches()` delegates to combination
- `toHelpLine()` formats for help screen display

### KeyBindingRegistry
- Stores all bindings
- `register(KeyBinding)` adds binding
- `match(string $rawKey)` finds first matching binding
- `getByActionId(string)` lookup by action
- `getAllByActionId(string)` all bindings for action (e.g., F12 + Ctrl+Q both quit)
- `getByContext(string)` returns bindings sorted by priority
- `all()` returns everything

### DefaultBindings
- Static `register()` method populates registry with defaults
- Uses **F12 for quit** (not F10!)
- Registers Ctrl+Q and Ctrl+C as quit alternatives
- Registers F1-F5 for menu navigation
- Registers context actions (Ctrl+R, Ctrl+E, Ctrl+G)
- Registers tab navigation (Ctrl+Left/Right)

---

## 3. Action ID Naming Convention

```
app.*      → Application-level (quit, minimize)
menu.*     → Menu activation (help, tools, files, system, composer)
nav.*      → Navigation (back, next_panel)
action.*   → Context actions (refresh, execute, goto_home)
tabs.*     → Tab navigation (previous, next)
edit.*     → Editing (copy, paste, cut, undo) — future
```

---

## 4. Integration Points

### Application
- Accepts optional `KeyBindingRegistryInterface` in constructor
- Creates default registry if not provided
- `onAction(string $actionId, callable)` registers handlers
- `handleInput()` uses `registry->match()` for global shortcuts

### MenuBuilder
- Accepts `KeyBindingRegistryInterface` in constructor
- Gets F-keys from registry via `getByActionId('menu.*')`
- No more hardcoded `DEFAULT_FKEY_MAP`

### MenuDefinition
- `fkey` property changes from `?string` to `?KeyCombination`
- Display uses `(string) $fkey` for human-readable format

### MenuSystem
- Builds fkeyMap from `KeyCombination::toRawKey()`
- Input matching uses raw key comparison

### Screens & Components
- Replace `case 'UP':` with `Key::UP` enum
- Use `Key::tryFrom($rawKey)` to convert input
- Let global keys (quit) bubble up to Application

---

## 5. Key Binding Flow

```
User presses key
       ↓
KeyboardHandler::getKey() → returns raw string "CTRL_Q"
       ↓
Application::handleInput()
       ↓
MenuSystem::handleInput() → checks if dropdown open
       ↓ (not handled)
KeyBindingRegistry::match("CTRL_Q") → returns KeyBinding{actionId: "app.quit"}
       ↓
Application::executeAction("app.quit") → calls registered handler
       ↓
Application::stop()
```

---

## 6. Default Key Bindings

### Global (handled by Application)

| Combination | Action ID | Description |
|-------------|-----------|-------------|
| F12 | app.quit | Quit application |
| Ctrl+Q | app.quit | Quit application |
| Ctrl+C | app.quit | Quit application |

### Menu (handled by MenuSystem)

| Combination | Action ID | Description |
|-------------|-----------|-------------|
| F1 | menu.help | Help |
| F2 | menu.tools | Tools |
| F3 | menu.files | Files |
| F4 | menu.system | System |
| F5 | menu.composer | Composer |

### Navigation (informational, used by components)

| Combination | Action ID | Description |
|-------------|-----------|-------------|
| Escape | nav.back | Go back / Close |
| Tab | nav.next_panel | Switch panel |

### Context (used by specific screens/tabs)

| Combination | Action ID | Description |
|-------------|-----------|-------------|
| Ctrl+R | action.refresh | Refresh |
| Ctrl+E | action.execute | Execute |
| Ctrl+G | action.goto_home | Go to home directory |

### Tabs (handled by TabContainer)

| Combination | Action ID | Description |
|-------------|-----------|-------------|
| Ctrl+Left | tabs.previous | Previous tab |
| Ctrl+Right | tabs.next | Next tab |

---

## 7. Backward Compatibility

- `KeyboardHandler::getKey()` unchanged — still returns strings
- New methods added: `parseToKey()`, `parseToCombination()`, `getKeyCombination()`
- `Application::registerGlobalShortcut()` deprecated but functional via legacy bindings
- Gradual migration: screens can mix old string comparisons with new Key enum
