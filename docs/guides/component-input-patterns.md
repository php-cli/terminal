# Component Input Patterns Guide

This guide documents input handling patterns for different component types: screens, tabs, modals, and composite
layouts.

## Input Priority System

Complex components handle input in priority order:

```
1. Modal dialogs (if active) - highest priority
2. Executing state (block input during operations)
3. Global shortcuts (help, execute, etc.)
4. Navigation shortcuts (tab switching, escape)
5. Delegate to focused child component - lowest priority
```

## Screen Input Pattern

Screens typically have multiple panels and need to manage focus and shortcuts.

### Standard Screen Structure

```php
final class MyScreen implements ScreenInterface
{
    private int $focusedPanelIndex = 0;
    private ?Modal $activeModal = null;
    private bool $isExecuting = false;

    public function handleInput(string $key): bool
    {
        // Priority 1: Modal (highest)
        if ($this->activeModal !== null) {
            return $this->activeModal->handleInput($key);
        }

        // Priority 2: Block during execution
        if ($this->isExecuting) {
            return true;
        }

        // Priority 3: Global shortcuts
        if ($this->handleGlobalShortcuts($key)) {
            return true;
        }

        // Priority 4: Navigation
        if ($this->handleNavigation($key)) {
            return true;
        }

        // Priority 5: Delegate to focused panel
        return $this->delegateToFocusedPanel($key);
    }
}
```

### Global Shortcuts Method

```php
private function handleGlobalShortcuts(string $key): bool
{
    $input = KeyInput::from($key);

    return match (true) {
        $input->is(Key::F1) => $this->showHelpModal() ?? true,
        $input->isCtrl(Key::E) => $this->handleExecute() ?? true,
        $input->isCtrl(Key::R) => $this->handleRefresh() ?? true,
        default => false,
    };
}
```

### Navigation Method

```php
private function handleNavigation(string $key): bool
{
    $input = KeyInput::from($key);

    return match (true) {
        $input->is(Key::TAB) => $this->switchPanel() ?? true,
        $input->is(Key::ESCAPE) => $this->handleEscape(),
        default => false,
    };
}

private function handleEscape(): bool
{
    // If right panel focused, go back to left
    if ($this->focusedPanelIndex === 1) {
        $this->switchToLeftPanel();
        return true;
    }
    
    // If at root, exit screen
    return false;
}
```

### Delegation Method

```php
private function delegateToFocusedPanel(string $key): bool
{
    return match ($this->focusedPanelIndex) {
        0 => $this->leftPanel->handleInput($key),
        1 => $this->rightPanel->handleInput($key),
        default => false,
    };
}
```

## Tab Input Pattern

Tabs within a TabContainer need to handle their own shortcuts while respecting container navigation.

### Tab Structure

```php
final class MyTab extends AbstractTab
{
    private int $focusedPanelIndex = 0;

    #[\Override]
    public function handleInput(string $key): bool
    {
        $input = KeyInput::from($key);

        // Tab-specific shortcuts
        if ($input->isCtrl(Key::R)) {
            $this->refresh();
            return true;
        }

        // Panel switching within tab
        if ($input->is(Key::TAB)) {
            $this->focusedPanelIndex = ($this->focusedPanelIndex + 1) % 2;
            $this->updateFocus();
            return true;
        }

        // Delegate to focused panel
        return $this->focusedPanelIndex === 0
            ? $this->leftPanel->handleInput($key)
            : $this->rightPanel->handleInput($key);
    }

    #[\Override]
    public function getShortcuts(): array
    {
        return [
            'Tab' => 'Switch Panel',
            'Ctrl+R' => 'Refresh',
        ];
    }
}
```

### Tab with Execution State

```php
final class ScriptsTab extends AbstractTab
{
    private bool $isExecuting = false;
    private ?Process $runningProcess = null;

    public function handleInput(string $key): bool
    {
        $input = KeyInput::from($key);

        // Allow cancellation during execution
        if ($this->isExecuting && $input->isCtrl(Key::C)) {
            $this->cancelExecution();
            return true;
        }

        // Block other input during execution
        if ($this->isExecuting) {
            return true;
        }

        // Normal input handling...
        return match (true) {
            $input->isCtrl(Key::R) => $this->refresh() ?? true,
            $input->is(Key::TAB) => $this->switchPanel() ?? true,
            default => $this->delegateToFocusedPanel($key),
        };
    }
}
```

## Modal Input Pattern

Modals capture all input and handle button navigation.

### Modal Structure

```php
final class Modal extends AbstractComponent
{
    private array $buttons = [];
    private int $selectedButtonIndex = 0;

    public function handleInput(string $key): bool
    {
        if (empty($this->buttons)) {
            return false;
        }

        $input = KeyInput::from($key);
        $buttonLabels = \array_keys($this->buttons);

        return match (true) {
            // Button navigation
            $input->is(Key::LEFT) => $this->selectPreviousButton(),
            $input->is(Key::RIGHT), $input->is(Key::TAB) => $this->selectNextButton(),
            
            // Activation
            $input->is(Key::ENTER), $input->isSpace() => $this->activateSelectedButton(),
            
            // Escape activates last button (usually Cancel)
            $input->is(Key::ESCAPE) => $this->activateLastButton(),
            
            // Quick access (1-9)
            $input->isDigit() => $this->handleDigitKey($input),
            
            default => false,
        };
    }

    private function selectPreviousButton(): bool
    {
        if ($this->selectedButtonIndex > 0) {
            $this->selectedButtonIndex--;
        }
        return true;
    }

    private function selectNextButton(): bool
    {
        if ($this->selectedButtonIndex < count($this->buttons) - 1) {
            $this->selectedButtonIndex++;
        }
        return true;
    }
}
```

## List/Table Input Pattern

Lists and tables use the `HandlesInput` trait for navigation.

### List with Trait

```php
final class ListComponent extends AbstractComponent
{
    use HandlesInput;

    private int $selectedIndex = 0;
    private int $scrollOffset = 0;
    private int $visibleRows = 0;

    public function handleInput(string $key): bool
    {
        if (!$this->isFocused() || empty($this->items)) {
            return false;
        }

        $input = KeyInput::from($key);
        $oldIndex = $this->selectedIndex;

        // Use trait for vertical navigation
        $handled = $this->handleVerticalNavigation(
            $input,
            $this->selectedIndex,
            count($this->items),
            $this->visibleRows,
        );

        if ($handled !== null) {
            $this->adjustScroll();
            if ($oldIndex !== $this->selectedIndex) {
                $this->triggerOnChange();
            }
            return true;
        }

        // Enter to select
        if ($input->is(Key::ENTER)) {
            $this->triggerOnSelect();
            return true;
        }

        return false;
    }
}
```

## Text Input Pattern

Text fields handle cursor movement and character input.

### TextField Structure

```php
final class TextField extends FormField
{
    private int $cursorPosition = 0;

    public function handleInput(string $key): bool
    {
        $input = KeyInput::from($key);
        $valueStr = (string) $this->value;

        return match (true) {
            // Cursor movement
            $input->is(Key::LEFT) => $this->moveCursorLeft(),
            $input->is(Key::RIGHT) => $this->moveCursorRight($valueStr),
            $input->is(Key::HOME) => $this->moveCursorToStart(),
            $input->is(Key::END) => $this->moveCursorToEnd($valueStr),
            
            // Deletion
            $input->is(Key::BACKSPACE) => $this->handleBackspace($valueStr),
            $input->is(Key::DELETE) => $this->handleDelete($valueStr),
            
            // Character input (must be last)
            default => $this->handleCharacterInput($key, $valueStr),
        };
    }

    private function handleCharacterInput(string $key, string $valueStr): bool
    {
        // Only accept printable characters
        if (\mb_strlen($key) === 1 && \ord($key) >= 32 && \ord($key) < 127) {
            $before = \mb_substr($valueStr, 0, $this->cursorPosition);
            $after = \mb_substr($valueStr, $this->cursorPosition);
            $this->value = $before . $key . $after;
            $this->cursorPosition++;
            return true;
        }
        return false;
    }
}
```

## Dropdown/Menu Input Pattern

Menus handle selection and hotkeys.

### MenuDropdown Structure

```php
final class MenuDropdown extends AbstractComponent
{
    private int $selectedIndex = 0;

    public function handleInput(string $key): bool
    {
        $input = KeyInput::from($key);

        return match (true) {
            // Navigation
            $input->is(Key::UP) => $this->moveSelection(-1) ?? true,
            $input->is(Key::DOWN) => $this->moveSelection(1) ?? true,
            
            // Selection
            $input->is(Key::ENTER), $input->isSpace() => $this->selectCurrentItem() ?? true,
            
            // Close
            $input->is(Key::ESCAPE) => $this->close() ?? true,
            
            // Hotkey (single printable character)
            $input->isPrintable() => $this->handleHotkey(\mb_strtolower($input->raw)),
            
            default => false,
        };
    }

    private function handleHotkey(string $key): bool
    {
        foreach ($this->items as $index => $item) {
            if ($item->getHotkey() === $key && !$item->isSeparator()) {
                $this->selectedIndex = $index;
                $this->selectCurrentItem();
                return true;
            }
        }
        return false;
    }
}
```

## TabContainer Input Pattern

Tab containers handle Ctrl+Arrow for switching while delegating to active tab.

### TabContainer Structure

```php
final class TabContainer extends AbstractComponent
{
    use HandlesInput;

    private int $activeTabIndex = 0;

    public function handleInput(string $key): bool
    {
        $input = KeyInput::from($key);

        // Tab navigation with Ctrl+Left/Right
        if ($input->isCtrl(Key::LEFT)) {
            $this->previousTab();
            return true;
        }

        if ($input->isCtrl(Key::RIGHT)) {
            $this->nextTab();
            return true;
        }

        // Delegate to active tab
        return $this->getActiveTab()?->handleInput($key) ?? false;
    }
}
```

## Form Input Pattern

Forms handle field navigation and submission.

### FormComponent Structure

```php
final class FormComponent extends AbstractComponent
{
    private array $fields = [];
    private int $focusedFieldIndex = 0;

    public function handleInput(string $key): bool
    {
        if (empty($this->fields)) {
            return false;
        }

        $input = KeyInput::from($key);

        return match (true) {
            // Field navigation
            $input->is(Key::UP) => $this->focusPreviousField(),
            $input->is(Key::DOWN), $input->is(Key::TAB) => $this->focusNextField(),
            
            // Submit
            $input->is(Key::ENTER) => $this->handleSubmit(),
            
            // Cancel
            $input->is(Key::ESCAPE) => $this->handleCancel(),
            
            // Delegate to focused field
            default => $this->fields[$this->focusedFieldIndex]->handleInput($key),
        };
    }
}
```

## Pattern Summary

| Component Type | Key Patterns                            | Delegation          |
|----------------|-----------------------------------------|---------------------|
| Screen         | Global → Navigation → Delegate          | To focused panel    |
| Tab            | Tab shortcuts → Panel switch → Delegate | To focused panel    |
| Modal          | Navigation → Activation → Quick keys    | None (captures all) |
| List/Table     | Vertical nav (trait) → Enter            | None                |
| TextField      | Cursor nav → Delete → Chars             | None                |
| Dropdown       | Nav → Select → Escape → Hotkey          | None                |
| TabContainer   | Ctrl+Arrow → Delegate                   | To active tab       |
| Form           | Field nav → Submit → Delegate           | To focused field    |

## Anti-Patterns to Avoid

### ❌ Raw String Comparisons

```php
// BAD
if ($key === 'CTRL_R') { ... }
if ($key === 'UP') { ... }
```

### ❌ Using Key::tryFrom in UI Components

```php
// BAD
$keyEnum = Key::tryFrom($key);
if ($keyEnum === Key::UP) { ... }
```

### ❌ Switch Statements

```php
// BAD
switch ($key) {
    case 'UP': ...
    case 'DOWN': ...
}
```

### ✅ Correct Pattern

```php
// GOOD
$input = KeyInput::from($key);
return match (true) {
    $input->is(Key::UP) => $this->moveUp() ?? true,
    $input->isCtrl(Key::R) => $this->refresh() ?? true,
    default => false,
};
```

## See Also

- [Keyboard Input Handling](keyboard-input-handling.md) - `KeyInput` API reference
- [Keyboard Architecture](keyboard-architecture.md) - Key binding system
