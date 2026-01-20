# Stage 6c: Key Mapping DTOs

## Overview

Replace the `KEY_MAPPINGS` array in `KeyboardHandler` with proper DTOs for better type safety, organization, and extensibility.

---

## Current Problems

1. **Magic strings everywhere** - `'UP'`, `'CTRL_A'`, `'F1'` scattered in array
2. **No type safety** - Array values are just strings
3. **Hard to extend** - Adding terminal-specific sequences requires modifying the array
4. **No categorization** - All mappings mixed together
5. **Comments as documentation** - Terminal compatibility notes buried in comments

---

## Proposed Structure

```structure
src/Infrastructure/Keyboard/
├── Key.php                    # Existing enum
├── KeyInput.php               # Existing value object
├── KeyCombination.php         # Existing value object
├── Modifier.php               # Existing enum
├── Mapping/
│   ├── KeySequence.php        # DTO: raw bytes → Key + modifiers
│   ├── SequenceType.php       # Enum: escape, control, special
│   ├── TerminalType.php       # Enum: xterm, linux, common
│   └── KeyMappingRegistry.php # Collection of KeySequence DTOs
└── KeyboardHandler.php        # Uses registry instead of array
```

---

## DTO Design

### KeySequence DTO

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Keyboard\Mapping;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\Modifier;

/**
 * Immutable DTO mapping a raw byte sequence to a key combination.
 */
final readonly class KeySequence
{
    /**
     * @param string $sequence Raw byte sequence (e.g., "\033[A", "\001")
     * @param Key $key The key this sequence represents
     * @param array<Modifier> $modifiers Active modifiers (Ctrl, Alt, Shift)
     * @param SequenceType $type Type of sequence for categorization
     * @param TerminalType $terminal Terminal compatibility
     * @param string|null $description Human-readable description
     */
    public function __construct(
        public string $sequence,
        public Key $key,
        public array $modifiers = [],
        public SequenceType $type = SequenceType::Special,
        public TerminalType $terminal = TerminalType::Common,
        public ?string $description = null,
    ) {}

    /**
     * Check if this mapping has a specific modifier.
     */
    public function hasModifier(Modifier $modifier): bool
    {
        return \in_array($modifier, $this->modifiers, true);
    }

    /**
     * Check if this has Ctrl modifier.
     */
    public function hasCtrl(): bool
    {
        return $this->hasModifier(Modifier::CTRL);
    }

    /**
     * Get the output key name (e.g., "UP", "CTRL_C", "F1").
     */
    public function toKeyName(): string
    {
        $prefix = '';
        
        foreach ($this->modifiers as $modifier) {
            $prefix .= $modifier->value . '_';
        }
        
        return $prefix . $this->key->value;
    }

    /**
     * Check if sequence matches raw input.
     */
    public function matches(string $input): bool
    {
        return $this->sequence === $input;
    }
}
```

### SequenceType Enum

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Keyboard\Mapping;

/**
 * Type of key sequence for categorization.
 */
enum SequenceType: string
{
    case Escape = 'escape';      // ESC sequences (arrows, function keys)
    case Control = 'control';    // Ctrl+letter (ASCII 1-26)
    case Special = 'special';    // Tab, Enter, Backspace, etc.
    case Printable = 'printable'; // Regular characters
}
```

### TerminalType Enum

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Keyboard\Mapping;

/**
 * Terminal type for sequence compatibility.
 */
enum TerminalType: string
{
    case Common = 'common';  // Works in all terminals
    case Xterm = 'xterm';    // xterm-specific sequences
    case Linux = 'linux';    // Linux console specific
    case VT100 = 'vt100';    // VT100 compatible
}
```

### KeyMappingRegistry

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Keyboard\Mapping;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\Modifier;

/**
 * Registry of all key sequence mappings.
 */
final class KeyMappingRegistry
{
    /** @var array<string, KeySequence> Indexed by sequence for fast lookup */
    private array $bySequence = [];

    /** @var array<string, list<KeySequence>> Indexed by key name */
    private array $byKeyName = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    /**
     * Register a key sequence mapping.
     */
    public function register(KeySequence $mapping): void
    {
        $this->bySequence[$mapping->sequence] = $mapping;
        $this->byKeyName[$mapping->toKeyName()][] = $mapping;
    }

    /**
     * Find mapping by raw sequence.
     */
    public function findBySequence(string $sequence): ?KeySequence
    {
        return $this->bySequence[$sequence] ?? null;
    }

    /**
     * Find all mappings for a key name.
     * 
     * @return list<KeySequence>
     */
    public function findByKeyName(string $keyName): array
    {
        return $this->byKeyName[$keyName] ?? [];
    }

    /**
     * Get all mappings of a specific type.
     * 
     * @return list<KeySequence>
     */
    public function getByType(SequenceType $type): array
    {
        return \array_values(\array_filter(
            $this->bySequence,
            static fn(KeySequence $m) => $m->type === $type,
        ));
    }

    /**
     * Get all registered mappings.
     * 
     * @return array<string, KeySequence>
     */
    public function all(): array
    {
        return $this->bySequence;
    }

    private function registerDefaults(): void
    {
        // Navigation keys
        $this->registerNavigation();
        
        // Function keys
        $this->registerFunctionKeys();
        
        // Special keys
        $this->registerSpecialKeys();
        
        // Ctrl combinations
        $this->registerCtrlCombinations();
    }

    private function registerNavigation(): void
    {
        // Arrow keys (common)
        $this->register(new KeySequence("\033[A", Key::UP, [], SequenceType::Escape));
        $this->register(new KeySequence("\033[B", Key::DOWN, [], SequenceType::Escape));
        $this->register(new KeySequence("\033[C", Key::RIGHT, [], SequenceType::Escape));
        $this->register(new KeySequence("\033[D", Key::LEFT, [], SequenceType::Escape));

        // Ctrl+Arrow keys
        $this->register(new KeySequence("\033[1;5A", Key::UP, [Modifier::CTRL], SequenceType::Escape));
        $this->register(new KeySequence("\033[1;5B", Key::DOWN, [Modifier::CTRL], SequenceType::Escape));
        $this->register(new KeySequence("\033[1;5C", Key::RIGHT, [Modifier::CTRL], SequenceType::Escape));
        $this->register(new KeySequence("\033[1;5D", Key::LEFT, [Modifier::CTRL], SequenceType::Escape));

        // Page navigation
        $this->register(new KeySequence("\033[5~", Key::PAGE_UP, [], SequenceType::Escape));
        $this->register(new KeySequence("\033[6~", Key::PAGE_DOWN, [], SequenceType::Escape));
        $this->register(new KeySequence("\033[1~", Key::HOME, [], SequenceType::Escape));
        $this->register(new KeySequence("\033[4~", Key::END, [], SequenceType::Escape));
        $this->register(new KeySequence("\033[2~", Key::INSERT, [], SequenceType::Escape));
        $this->register(new KeySequence("\033[3~", Key::DELETE, [], SequenceType::Escape));
    }

    private function registerFunctionKeys(): void
    {
        // F1-F4 (xterm style)
        $this->register(new KeySequence("\033OP", Key::F1, [], SequenceType::Escape, TerminalType::Xterm));
        $this->register(new KeySequence("\033OQ", Key::F2, [], SequenceType::Escape, TerminalType::Xterm));
        $this->register(new KeySequence("\033OR", Key::F3, [], SequenceType::Escape, TerminalType::Xterm));
        $this->register(new KeySequence("\033OS", Key::F4, [], SequenceType::Escape, TerminalType::Xterm));

        // F1-F4 (linux console style)
        $this->register(new KeySequence("\033[11~", Key::F1, [], SequenceType::Escape, TerminalType::Linux));
        $this->register(new KeySequence("\033[12~", Key::F2, [], SequenceType::Escape, TerminalType::Linux));
        $this->register(new KeySequence("\033[13~", Key::F3, [], SequenceType::Escape, TerminalType::Linux));
        $this->register(new KeySequence("\033[14~", Key::F4, [], SequenceType::Escape, TerminalType::Linux));

        // F5-F12 (common)
        $this->register(new KeySequence("\033[15~", Key::F5, [], SequenceType::Escape));
        $this->register(new KeySequence("\033[17~", Key::F6, [], SequenceType::Escape));
        $this->register(new KeySequence("\033[18~", Key::F7, [], SequenceType::Escape));
        $this->register(new KeySequence("\033[19~", Key::F8, [], SequenceType::Escape));
        $this->register(new KeySequence("\033[20~", Key::F9, [], SequenceType::Escape));
        $this->register(new KeySequence("\033[21~", Key::F10, [], SequenceType::Escape));
        $this->register(new KeySequence("\033[23~", Key::F11, [], SequenceType::Escape));
        $this->register(new KeySequence("\033[24~", Key::F12, [], SequenceType::Escape));
    }

    private function registerSpecialKeys(): void
    {
        // Enter (multiple representations)
        $this->register(new KeySequence("\n", Key::ENTER, [], SequenceType::Special, description: 'Line feed (Unix)'));
        $this->register(new KeySequence("\r", Key::ENTER, [], SequenceType::Special, description: 'Carriage return'));
        $this->register(new KeySequence("\r\n", Key::ENTER, [], SequenceType::Special, description: 'CRLF (Windows)'));

        // Other special keys
        $this->register(new KeySequence("\t", Key::TAB, [], SequenceType::Special));
        $this->register(new KeySequence("\033", Key::ESCAPE, [], SequenceType::Special));
        $this->register(new KeySequence("\177", Key::BACKSPACE, [], SequenceType::Special, description: 'DEL character'));
        $this->register(new KeySequence("\010", Key::BACKSPACE, [], SequenceType::Special, description: 'BS character'));
    }

    private function registerCtrlCombinations(): void
    {
        // Ctrl+A through Ctrl+Z (ASCII 1-26)
        // Note: Some overlap with special keys (Ctrl+H=BS, Ctrl+I=Tab, Ctrl+J=LF, Ctrl+M=CR)
        $ctrlMappings = [
            "\001" => Key::A,
            "\002" => Key::B,
            "\003" => Key::C,
            "\004" => Key::D,
            "\005" => Key::E,
            "\006" => Key::F,
            "\007" => Key::G,
            // "\010" => Key::H, // Backspace
            // "\011" => Key::I, // Tab
            // "\012" => Key::J, // Line feed (Enter)
            "\013" => Key::K,
            "\014" => Key::L,
            // "\015" => Key::M, // Carriage return (Enter)
            "\016" => Key::N,
            "\017" => Key::O,
            "\020" => Key::P,
            "\021" => Key::Q,
            "\022" => Key::R,
            "\023" => Key::S,
            "\024" => Key::T,
            "\025" => Key::U,
            "\026" => Key::V,
            "\027" => Key::W,
            "\030" => Key::X,
            "\031" => Key::Y,
            "\032" => Key::Z,
        ];

        foreach ($ctrlMappings as $sequence => $key) {
            $this->register(new KeySequence($sequence, $key, [Modifier::CTRL], SequenceType::Control));
        }
    }
}
```

---

## Updated KeyboardHandler

```php
public function __construct(
    private readonly KeyMappingRegistry $mappings = new KeyMappingRegistry(),
) {
    $this->stdin = STDIN;
}

public function getKey(): ?string
{
    // ... read from stdin ...

    // Check for known sequences
    $mapping = $this->mappings->findBySequence($char);
    if ($mapping !== null) {
        return $mapping->toKeyName();
    }

    // ... rest of logic ...
}
```

---

## Benefits

1. **Type Safety** - `Key` enum instead of strings
2. **Self-Documenting** - Each mapping has description, terminal type
3. **Extensible** - Easy to add custom mappings or terminal-specific ones
4. **Testable** - Can mock registry, test individual mappings
5. **Organized** - Categorized by type (escape, control, special)
6. **Discoverable** - Can list all mappings for help screens

---

## Migration Steps

1. [x] Create `SequenceType` enum ✅
2. [x] Create `TerminalType` enum ✅
3. [x] Create `KeySequence` DTO ✅
4. [x] Create `KeyMappingRegistry` ✅
5. [x] Update `KeyboardHandler` to use registry ✅
6. [x] Remove `KEY_MAPPINGS` constant ✅
7. [ ] Add tests for mappings

---

## Files Created

```structure
src/Infrastructure/Keyboard/Mapping/
├── SequenceType.php      # Enum: escape, control, special, printable
├── TerminalType.php      # Enum: common, xterm, linux, vt100
├── KeySequence.php       # DTO: sequence → Key + modifiers
└── KeyMappingRegistry.php # Collection with fast lookup
```

## Updated Files

- `src/Infrastructure/Terminal/KeyboardHandler.php`
  - Removed 95-line `KEY_MAPPINGS` constant
  - Added `KeyMappingRegistry` dependency injection
  - Added `getMappings()` method for extensibility
  - Updated `getKey()` and `readEscapeSequence()` to use registry
