# Stage 3: KeyboardHandler Integration

## Overview

Extend `KeyboardHandler` to work with the new `Key` enum and `KeyCombination` while
maintaining full backward compatibility with existing code that uses string keys.

## Files

**MODIFY:**
- `src/Infrastructure/Terminal/KeyboardHandler.php` - Add new methods for typed key access

## Code References

- `src/Infrastructure/Terminal/KeyboardHandler.php:17-85` - Current KEY_MAPPINGS
- `src/Infrastructure/Terminal/KeyboardHandler.php:120-148` - Current `getKey()` method

## Implementation Details

### New Methods to Add

Add these methods to `KeyboardHandler` without changing existing behavior:

```php
/**
 * Parse raw key string to Key enum
 * 
 * @param string $rawKey Raw key from getKey() like 'UP', 'CTRL_C', 'F12'
 * @return Key|null Key enum or null if not mappable
 */
public function parseToKey(string $rawKey): ?Key
{
    // Direct enum match
    $key = Key::tryFrom($rawKey);
    if ($key !== null) {
        return $key;
    }
    
    // Handle CTRL_X format
    if (\str_starts_with($rawKey, 'CTRL_')) {
        $letter = \substr($rawKey, 5);
        return Key::tryFrom($letter);
    }
    
    // Handle single letters (uppercase for enum)
    if (\strlen($rawKey) === 1 && \ctype_alpha($rawKey)) {
        return Key::tryFrom(\strtoupper($rawKey));
    }
    
    return null;
}

/**
 * Parse raw key string to KeyCombination
 * 
 * @param string $rawKey Raw key from getKey()
 * @return KeyCombination|null Combination or null if not parseable
 */
public function parseToCombination(string $rawKey): ?KeyCombination
{
    // Handle CTRL_X format
    if (\str_starts_with($rawKey, 'CTRL_')) {
        $keyPart = \substr($rawKey, 5);
        $key = Key::tryFrom($keyPart);
        if ($key !== null) {
            return KeyCombination::ctrl($key);
        }
        // Try as letter
        if (\strlen($keyPart) === 1 && \ctype_alpha($keyPart)) {
            $key = Key::tryFrom(\strtoupper($keyPart));
            if ($key !== null) {
                return KeyCombination::ctrl($key);
            }
        }
        return null;
    }
    
    // Direct key match
    $key = Key::tryFrom($rawKey);
    if ($key !== null) {
        return KeyCombination::key($key);
    }
    
    // Single letter
    if (\strlen($rawKey) === 1 && \ctype_alpha($rawKey)) {
        $key = Key::tryFrom(\strtoupper($rawKey));
        if ($key !== null) {
            return KeyCombination::key($key);
        }
    }
    
    return null;
}

/**
 * Get next key as KeyCombination (non-blocking)
 * 
 * @return KeyCombination|null Combination or null if no input
 */
public function getKeyCombination(): ?KeyCombination
{
    $raw = $this->getKey();
    if ($raw === null) {
        return null;
    }
    
    return $this->parseToCombination($raw);
}
```

### Required Imports

Add at top of file:
```php
use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;
```

### Backward Compatibility

**Do NOT modify** the existing `getKey()` method. It must continue returning
strings like `'UP'`, `'CTRL_C'`, `'F12'` for all existing code.

The new methods are **additive** - they provide a typed alternative without
breaking anything.

## Definition of Done

- [ ] `parseToKey()` correctly maps all KEY_MAPPINGS values to Key enum
- [ ] `parseToKey()` handles CTRL_X format (returns the base key)
- [ ] `parseToKey()` handles single letter keys
- [ ] `parseToCombination()` returns correct KeyCombination for all inputs
- [ ] `parseToCombination()` correctly sets ctrl=true for CTRL_X inputs
- [ ] `getKeyCombination()` is a convenience wrapper around getKey() + parse
- [ ] Existing `getKey()` method is unchanged
- [ ] All existing code continues to work
- [ ] No breaking changes to public API

## Dependencies

**Requires**: Stage 1 (Key, KeyCombination)
**Enables**: Stage 5 (Application refactoring), Stage 6 (Screen refactoring)
