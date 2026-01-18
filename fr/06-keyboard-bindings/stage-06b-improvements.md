# Stage 6b: Keyboard System Improvements

## Overview

Building on Stage 6's refactoring, this document proposes improvements to make keyboard handling more consistent, type-safe, and elegant across the application.

---

## Problem Analysis

### Current Issues

1. **Inconsistent Ctrl handling** - Some files use `$key === 'CTRL_R'`, others use `Key::tryFromRaw()`
2. **No unified KeyInput abstraction** - Components receive raw strings, must parse themselves
3. **KeyCombination underutilized** - Excellent class exists but not used in `handleInput()` methods
4. **Repeated patterns** - Every component has similar boilerplate for key matching
5. **No component-level binding support** - Components can't declare their shortcuts declaratively

---

## Proposed Improvements

### Improvement 1: KeyInput Value Object

Create a lightweight wrapper around raw key strings that provides convenient methods:

```php
// src/Infrastructure/Keyboard/KeyInput.php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Keyboard;

/**
 * Immutable value object wrapping raw keyboard input.
 * 
 * Provides convenient methods for checking key types and combinations
 * without repeatedly parsing the raw string.
 */
final readonly class KeyInput
{
    private ?Key $key;
    private bool $hasCtrl;
    private bool $hasAlt;
    private bool $hasShift;

    public function __construct(
        public string $raw,
    ) {
        $this->hasCtrl = \str_starts_with($raw, 'CTRL_');
        $this->hasAlt = \str_contains($raw, 'ALT_');
        $this->hasShift = \str_contains($raw, 'SHIFT_');
        $this->key = Key::tryFromRaw($raw);
    }

    /**
     * Get the base key (without modifiers).
     */
    public function key(): ?Key
    {
        return $this->key;
    }

    /**
     * Check if this is a specific key (ignoring modifiers).
     */
    public function is(Key $key): bool
    {
        return $this->key === $key;
    }

    /**
     * Check if this is Ctrl+Key.
     */
    public function isCtrl(Key $key): bool
    {
        return $this->hasCtrl && $this->key === $key;
    }

    /**
     * Check if this matches a KeyCombination.
     */
    public function matches(KeyCombination $combination): bool
    {
        return $combination->matches($this->raw);
    }

    /**
     * Check if any Ctrl modifier is present.
     */
    public function hasCtrl(): bool
    {
        return $this->hasCtrl;
    }

    /**
     * Check if key is navigation (arrows, home, end, page up/down).
     */
    public function isNavigation(): bool
    {
        return $this->key?->isNavigation() ?? false;
    }

    /**
     * Check if key is a function key (F1-F12).
     */
    public function isFunctionKey(): bool
    {
        return $this->key?->isFunctionKey() ?? false;
    }

    /**
     * Check if key is a printable character.
     */
    public function isPrintable(): bool
    {
        return \mb_strlen($this->raw) === 1 
            && \ord($this->raw) >= 32 
            && \ord($this->raw) < 127;
    }

    /**
     * Get as printable character (or null if not printable).
     */
    public function char(): ?string
    {
        return $this->isPrintable() ? $this->raw : null;
    }
}
```

### Improvement 2: Update Component Signatures

Change `handleInput(string $key)` to `handleInput(KeyInput $input)`:

**Before:**
```php
public function handleInput(string $key): bool
{
    $keyEnum = Key::tryFrom($key);
    
    return match ($keyEnum) {
        Key::TAB => $this->switchPanel() ?? true,
        default => match ($key) {
            'CTRL_R' => $this->refresh() ?? true,
            default => false,
        },
    };
}
```

**After:**
```php
public function handleInput(KeyInput $input): bool
{
    return match (true) {
        $input->is(Key::TAB) => $this->switchPanel() ?? true,
        $input->isCtrl(Key::R) => $this->refresh() ?? true,
        default => false,
    };
}
```

### Improvement 3: InputHandler Trait for Common Patterns

```php
// src/UI/Component/Concerns/HandlesInput.php
<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Concerns;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;

trait HandlesInput
{
    /**
     * Handle vertical navigation (UP/DOWN/PAGE_UP/PAGE_DOWN/HOME/END).
     * 
     * @return bool|null True if handled, null to continue, false if not applicable
     */
    protected function handleVerticalNavigation(
        KeyInput $input,
        int &$index,
        int $totalItems,
        int $pageSize,
    ): ?bool {
        return match ($input->key()) {
            Key::UP => $index > 0 ? (--$index !== null) : null,
            Key::DOWN => $index < $totalItems - 1 ? (++$index !== null) : null,
            Key::PAGE_UP => ($index = \max(0, $index - $pageSize)) !== null,
            Key::PAGE_DOWN => ($index = \min($totalItems - 1, $index + $pageSize)) !== null,
            Key::HOME => ($index = 0) !== null,
            Key::END => ($index = $totalItems - 1) !== null,
            default => null,
        };
    }

    /**
     * Handle cursor navigation in text (LEFT/RIGHT/HOME/END).
     */
    protected function handleCursorNavigation(
        KeyInput $input,
        int &$cursor,
        int $textLength,
    ): ?bool {
        return match ($input->key()) {
            Key::LEFT => $cursor > 0 ? (--$cursor !== null) : null,
            Key::RIGHT => $cursor < $textLength ? (++$cursor !== null) : null,
            Key::HOME => ($cursor = 0) !== null,
            Key::END => ($cursor = $textLength) !== null,
            default => null,
        };
    }
}
```

### Improvement 4: Declarative Key Bindings for Components

Allow components to declare their bindings using attributes:

```php
// src/Infrastructure/Keyboard/Attribute/KeyBind.php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Keyboard\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class KeyBind
{
    public function __construct(
        public string $key,           // "Ctrl+R", "F1", "Escape"
        public string $description,
        public string $category = 'component',
    ) {}
}
```

**Usage:**
```php
final class InstalledPackagesTab extends AbstractTab
{
    #[KeyBind('Ctrl+R', 'Refresh package list')]
    private function refresh(): void
    {
        $this->composerService->clearCache();
        $this->loadData();
    }

    #[KeyBind('Tab', 'Switch panel')]
    private function switchPanel(): void
    {
        $this->focusedPanelIndex = ($this->focusedPanelIndex + 1) % 2;
        $this->updateFocus();
    }

    // Auto-generated shortcuts for status bar
    public function getShortcuts(): array
    {
        return KeyBindCollector::fromClass($this);
    }
}
```

---

## Implementation Priority

| Priority | Improvement | Impact | Effort |
|----------|------------|--------|--------|
| 1️⃣ High | KeyInput value object | High - cleaner code everywhere | Low |
| 2️⃣ Medium | HandlesInput trait | Medium - reduces duplication | Low |
| 3️⃣ Low | Declarative bindings | Medium - better DX | Medium |

---

## Migration Path

### Phase 1: Add KeyInput (Non-Breaking)

1. Create `KeyInput` class
2. Add `KeyInput::from(string $raw)` factory method
3. Components can adopt gradually - both signatures work

### Phase 2: Add HandlesInput Trait (Non-Breaking)

1. Create trait with common navigation helpers
2. Components can use trait optionally
3. Reduces code duplication in lists/tables/text displays

### Phase 3: Update Interface (Breaking)

1. Change `ComponentInterface::handleInput()` signature
2. Update all components
3. Keyboard handler creates `KeyInput` before dispatching

---

## Specific File Improvements

### TabContainer.php - Simplify with KeyInput

**Current:**
```php
public function handleInput(string $key): bool
{
    $ctrlKey = Key::tryFromRaw($key);

    if (\str_starts_with($key, 'CTRL_')) {
        return match ($ctrlKey) {
            Key::LEFT => $this->previousTab() ?? true,
            Key::RIGHT => $this->nextTab() ?? true,
            default => $this->delegateToActiveTab($key),
        };
    }

    return $this->delegateToActiveTab($key);
}
```

**With KeyInput:**
```php
public function handleInput(KeyInput $input): bool
{
    return match (true) {
        $input->isCtrl(Key::LEFT) => $this->previousTab() ?? true,
        $input->isCtrl(Key::RIGHT) => $this->nextTab() ?? true,
        default => $this->delegateToActiveTab($input),
    };
}
```

### ListComponent.php - Use Navigation Trait

**Current:**
```php
$handled = match ($keyEnum) {
    Key::UP => $this->selectedIndex > 0 ? (--$this->selectedIndex !== null) && $this->adjustScroll() === null : false,
    Key::DOWN => $this->selectedIndex < \count($this->items) - 1 ? (++$this->selectedIndex !== null) && $this->adjustScroll() === null : false,
    Key::PAGE_UP => ($this->selectedIndex = \max(0, $this->selectedIndex - $this->visibleRows)) !== null && $this->adjustScroll() === null,
    // ... more cases
};
```

**With Trait:**
```php
use HandlesInput;

public function handleInput(KeyInput $input): bool
{
    $oldIndex = $this->selectedIndex;
    
    $result = $this->handleVerticalNavigation(
        $input,
        $this->selectedIndex,
        \count($this->items),
        $this->visibleRows,
    );
    
    if ($result !== null) {
        $this->adjustScroll();
        if ($oldIndex !== $this->selectedIndex) {
            $this->notifyChange();
        }
        return true;
    }
    
    if ($input->is(Key::ENTER)) {
        return $this->handleEnter();
    }
    
    return false;
}
```

---

## Migration Checklist

Components that can be updated to use `KeyInput` and/or `HandlesInput` trait:

### ✅ Already Updated
| Component | KeyInput | HandlesInput Trait | Notes |
|-----------|----------|-------------------|-------|
| TabContainer | ✅ | - | Uses `isCtrl()` for Ctrl+Arrow |
| ListComponent | ✅ | ✅ | Uses `handleVerticalNavigation()` |

### 📋 Candidates for HandlesInput Trait

| Component | Current Pattern | Trait Method to Use |
|-----------|-----------------|---------------------|
| TableComponent | Vertical navigation match | `handleVerticalNavigation()` |
| TextDisplay | Scroll navigation match | `handleScrollNavigation()` |
| FileContentViewer | Scroll navigation match | `handleScrollNavigation()` |
| TextField | Cursor navigation match | `handleCursorNavigation()` |
| Modal | Horizontal button nav | `handleHorizontalNavigation()` |
| MenuDropdown | Vertical menu nav | `handleVerticalNavigation()` |
| FormComponent | Vertical field nav | `handleVerticalNavigation()` |

### 📋 Candidates for KeyInput Only

| Component | Benefit |
|-----------|---------|
| CommandsScreen | Cleaner `isCtrl()` checks for CTRL_E |
| FileBrowserScreen | Cleaner `isCtrl()` checks for CTRL_R, CTRL_G |
| InstalledPackagesTab | Cleaner `isCtrl()` check for CTRL_R |
| OutdatedPackagesTab | Cleaner `isCtrl()` check for CTRL_R |
| ScriptsTab | Cleaner `isCtrl()` checks for CTRL_R, CTRL_C |
| SecurityAuditTab | Cleaner `isCtrl()` check for CTRL_R |
| CheckboxField | Cleaner `isSpace()` check |

---

## Benefits Summary

1. **Type Safety** - `KeyInput` provides type-safe access to key properties
2. **Cleaner Code** - No more `Key::tryFrom()` / `Key::tryFromRaw()` dance
3. **Consistency** - Single way to check Ctrl combinations: `$input->isCtrl(Key::R)`
4. **Reduced Duplication** - Common navigation patterns extracted to traits
5. **Better DX** - Declarative bindings make shortcuts self-documenting
6. **Testability** - `KeyInput` objects easy to construct in tests

---

## Next Steps

1. [x] Implement `KeyInput` class ✅
2. [x] Add `HandlesInput` trait ✅
3. [x] Update TabContainer as proof-of-concept ✅
4. [x] Update ListComponent with trait usage ✅
5. [ ] Create migration guide for remaining components
6. [ ] Consider declarative bindings for v2

---

## Completed Examples

### TabContainer - Now uses KeyInput

```php
public function handleInput(string $key): bool
{
    $input = KeyInput::from($key);

    if ($input->isCtrl(Key::LEFT)) {
        $this->previousTab();
        return true;
    }

    if ($input->isCtrl(Key::RIGHT)) {
        $this->nextTab();
        return true;
    }

    return $this->getActiveTab()?->handleInput($key) ?? false;
}
```

### ListComponent - Now uses HandlesInput trait

```php
use HandlesInput;

public function handleInput(string $key): bool
{
    $input = KeyInput::from($key);
    $oldIndex = $this->selectedIndex;

    $handled = $this->handleVerticalNavigation(
        $input,
        $this->selectedIndex,
        \count($this->items),
        $this->visibleRows,
    );

    if ($handled !== null) {
        $this->adjustScroll();
        if ($oldIndex !== $this->selectedIndex && $this->onChange !== null) {
            ($this->onChange)($this->getSelectedItem(), $this->selectedIndex);
        }
        return true;
    }

    if ($input->is(Key::ENTER)) {
        return $this->handleEnter();
    }

    return false;
}
```
