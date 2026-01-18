# Keyboard Architecture Guide

This guide explains the keyboard handling architecture in Commander, from raw terminal input to application-level actions.

## Complete Keyboard Stack

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Terminal                                     │
│                   Raw byte sequences                                 │
│              "\033[A", "\003", "a", "\n"                            │
└───────────────────────────┬─────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────────┐
│                   KeyMappingRegistry                                 │
│              (KeySequence DTOs)                                      │
│                                                                      │
│     "\033[A" → KeySequence(UP, [], Escape)                          │
│     "\003"   → KeySequence(C, [CTRL], Control)                      │
│                                                                      │
│     See: key-mapping-system.md                                      │
└───────────────────────────┬─────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     KeyboardHandler                                  │
│              Converts sequences to strings                           │
│                                                                      │
│     getKey() → "UP", "CTRL_C", "a", "ENTER"                        │
└───────────────────────────┬─────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       KeyInput                                       │
│              Type-safe wrapper for components                        │
│                                                                      │
│     $input->is(Key::UP)                                             │
│     $input->isCtrl(Key::C)                                          │
│                                                                      │
│     See: keyboard-input-handling.md                                 │
└───────────────────────────┬─────────────────────────────────────────┘
                            │
        ┌───────────────────┴───────────────────────┐
        ▼                                           ▼
┌───────────────────────┐               ┌───────────────────────┐
│    KeyBindingRegistry │               │    UI Components      │
│   (Application-level) │               │                       │
│                       │               │  handleInput($key)    │
│   F12 → app.quit     │               │    ↓                   │
│   Ctrl+Q → app.quit  │               │  KeyInput::from($key) │
│   F2 → menu.tools    │               │    ↓                   │
│                       │               │  match expression     │
│   See: This guide     │               │                       │
└───────────────────────┘               └───────────────────────┘
```

## Two Levels of Key Handling

### Level 1: Key Mapping (Low-Level)

Converts raw terminal bytes to logical key names.

| Component | Purpose |
|-----------|---------|
| `KeySequence` | DTO mapping byte sequence → Key enum |
| `KeyMappingRegistry` | Collection with O(1) lookup |
| `KeyboardHandler` | Reads stdin, uses registry |

**Example:** `"\033[A"` → `"UP"`

See [Key Mapping System Guide](key-mapping-system.md) for details.

### Level 2: Key Bindings (Application-Level)

Maps logical keys to application actions.

| Component | Purpose |
|-----------|---------|
| `KeyCombination` | Value object for key + modifiers |
| `KeyBinding` | Links combination to action ID |
| `KeyBindingRegistry` | Stores bindings, lookup by key |
| `DefaultKeyBindings` | Standard application shortcuts |

**Example:** `Key::F12` → `"app.quit"` action

This guide covers Level 2.

## System Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                        Application Bootstrap                         │
│                                                                      │
│   $keyBindings = DefaultKeyBindings::createRegistry();              │
│   $menuBuilder = new MenuBuilder($registry, $keyBindings);          │
│   $app = new Application($keyBindings);                             │
└─────────────────────────────────┬───────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      KeyBindingRegistry                              │
│                                                                      │
│   Stores all registered key bindings with:                          │
│   - KeyCombination (e.g., Ctrl+R, F12, Escape)                      │
│   - Action ID (e.g., "app.quit", "menu.tools")                      │
│   - Description, category, priority                                  │
└─────────────────────────────────┬───────────────────────────────────┘
                                  │
          ┌───────────────────────┼───────────────────────┐
          ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Application   │    │   MenuBuilder   │    │     Screens     │
│                 │    │                 │    │                 │
│ Global shortcuts│    │ F-key assignment│    │ Context-aware   │
│ (quit, etc.)    │    │ for menus       │    │ shortcuts       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## Core Components

### Key Enum

The `Key` enum represents all supported keyboard keys:

```php
use Butschster\Commander\Infrastructure\Keyboard\Key;

// Navigation
Key::UP, Key::DOWN, Key::LEFT, Key::RIGHT
Key::HOME, Key::END, Key::PAGE_UP, Key::PAGE_DOWN

// Function keys
Key::F1, Key::F2, ... Key::F12

// Special keys
Key::ENTER, Key::ESCAPE, Key::TAB, Key::SPACE
Key::BACKSPACE, Key::DELETE, Key::INSERT

// Letters (for Ctrl+Letter combinations)
Key::A, Key::B, ... Key::Z

// Digits (for quick-select)
Key::D0, Key::D1, ... Key::D9
```

### KeyCombination

Represents a key with optional modifiers:

```php
use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;

// Simple keys
KeyCombination::key(Key::F12);        // F12
KeyCombination::key(Key::ESCAPE);     // Escape

// With modifiers
KeyCombination::ctrl(Key::R);         // Ctrl+R
KeyCombination::ctrl(Key::C);         // Ctrl+C
KeyCombination::alt(Key::F);          // Alt+F
KeyCombination::shift(Key::TAB);      // Shift+Tab
KeyCombination::ctrlShift(Key::S);    // Ctrl+Shift+S

// Display formatting
$combo = KeyCombination::ctrl(Key::R);
echo $combo;                          // "Ctrl+R"
echo $combo->toRawKey();              // "CTRL_R"
```

### KeyBinding

Links a key combination to an action:

```php
use Butschster\Commander\Infrastructure\Keyboard\KeyBinding;

$binding = new KeyBinding(
    combination: KeyCombination::key(Key::F12),
    actionId: 'app.quit',
    description: 'Quit application',
    category: 'global',
    priority: 100,
);
```

### KeyBindingRegistry

Central storage for all bindings:

```php
use Butschster\Commander\Infrastructure\Keyboard\KeyBindingRegistry;

$registry = new KeyBindingRegistry();

// Register a binding
$registry->register(new KeyBinding(
    combination: KeyCombination::ctrl(Key::R),
    actionId: 'action.refresh',
    description: 'Refresh data',
    category: 'actions',
));

// Find binding by key
$binding = $registry->match('CTRL_R');

// Get primary binding for an action
$binding = $registry->getPrimaryByActionId('app.quit');

// Get all bindings for an action (e.g., F12 and Ctrl+Q both quit)
$bindings = $registry->getByActionId('app.quit');

// Get bindings by category
$globalBindings = $registry->getByCategory('global');
```

## Default Key Bindings

The `DefaultKeyBindings` class provides standard application shortcuts:

```php
use Butschster\Commander\Infrastructure\Keyboard\DefaultKeyBindings;

// Create a pre-populated registry
$registry = DefaultKeyBindings::createRegistry();

// Or register into existing registry
DefaultKeyBindings::register($existingRegistry);
```

### Standard Bindings

| Key | Action ID | Description |
|-----|-----------|-------------|
| F12 | `app.quit` | Quit application (primary) |
| Ctrl+Q | `app.quit` | Quit application (alternative) |
| Ctrl+C | `app.quit` | Quit application (interrupt) |
| F1 | `menu.help` | Help menu |
| F2 | `menu.tools` | Tools menu |
| F3 | `menu.files` | Files menu |
| F4 | `menu.system` | System menu |
| F5 | `menu.composer` | Composer menu |
| Escape | `nav.back` | Go back / Close |
| Tab | `nav.next_panel` | Switch panel |

### Why F12 Instead of F10?

F10 is reserved by GNOME Terminal for its menu. Using F12 for quit avoids this conflict.

## Action ID Conventions

Action IDs follow a namespaced pattern:

| Prefix | Purpose | Examples |
|--------|---------|----------|
| `app.*` | Application-level actions | `app.quit`, `app.settings` |
| `menu.*` | Menu navigation | `menu.help`, `menu.tools`, `menu.files` |
| `nav.*` | Navigation actions | `nav.back`, `nav.next_panel` |
| `action.*` | Generic actions | `action.refresh`, `action.save` |

## Integration with MenuBuilder

The `MenuBuilder` uses the registry to assign F-keys to menus:

```php
use Butschster\Commander\UI\Menu\MenuBuilder;

$keyBindings = DefaultKeyBindings::createRegistry();
$menuBuilder = new MenuBuilder($screenRegistry, $keyBindings);

// Menus automatically get F-keys based on their category:
// - "tools" category → menu.tools → F2
// - "files" category → menu.files → F3
// - "system" category → menu.system → F4

$menus = $menuBuilder->build();
```

### Category to Action ID Mapping

Screen categories map to menu action IDs:

```php
#[Metadata(
    name: 'command_browser',
    category: 'tools',    // → menu.tools → F2
)]
final class CommandsScreen implements ScreenInterface {}

#[Metadata(
    name: 'file_browser',
    category: 'files',    // → menu.files → F3
)]
final class FileBrowserScreen implements ScreenInterface {}
```

## Integration with Application

The `Application` class uses the registry for global shortcuts:

```php
// In Application::handleInput()
$binding = $this->keyBindings->match($key);
if ($binding !== null) {
    return $this->executeAction($binding->actionId);
}
```

### Registering Action Handlers

```php
$app = new Application($keyBindings);

// Register handler for an action
$app->onAction('app.quit', fn() => $app->stop());
$app->onAction('action.refresh', fn() => $this->refresh());
```

## Custom Key Bindings

### Adding New Bindings

```php
$registry = DefaultKeyBindings::createRegistry();

// Add custom binding
$registry->register(new KeyBinding(
    combination: KeyCombination::ctrl(Key::N),
    actionId: 'action.new',
    description: 'Create new item',
    category: 'actions',
));
```

### Overriding Default Bindings

Bindings are matched by priority (lower = higher priority):

```php
// Override F12 with a different action
$registry->register(new KeyBinding(
    combination: KeyCombination::key(Key::F12),
    actionId: 'custom.action',
    description: 'Custom action',
    category: 'custom',
    priority: 50,  // Lower than default (100)
));
```

## Best Practices

1. **Use DefaultKeyBindings** as a starting point for consistent UX
2. **Follow action ID conventions** for clarity and maintainability
3. **Assign priorities carefully** - lower numbers take precedence
4. **Document custom bindings** in your application's help screen
5. **Avoid F10** due to terminal conflicts
6. **Provide alternatives** for important actions (e.g., F12 and Ctrl+Q for quit)

## Architecture Diagram

```
┌──────────────────────────────────────────────────────────────────┐
│                         console (bootstrap)                       │
│                                                                   │
│  $keyBindings = DefaultKeyBindings::createRegistry();            │
│  $app = new Application($keyBindings);                           │
│  $menuBuilder = new MenuBuilder($registry, $keyBindings);        │
└──────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌──────────────────────────────────────────────────────────────────┐
│                     KeyBindingRegistry                            │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │ Bindings:                                                   │  │
│  │   F12      → app.quit       (priority: 100)                │  │
│  │   Ctrl+Q   → app.quit       (priority: 101)                │  │
│  │   Ctrl+C   → app.quit       (priority: 102)                │  │
│  │   F1       → menu.help      (priority: 1)                  │  │
│  │   F2       → menu.tools     (priority: 2)                  │  │
│  │   ...                                                       │  │
│  └────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────┘
                                │
        ┌───────────────────────┴───────────────────────┐
        ▼                                               ▼
┌───────────────────┐                       ┌───────────────────┐
│    Application    │                       │    MenuBuilder    │
│                   │                       │                   │
│ handleInput($key) │                       │ build()           │
│   ↓               │                       │   ↓               │
│ registry->match() │                       │ For each category:│
│   ↓               │                       │   Get F-key from  │
│ executeAction()   │                       │   registry        │
└───────────────────┘                       └───────────────────┘
```

## See Also

- [Keyboard Input Handling](keyboard-input-handling.md) - Using `KeyInput` in components
- [Component Input Patterns](component-input-patterns.md) - Patterns for screens and tabs
