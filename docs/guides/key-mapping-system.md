# Key Mapping System Guide

This guide explains the DTO-based key mapping system that converts raw terminal byte sequences into logical key
representations.

## Overview

When you press a key in a terminal, it sends a sequence of bytes. The key mapping system translates these sequences:

```
Terminal Input          →    KeyMappingRegistry    →    Logical Key
───────────────────────────────────────────────────────────────────
"\033[A" (ESC [ A)      →    KeySequence DTO       →    "UP"
"\033[1;5C"             →    KeySequence DTO       →    "CTRL_RIGHT"
"\003" (ASCII 3)        →    KeySequence DTO       →    "CTRL_C"
"\n" (LF)               →    KeySequence DTO       →    "ENTER"
"\033OP" (xterm F1)     →    KeySequence DTO       →    "F1"
"\033[11~" (linux F1)   →    KeySequence DTO       →    "F1"
```

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      KeyboardHandler                             │
│                                                                  │
│   getKey() → reads raw bytes → looks up in registry             │
└────────────────────────────────┬────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────┐
│                    KeyMappingRegistry                            │
│                                                                  │
│   ┌─────────────────────────────────────────────────────────┐   │
│   │  bySequence: array<string, KeySequence>                 │   │
│   │                                                          │   │
│   │  "\033[A"    → KeySequence(UP, [], Escape, Common)      │   │
│   │  "\033[1;5A" → KeySequence(UP, [CTRL], Escape, Common)  │   │
│   │  "\003"      → KeySequence(C, [CTRL], Control, Common)  │   │
│   │  "\n"        → KeySequence(ENTER, [], Special, Common)  │   │
│   │  "\033OP"    → KeySequence(F1, [], Escape, Xterm)       │   │
│   │  "\033[11~"  → KeySequence(F1, [], Escape, Linux)       │   │
│   └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

## Core Components

### KeySequence DTO

The `KeySequence` class is an immutable DTO that maps a byte sequence to a key:

```php
use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\Modifier;
use Butschster\Commander\Infrastructure\Keyboard\Mapping\KeySequence;
use Butschster\Commander\Infrastructure\Keyboard\Mapping\SequenceType;
use Butschster\Commander\Infrastructure\Keyboard\Mapping\TerminalType;

// Full constructor
$mapping = new KeySequence(
    sequence: "\033[A",           // Raw bytes
    key: Key::UP,                 // Logical key
    modifiers: [],                // [Modifier::CTRL, Modifier::ALT, Modifier::SHIFT]
    type: SequenceType::Escape,   // Categorization
    terminal: TerminalType::Common, // Terminal compatibility
    description: 'Arrow up',      // Human-readable description
);

// Using factory methods (recommended)
$up = KeySequence::escape("\033[A", Key::UP);
$ctrlC = KeySequence::ctrl("\003", Key::C, 'Interrupt signal');
$enter = KeySequence::special("\n", Key::ENTER, 'Line feed');

// With modifiers
$ctrlUp = KeySequence::escape("\033[1;5A", Key::UP, [Modifier::CTRL]);

// Terminal-specific
$f1Xterm = KeySequence::escape("\033OP", Key::F1, terminal: TerminalType::Xterm);
$f1Linux = KeySequence::escape("\033[11~", Key::F1, terminal: TerminalType::Linux);
```

### KeySequence Properties

| Property      | Type              | Description                         |
|---------------|-------------------|-------------------------------------|
| `sequence`    | `string`          | Raw byte sequence from terminal     |
| `key`         | `Key`             | The logical key enum value          |
| `modifiers`   | `array<Modifier>` | Active modifiers (Ctrl, Alt, Shift) |
| `type`        | `SequenceType`    | Category of the sequence            |
| `terminal`    | `TerminalType`    | Terminal compatibility              |
| `description` | `?string`         | Human-readable description          |

### KeySequence Methods

```php
// Check modifiers
$mapping->hasCtrl();    // bool
$mapping->hasAlt();     // bool
$mapping->hasShift();   // bool
$mapping->hasModifier(Modifier::CTRL); // bool

// Get output key name (used by rest of application)
$mapping->toKeyName();  // "UP", "CTRL_C", "F1"

// Convert to KeyCombination value object
$combo = $mapping->toCombination();

// Check if sequence matches input
$mapping->matches("\033[A"); // true

// Debug: get hex representation
$mapping->sequenceAsHex(); // "1b5b41" for "\033[A"
```

### SequenceType Enum

Categorizes sequences by their nature:

```php
use Butschster\Commander\Infrastructure\Keyboard\Mapping\SequenceType;

SequenceType::Escape    // ESC sequences (arrows, F-keys, navigation)
SequenceType::Control   // Ctrl+letter (ASCII 1-26)
SequenceType::Special   // Tab, Enter, Backspace, etc.
SequenceType::Printable // Regular characters
```

### TerminalType Enum

Identifies terminal compatibility:

```php
use Butschster\Commander\Infrastructure\Keyboard\Mapping\TerminalType;

TerminalType::Common  // Works in all terminals
TerminalType::Xterm   // xterm-specific (ESC O P for F1)
TerminalType::Linux   // Linux console (ESC [ 11 ~ for F1)
TerminalType::VT100   // VT100 compatible
```

### KeyMappingRegistry

Collection class with O(1) lookup:

```php
use Butschster\Commander\Infrastructure\Keyboard\Mapping\KeyMappingRegistry;

// Create with default mappings
$registry = new KeyMappingRegistry();

// Create empty (for custom setup)
$registry = new KeyMappingRegistry(registerDefaults: false);
```

### Registry Methods

```php
// Register single mapping
$registry->register(KeySequence::escape("\033[A", Key::UP));

// Register multiple
$registry->registerAll([
    KeySequence::escape("\033[A", Key::UP),
    KeySequence::escape("\033[B", Key::DOWN),
]);

// Lookup by sequence (O(1))
$mapping = $registry->findBySequence("\033[A");
if ($mapping !== null) {
    echo $mapping->toKeyName(); // "UP"
}

// Find all mappings for a key name
$f1Mappings = $registry->findByKeyName('F1');
// Returns both xterm and linux versions

// Query by type
$escapeSequences = $registry->getByType(SequenceType::Escape);
$ctrlCombos = $registry->getByType(SequenceType::Control);

// Query by terminal
$xtermMappings = $registry->getByTerminal(TerminalType::Xterm);

// Check existence
$registry->has("\033[A"); // true

// Get all mappings
$all = $registry->all();

// Count
$registry->count(); // ~50 default mappings
```

## Default Mappings

The registry comes pre-configured with standard mappings:

### Navigation Keys

| Sequence    | Key        | Notes            |
|-------------|------------|------------------|
| `\033[A`    | UP         | Arrow up         |
| `\033[B`    | DOWN       | Arrow down       |
| `\033[C`    | RIGHT      | Arrow right      |
| `\033[D`    | LEFT       | Arrow left       |
| `\033[1;5A` | CTRL_UP    | Ctrl+Arrow up    |
| `\033[1;5B` | CTRL_DOWN  | Ctrl+Arrow down  |
| `\033[1;5C` | CTRL_RIGHT | Ctrl+Arrow right |
| `\033[1;5D` | CTRL_LEFT  | Ctrl+Arrow left  |
| `\033[5~`   | PAGE_UP    | Page up          |
| `\033[6~`   | PAGE_DOWN  | Page down        |
| `\033[1~`   | HOME       | Home             |
| `\033[4~`   | END        | End              |
| `\033[2~`   | INSERT     | Insert           |
| `\033[3~`   | DELETE     | Delete           |

### Function Keys

| Sequence   | Key | Terminal |
|------------|-----|----------|
| `\033OP`   | F1  | xterm    |
| `\033[11~` | F1  | linux    |
| `\033OQ`   | F2  | xterm    |
| `\033[12~` | F2  | linux    |
| `\033OR`   | F3  | xterm    |
| `\033[13~` | F3  | linux    |
| `\033OS`   | F4  | xterm    |
| `\033[14~` | F4  | linux    |
| `\033[15~` | F5  | common   |
| `\033[17~` | F6  | common   |
| `\033[18~` | F7  | common   |
| `\033[19~` | F8  | common   |
| `\033[20~` | F9  | common   |
| `\033[21~` | F10 | common   |
| `\033[23~` | F11 | common   |
| `\033[24~` | F12 | common   |

### Special Keys

| Sequence | Key       | Notes               |
|----------|-----------|---------------------|
| `\n`     | ENTER     | Line feed (Unix)    |
| `\r`     | ENTER     | Carriage return     |
| `\r\n`   | ENTER     | CRLF (Windows)      |
| `\t`     | TAB       | Tab                 |
| `\033`   | ESCAPE    | Escape (standalone) |
| `\177`   | BACKSPACE | DEL character       |
| `\010`   | BACKSPACE | BS character        |

### Ctrl Combinations

| Sequence | Key    | Notes            |
|----------|--------|------------------|
| `\001`   | CTRL_A |                  |
| `\002`   | CTRL_B |                  |
| `\003`   | CTRL_C | Interrupt signal |
| `\004`   | CTRL_D | EOF signal       |
| `\005`   | CTRL_E |                  |
| ...      | ...    |                  |
| `\032`   | CTRL_Z | Suspend signal   |

**Note:** Some Ctrl codes overlap with special keys and are not registered:

- `\010` (Ctrl+H) = Backspace
- `\011` (Ctrl+I) = Tab
- `\012` (Ctrl+J) = Enter (LF)
- `\015` (Ctrl+M) = Enter (CR)

## Integration with KeyboardHandler

The `KeyboardHandler` uses the registry automatically:

```php
use Butschster\Commander\Infrastructure\Terminal\KeyboardHandler;

// Default: creates registry with all standard mappings
$handler = new KeyboardHandler();

// Custom registry
$customRegistry = new KeyMappingRegistry(registerDefaults: false);
$customRegistry->register(KeySequence::special("\n", Key::ENTER));
$handler = new KeyboardHandler($customRegistry);

// Access registry for extensions
$handler->getMappings()->register(
    KeySequence::escape("\033[25~", Key::F13)
);
```

### How getKey() Works

```php
public function getKey(): ?string
{
    $char = fread($this->stdin, 1);
    
    if ($char === "\033") {
        // Escape sequence - read more bytes
        return $this->readEscapeSequence();
    }
    
    // Check registry for control characters
    $mapping = $this->mappings->findBySequence($char);
    if ($mapping !== null) {
        return $mapping->toKeyName();
    }
    
    // Regular character
    return $char;
}
```

## Custom Mappings

### Adding Terminal-Specific Keys

```php
// Some terminals send F13-F24
$handler->getMappings()->registerAll([
    KeySequence::escape("\033[25~", Key::F13, terminal: TerminalType::Custom),
    KeySequence::escape("\033[26~", Key::F14, terminal: TerminalType::Custom),
]);
```

### Adding Application-Specific Shortcuts

```php
// Custom escape sequence for your application
$handler->getMappings()->register(
    new KeySequence(
        sequence: "\033[200~",
        key: Key::INSERT,
        modifiers: [Modifier::CTRL],
        type: SequenceType::Escape,
        terminal: TerminalType::Custom,
        description: 'Bracketed paste start',
    )
);
```

### Creating a Minimal Registry

```php
// Only register the keys your application uses
$minimal = new KeyMappingRegistry(registerDefaults: false);

$minimal->registerAll([
    // Navigation
    KeySequence::escape("\033[A", Key::UP),
    KeySequence::escape("\033[B", Key::DOWN),
    
    // Enter and Escape
    KeySequence::special("\n", Key::ENTER),
    KeySequence::special("\033", Key::ESCAPE),
    
    // Ctrl+C for quit
    KeySequence::ctrl("\003", Key::C),
]);

$handler = new KeyboardHandler($minimal);
```

## Debugging

### Identifying Unknown Sequences

When the handler encounters an unknown escape sequence, it returns `UNKNOWN_` followed by the hex:

```php
$key = $handler->getKey();
if (str_starts_with($key, 'UNKNOWN_')) {
    $hex = substr($key, 8);
    echo "Unknown sequence: " . $hex;
    // e.g., "Unknown sequence: 5b313b3341"
}
```

### Inspecting Registered Mappings

```php
$registry = $handler->getMappings();

// List all escape sequences
foreach ($registry->getByType(SequenceType::Escape) as $mapping) {
    printf(
        "%s → %s (%s)\n",
        $mapping->sequenceAsHex(),
        $mapping->toKeyName(),
        $mapping->terminal->value,
    );
}

// Find how F1 is registered
foreach ($registry->findByKeyName('F1') as $mapping) {
    printf(
        "F1: %s (%s)\n",
        $mapping->sequenceAsHex(),
        $mapping->terminal->value,
    );
}
// Output:
// F1: 1b4f50 (xterm)
// F1: 1b5b31317e (linux)
```

## Best Practices

1. **Use factory methods** - `KeySequence::escape()`, `::ctrl()`, `::special()` are clearer than the full constructor

2. **Add descriptions** for non-obvious sequences:
   ```php
   KeySequence::ctrl("\003", Key::C, 'Interrupt signal')
   ```

3. **Specify terminal type** when the sequence is terminal-specific:
   ```php
   KeySequence::escape("\033OP", Key::F1, terminal: TerminalType::Xterm)
   ```

4. **Don't modify the default registry** unless you need to - it covers most use cases

5. **Use `findBySequence()` for lookups** - it's O(1) hash map access

## See Also

- [Keyboard Input Handling](keyboard-input-handling.md) - Using `KeyInput` in components
- [Keyboard Architecture](keyboard-architecture.md) - Application-level key bindings
- [Component Input Patterns](component-input-patterns.md) - Input handling patterns
