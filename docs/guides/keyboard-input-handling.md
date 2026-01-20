``# Keyboard Input Handling Guide

This guide explains how to handle keyboard input in Commander UI components using the `KeyInput` value object and
`HandlesInput` trait.

## Overview

``
The keyboard handling system consists of three layers:

```
┌─────────────────────────────────────────────────────────┐
│                    Raw Terminal Input                    │
│              "\033[A", "\003", "a", "\n"                 │
└─────────────────────┬───────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────┐
│                  KeyboardHandler                         │
│         Converts sequences to key names                  │
│              "UP", "CTRL_C", "a", "ENTER"               │
└─────────────────────┬───────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────┐
│                     KeyInput                             │
│         Type-safe wrapper with helper methods            │
│    $input->is(Key::UP), $input->isCtrl(Key::C)          │
└─────────────────────┬───────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────┐
│                   Your Component                         │
│              handleInput(string $key)                    │
└─────────────────────────────────────────────────────────┘
```

## KeyInput Class

### Creating a KeyInput

```php
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;

// In your handleInput method
public function handleInput(string $key): bool
{
    $input = KeyInput::from($key);
    
    // Now use $input for type-safe key checking
}
```

### Basic Key Checking

```php
use Butschster\Commander\Infrastructure\Keyboard\Key;

// Check for a specific key (ignoring modifiers)
if ($input->is(Key::ENTER)) {
    $this->submit();
    return true;
}

// Check for exact key (no modifiers)
if ($input->isExactly(Key::TAB)) {
    $this->nextField();
    return true;
}

// Check if key is one of several options
if ($input->isAnyOf(Key::UP, Key::DOWN, Key::LEFT, Key::RIGHT)) {
    $this->handleNavigation($input);
    return true;
}
```

### Modifier Key Checking

```php
// Ctrl combinations
if ($input->isCtrl(Key::C)) {
    $this->cancel();
    return true;
}

if ($input->isCtrl(Key::S)) {
    $this->save();
    return true;
}

// Alt combinations
if ($input->isAlt(Key::F)) {
    $this->openFileMenu();
    return true;
}

// Shift combinations
if ($input->isShift(Key::TAB)) {
    $this->previousField();
    return true;
}

// Ctrl+Shift combinations
if ($input->isCtrlShift(Key::S)) {
    $this->saveAs();
    return true;
}

// Check if any modifier is present
if ($input->hasModifiers()) {
    // Handle modified key differently
}

// Check specific modifier presence
if ($input->hasCtrl()) {
    // Ctrl is held
}
```

### Key Type Checking

```php
// Navigation keys (UP, DOWN, LEFT, RIGHT, HOME, END, PAGE_UP, PAGE_DOWN)
if ($input->isNavigation()) {
    return $this->handleNavigation($input);
}

// Function keys (F1-F12)
if ($input->isFunctionKey()) {
    return $this->handleFunctionKey($input);
}

// Letter keys (A-Z)
if ($input->isLetter()) {
    return $this->handleHotkey($input);
}

// Digit keys (0-9)
if ($input->isDigit()) {
    return $this->handleQuickSelect($input);
}
```

### Character Input

```php
// Check if it's a printable character
if ($input->isPrintable()) {
    $char = $input->char();
    $this->insertCharacter($char);
    return true;
}

// Space key (can be ' ' or Key::SPACE)
if ($input->isSpace()) {
    $this->toggleSelection();
    return true;
}
```

### Using with Match Expression

The most elegant way to handle multiple keys:

```php
public function handleInput(string $key): bool
{
    $input = KeyInput::from($key);

    return match (true) {
        $input->is(Key::UP) => $this->moveUp() ?? true,
        $input->is(Key::DOWN) => $this->moveDown() ?? true,
        $input->is(Key::ENTER) => $this->select() ?? true,
        $input->is(Key::ESCAPE) => $this->cancel() ?? true,
        $input->isCtrl(Key::R) => $this->refresh() ?? true,
        $input->isCtrl(Key::S) => $this->save() ?? true,
        $input->isPrintable() => $this->insertChar($input->char()) ?? true,
        default => false,
    };
}
```

### Accessing Raw Values

```php
// Get the underlying Key enum (or null)
$key = $input->key();

// Get the raw string that was passed in
$raw = $input->raw;
```

## HandlesInput Trait

For common navigation patterns, use the `HandlesInput` trait to reduce boilerplate.

### Adding the Trait

```php
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Component\Concerns\HandlesInput;

final class MyListComponent extends AbstractComponent
{
    use HandlesInput;
    
    // ...
}
```

### Vertical Navigation (Lists, Tables)

```php
public function handleInput(string $key): bool
{
    $input = KeyInput::from($key);
    $oldIndex = $this->selectedIndex;

    // Handle UP/DOWN/PAGE_UP/PAGE_DOWN/HOME/END
    $handled = $this->handleVerticalNavigation(
        $input,
        $this->selectedIndex,      // Modified by reference
        count($this->items),       // Total items
        $this->visibleRows,        // Page size for PAGE_UP/DOWN
    );

    if ($handled !== null) {
        $this->adjustScroll();
        if ($oldIndex !== $this->selectedIndex) {
            $this->onSelectionChanged();
        }
        return true;
    }

    // Handle other keys...
    return false;
}
```

### Horizontal Navigation (Buttons, Tabs)

```php
$handled = $this->handleHorizontalNavigation(
    $input,
    $this->selectedButtonIndex,   // Modified by reference
    count($this->buttons),        // Total items
);

if ($handled !== null) {
    return true;
}
```

### Text Cursor Navigation

```php
$handled = $this->handleCursorNavigation(
    $input,
    $this->cursorPosition,        // Modified by reference
    mb_strlen($this->text),       // Text length
);

if ($handled !== null) {
    return true;
}
```

### Scroll Navigation (Text Viewers)

```php
$handled = $this->handleScrollNavigation(
    $input,
    $this->scrollOffset,          // Modified by reference
    count($this->lines),          // Total lines
    $this->visibleLines,          // Visible area height
);

if ($handled !== null) {
    return true;
}
```

### Ctrl+Arrow Tab Navigation

```php
$handled = $this->handleCtrlArrowNavigation(
    $input,
    $this->activeTabIndex,        // Modified by reference
    count($this->tabs),           // Total tabs
    wrap: true,                   // Wrap around at boundaries
);

if ($handled !== null) {
    $this->switchToTab($this->activeTabIndex);
    return true;
}
```

## Complete Examples

### Simple List Component

```php
<?php

declare(strict_types=1);

namespace App\UI\Component;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Component\Concerns\HandlesInput;

final class SimpleList extends AbstractComponent
{
    use HandlesInput;

    private int $selectedIndex = 0;
    private int $scrollOffset = 0;
    private int $visibleRows = 10;

    public function __construct(
        private array $items = [],
        private ?\Closure $onSelect = null,
    ) {}

    public function handleInput(string $key): bool
    {
        $input = KeyInput::from($key);
        $oldIndex = $this->selectedIndex;

        // Vertical navigation
        $handled = $this->handleVerticalNavigation(
            $input,
            $this->selectedIndex,
            count($this->items),
            $this->visibleRows,
        );

        if ($handled !== null) {
            $this->adjustScroll();
            return true;
        }

        // Enter to select
        if ($input->is(Key::ENTER) && $this->onSelect !== null) {
            ($this->onSelect)($this->items[$this->selectedIndex], $this->selectedIndex);
            return true;
        }

        return false;
    }

    private function adjustScroll(): void
    {
        if ($this->selectedIndex < $this->scrollOffset) {
            $this->scrollOffset = $this->selectedIndex;
        } elseif ($this->selectedIndex >= $this->scrollOffset + $this->visibleRows) {
            $this->scrollOffset = $this->selectedIndex - $this->visibleRows + 1;
        }
    }
}
```

### Text Input Field

```php
<?php

declare(strict_types=1);

namespace App\UI\Component;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Component\Concerns\HandlesInput;

final class TextInput extends AbstractComponent
{
    use HandlesInput;

    private string $value = '';
    private int $cursor = 0;

    public function handleInput(string $key): bool
    {
        $input = KeyInput::from($key);

        // Cursor navigation (LEFT/RIGHT/HOME/END)
        $handled = $this->handleCursorNavigation(
            $input,
            $this->cursor,
            mb_strlen($this->value),
        );

        if ($handled !== null) {
            return true;
        }

        // Backspace
        if ($input->is(Key::BACKSPACE) && $this->cursor > 0) {
            $this->value = mb_substr($this->value, 0, $this->cursor - 1)
                         . mb_substr($this->value, $this->cursor);
            $this->cursor--;
            return true;
        }

        // Delete
        if ($input->is(Key::DELETE) && $this->cursor < mb_strlen($this->value)) {
            $this->value = mb_substr($this->value, 0, $this->cursor)
                         . mb_substr($this->value, $this->cursor + 1);
            return true;
        }

        // Printable character
        if ($input->isPrintable()) {
            $char = $input->char();
            $this->value = mb_substr($this->value, 0, $this->cursor)
                         . $char
                         . mb_substr($this->value, $this->cursor);
            $this->cursor++;
            return true;
        }

        return false;
    }
}
```

### Tab Container with Ctrl+Arrow

```php
<?php

declare(strict_types=1);

namespace App\UI\Component;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Component\Concerns\HandlesInput;

final class TabContainer extends AbstractComponent
{
    use HandlesInput;

    private int $activeTabIndex = 0;
    private array $tabs = [];

    public function handleInput(string $key): bool
    {
        $input = KeyInput::from($key);

        // Ctrl+Left/Right for tab switching
        $handled = $this->handleCtrlArrowNavigation(
            $input,
            $this->activeTabIndex,
            count($this->tabs),
            wrap: true,
        );

        if ($handled !== null) {
            $this->activateTab($this->activeTabIndex);
            return true;
        }

        // Delegate to active tab
        return $this->tabs[$this->activeTabIndex]->handleInput($key);
    }

    private function activateTab(int $index): void
    {
        // Deactivate old, activate new...
    }
}
```

### Modal with Button Navigation

```php
<?php

declare(strict_types=1);

namespace App\UI\Component;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Component\Concerns\HandlesInput;

final class ConfirmDialog extends AbstractComponent
{
    use HandlesInput;

    private int $selectedButton = 0;
    private array $buttons = ['Cancel', 'OK'];

    public function handleInput(string $key): bool
    {
        $input = KeyInput::from($key);

        // LEFT/RIGHT for button navigation
        $handled = $this->handleHorizontalNavigation(
            $input,
            $this->selectedButton,
            count($this->buttons),
        );

        if ($handled !== null) {
            return true;
        }

        return match (true) {
            // Enter or Space to confirm
            $input->is(Key::ENTER), $input->isSpace() => $this->confirm() ?? true,
            
            // Escape to cancel
            $input->is(Key::ESCAPE) => $this->cancel() ?? true,
            
            // Quick keys: 1 for Cancel, 2 for OK
            $input->isDigit() => $this->handleQuickKey($input) ?? true,
            
            default => false,
        };
    }

    private function handleQuickKey(KeyInput $input): bool
    {
        $index = (int) $input->raw - 1;
        if ($index >= 0 && $index < count($this->buttons)) {
            $this->selectedButton = $index;
            return $this->confirm();
        }
        return false;
    }
}
```

## Migration Guide

### From Raw String Comparisons

**Before:**

```php
public function handleInput(string $key): bool
{
    if ($key === 'UP') {
        $this->moveUp();
        return true;
    }
    
    if ($key === 'CTRL_R') {
        $this->refresh();
        return true;
    }
    
    return false;
}
```

**After:**

```php
public function handleInput(string $key): bool
{
    $input = KeyInput::from($key);

    return match (true) {
        $input->is(Key::UP) => $this->moveUp() ?? true,
        $input->isCtrl(Key::R) => $this->refresh() ?? true,
        default => false,
    };
}
```

### From Key::tryFrom()

**Before:**

```php
public function handleInput(string $key): bool
{
    $keyEnum = Key::tryFrom($key);

    return match ($keyEnum) {
        Key::UP => $this->moveUp() ?? true,
        Key::DOWN => $this->moveDown() ?? true,
        default => match ($key) {
            'CTRL_R' => $this->refresh() ?? true,
            default => false,
        },
    };
}
```

**After:**

```php
public function handleInput(string $key): bool
{
    $input = KeyInput::from($key);

    return match (true) {
        $input->is(Key::UP) => $this->moveUp() ?? true,
        $input->is(Key::DOWN) => $this->moveDown() ?? true,
        $input->isCtrl(Key::R) => $this->refresh() ?? true,
        default => false,
    };
}
```

## Best Practices

1. **Always create KeyInput at the start** of `handleInput()` method
2. **Use match expressions** for cleaner, more readable code
3. **Use the trait** for common navigation patterns to reduce duplication
4. **Return early** when a key is handled to prevent further processing
5. **Delegate unhandled keys** to child components when appropriate
6. **Use `isExactly()`** when you need to ensure no modifiers are pressed
7. **Group related keys** using `isAnyOf()` for cleaner conditionals

## API Reference

### KeyInput Methods

| Method                  | Description                       |
|-------------------------|-----------------------------------|
| `from(string $raw)`     | Create from raw key string        |
| `key()`                 | Get underlying Key enum (or null) |
| `is(Key $key)`          | Check key (ignoring modifiers)    |
| `isExactly(Key $key)`   | Check key with no modifiers       |
| `isCtrl(Key $key)`      | Check Ctrl+Key                    |
| `isAlt(Key $key)`       | Check Alt+Key                     |
| `isShift(Key $key)`     | Check Shift+Key                   |
| `isCtrlShift(Key $key)` | Check Ctrl+Shift+Key              |
| `hasModifiers()`        | Any modifier present?             |
| `hasCtrl()`             | Ctrl modifier present?            |
| `hasAlt()`              | Alt modifier present?             |
| `hasShift()`            | Shift modifier present?           |
| `isNavigation()`        | Is arrow/home/end/page key?       |
| `isFunctionKey()`       | Is F1-F12?                        |
| `isLetter()`            | Is A-Z?                           |
| `isDigit()`             | Is 0-9?                           |
| `isPrintable()`         | Is printable character?           |
| `char()`                | Get character (or null)           |
| `isSpace()`             | Is space key?                     |
| `isAnyOf(Key ...$keys)` | Is any of specified keys?         |

### HandlesInput Trait Methods

| Method                         | Keys Handled                            | Use Case                  |
|--------------------------------|-----------------------------------------|---------------------------|
| `handleVerticalNavigation()`   | UP, DOWN, PAGE_UP, PAGE_DOWN, HOME, END | Lists, tables             |
| `handleHorizontalNavigation()` | LEFT, RIGHT                             | Buttons, horizontal menus |
| `handleCursorNavigation()`     | LEFT, RIGHT, HOME, END                  | Text input                |
| `handleScrollNavigation()`     | UP, DOWN, PAGE_UP, PAGE_DOWN, HOME, END | Text viewers              |
| `handleCtrlArrowNavigation()`  | CTRL+LEFT, CTRL+RIGHT                   | Tab switching             |
