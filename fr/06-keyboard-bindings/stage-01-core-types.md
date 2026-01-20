# Stage 1: Core Types (Key Enum & KeyCombination)

## Overview

Create the foundational type system for keyboard handling — type-safe key constants
and a value object for key combinations.

## Files to Create

```
src/Infrastructure/Keyboard/
├── Key.php              # Enum with all key constants  
├── Modifier.php         # Enum: CTRL, ALT, SHIFT
└── KeyCombination.php   # Value object: key + modifiers
```

## Key Enum Specification

Must contain all keys from `KeyboardHandler::KEY_MAPPINGS` (lines 17-95):

| Category | Keys |
|----------|------|
| Navigation | UP, DOWN, LEFT, RIGHT, HOME, END, PAGE_UP, PAGE_DOWN |
| Function | F1, F2, F3, F4, F5, F6, F7, F8, F9, F10, F11, F12 |
| Special | ENTER, ESCAPE, TAB, SPACE, BACKSPACE, DELETE, INSERT |
| Letters | A-Z (for Ctrl+Letter) |
| Digits | 0-9 (for Modal quick-select) |

Helper methods:
- `isNavigation(): bool`
- `isFunctionKey(): bool`
- `isLetter(): bool`
- `isDigit(): bool`

## Modifier Enum Specification

Three cases: CTRL, ALT, SHIFT

Method: `toRawPrefix(): string` returns "CTRL_", "ALT_", "SHIFT_"

## KeyCombination Specification

### Properties
- `Key $key` — the base key
- `bool $ctrl` — Ctrl modifier
- `bool $alt` — Alt modifier  
- `bool $shift` — Shift modifier

### Factory Methods
- `key(Key $key): self` — key alone
- `ctrl(Key $key): self` — Ctrl+Key
- `alt(Key $key): self` — Alt+Key
- `shift(Key $key): self` — Shift+Key
- `ctrlShift(Key $key): self` — Ctrl+Shift+Key
- `ctrlAlt(Key $key): self` — Ctrl+Alt+Key

### Methods
- `fromString(string $combo): self` — parse "Ctrl+C", "F12"
- `matches(string $rawKey): bool` — compare with KeyboardHandler output
- `toRawKey(): string` — produce KeyboardHandler-compatible string
- `__toString(): string` — human-readable "Ctrl+C", "F12"
- `hasModifiers(): bool`

## Definition of Done

- [ ] `Key` enum contains all keys from KeyboardHandler::KEY_MAPPINGS
- [ ] `Key` enum includes A-Z letters and 0-9 digits
- [ ] `Key` has helper methods (isNavigation, etc.)
- [ ] `Modifier` enum has CTRL, ALT, SHIFT with toRawPrefix()
- [ ] `KeyCombination` is readonly and immutable
- [ ] All factory methods work correctly
- [ ] `fromString()` parses "Ctrl+C", "F12", "Ctrl+Shift+A"
- [ ] `__toString()` produces readable format
- [ ] `matches()` correctly compares with raw KeyboardHandler output
- [ ] `toRawKey()` produces "CTRL_C", "F12", "UP" format

## Code References

- `src/Infrastructure/Terminal/KeyboardHandler.php:17-95` — KEY_MAPPINGS constant

## Dependencies

**Requires:** None (foundation stage)
**Enables:** Stage 2 (Binding System)
