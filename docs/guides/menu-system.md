# Menu System Guide

This guide documents the polymorphic menu item system used for building dropdown menus, menu bars, and navigation in
Commander applications.

## Architecture Overview

The menu system uses a polymorphic class hierarchy instead of string-based type discrimination. Each menu item type is a
separate class with only the properties relevant to its purpose.

```
MenuItemInterface (contract)
    │
    └── AbstractMenuItem (shared implementation)
            │
            ├── ScreenMenuItem (navigate to screen)
            ├── ActionMenuItem (execute closure)
            ├── SubmenuMenuItem (nested menu)
            └── SeparatorMenuItem (visual divider)
```

## Core Interfaces

### MenuItemInterface

The contract all menu items must implement:

```php
interface MenuItemInterface
{
    public function getLabel(): string;
    public function getHotkey(): ?string;
    public function isSeparator(): bool;
}
```

### AbstractMenuItem

Base class providing common functionality:

```php
abstract readonly class AbstractMenuItem implements MenuItemInterface
{
    public function __construct(
        protected string $label,
        protected ?string $hotkey = null,
    ) {}

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getHotkey(): ?string
    {
        // Returns explicit hotkey (lowercase) or first char of label
        if ($this->hotkey !== null) {
            return \mb_strtolower($this->hotkey);
        }

        if ($this->isSeparator()) {
            return null;
        }

        return \mb_strtolower(\mb_substr($this->label, 0, 1));
    }

    public function isSeparator(): bool
    {
        return false;
    }
}
```

## Menu Item Types

### ScreenMenuItem

Navigates to a registered screen when selected:

```php
use Butschster\Commander\UI\Menu\ScreenMenuItem;

// Using static factory (preferred)
$item = ScreenMenuItem::create('File Browser', 'files.browser', 'f');

// Using constructor
$item = new ScreenMenuItem(
    label: 'File Browser',
    screenName: 'files.browser',
    hotkey: 'f',
);

// Access properties
$item->getLabel();    // "File Browser"
$item->screenName;    // "files.browser"
$item->getHotkey();   // "f"
$item->isSeparator(); // false
```

### ActionMenuItem

Executes a closure when selected:

```php
use Butschster\Commander\UI\Menu\ActionMenuItem;

// With explicit hotkey
$item = ActionMenuItem::create('Save', function () {
    $this->saveDocument();
}, 's');

// Accepts any callable (converted to Closure internally)
$item = ActionMenuItem::create('Refresh', $this->handleRefresh(...));

// Access properties
$item->getLabel();    // "Save"
$item->action;        // \Closure instance
$item->getHotkey();   // "s"

// Execute the action
($item->action)();
```

### SubmenuMenuItem

Contains nested menu items for hierarchical menus:

```php
use Butschster\Commander\UI\Menu\SubmenuMenuItem;

$item = SubmenuMenuItem::create('Export', [
    ActionMenuItem::create('As JSON', fn() => $this->exportJson()),
    ActionMenuItem::create('As CSV', fn() => $this->exportCsv()),
    SeparatorMenuItem::create(),
    ActionMenuItem::create('As PDF', fn() => $this->exportPdf()),
], 'e');

// Access properties
$item->getLabel();    // "Export"
$item->items;         // array<MenuItemInterface>
$item->getHotkey();   // "e"
```

### SeparatorMenuItem

Visual divider between menu items (cannot be selected):

```php
use Butschster\Commander\UI\Menu\SeparatorMenuItem;

$item = SeparatorMenuItem::create();

// Properties
$item->getLabel();    // "─────────"
$item->getHotkey();   // null (always)
$item->isSeparator(); // true (always)
```

## Building Menus

### MenuDefinition

Represents a top-level menu with its dropdown items:

```php
use Butschster\Commander\UI\Menu\MenuDefinition;
use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;

$fileMenu = new MenuDefinition(
    label: 'Files',
    fkey: KeyCombination::fkey(1), // F1
    items: [
        ScreenMenuItem::create('Browser', 'files.browser'),
        ScreenMenuItem::create('Recent', 'files.recent'),
        SeparatorMenuItem::create(),
        ActionMenuItem::create('Quit', fn() => exit(), 'q'),
    ],
    priority: 0, // Lower = further left in menu bar
);

// Get first selectable item (skips separators)
$first = $fileMenu->getFirstItem(); // ScreenMenuItem or null
```

### MenuBuilder

Automatically builds menus from registered screens:

```php
use Butschster\Commander\UI\Menu\MenuBuilder;

$builder = new MenuBuilder($screenRegistry, $keyBindings);
$menus = $builder->build();

// Add custom items to existing category
$builder->addItem('files', ActionMenuItem::create('Import', fn() => ...));

// Add separator
$builder->addSeparator('files');
```

## Handling Menu Selection

### Pattern Matching with instanceof

Use `match` expressions for type-safe dispatch:

```php
private function handleMenuItemSelected(MenuItemInterface $item): void
{
    match (true) {
        $item instanceof ScreenMenuItem => $this->navigateToScreen($item->screenName),
        $item instanceof ActionMenuItem => ($item->action)(),
        $item instanceof SubmenuMenuItem => $this->openSubmenu($item->items),
        default => null, // SeparatorMenuItem or unknown
    };
}
```

### In MenuDropdown Callbacks

```php
$dropdown->onSelect(function (MenuItemInterface $item): void {
    if ($item instanceof ScreenMenuItem) {
        $this->screenManager->pushScreen($item->screenName);
    } elseif ($item instanceof ActionMenuItem) {
        ($item->action)();
    }
});
```

## Rendering Menus

### MenuDropdown Component

Renders a dropdown menu with navigation support:

```php
use Butschster\Commander\UI\Component\Layout\MenuDropdown;

$dropdown = new MenuDropdown(
    items: $menuItems,
    menuX: 10,
    menuY: 2,
);

// Set callbacks
$dropdown->onSelect(function (MenuItemInterface $item): void {
    $this->handleSelection($item);
});

$dropdown->onClose(function (): void {
    $this->closeDropdown();
});

// Handle input
$dropdown->handleInput('DOWN');  // Navigate
$dropdown->handleInput('ENTER'); // Select
$dropdown->handleInput('p');     // Hotkey selection
```

### Custom Item Rendering

When rendering items, check types for special display:

```php
private function renderItem(MenuItemInterface $item, bool $isSelected): void
{
    if ($item->isSeparator()) {
        $this->drawHorizontalLine();
        return;
    }

    $label = $item->getLabel();
    $hotkey = $item->getHotkey();

    $this->drawItemBackground($isSelected);
    $this->writeText($label);

    if ($hotkey !== null) {
        $this->writeHotkeyHint(\strtoupper($hotkey));
    }

    // Show submenu indicator
    if ($item instanceof SubmenuMenuItem) {
        $this->writeText('►');
    }
}
```

## Testing Menu Items

### Unit Testing

```php
use Butschster\Commander\UI\Menu\ActionMenuItem;
use Butschster\Commander\UI\Menu\MenuItemInterface;
use Butschster\Commander\UI\Menu\ScreenMenuItem;
use Butschster\Commander\UI\Menu\SeparatorMenuItem;

final class MenuItemTest extends TestCase
{
    public function testScreenMenuItem(): void
    {
        $item = ScreenMenuItem::create('Files', 'files.list', 'f');

        $this->assertSame('Files', $item->getLabel());
        $this->assertSame('files.list', $item->screenName);
        $this->assertSame('f', $item->getHotkey());
        $this->assertFalse($item->isSeparator());
    }

    public function testActionMenuItemExecutesClosure(): void
    {
        $executed = false;
        $item = ActionMenuItem::create('Test', function () use (&$executed): void {
            $executed = true;
        });

        ($item->action)();

        $this->assertTrue($executed);
    }

    public function testHotkeyFallsBackToFirstChar(): void
    {
        $item = ScreenMenuItem::create('Settings', 'settings.main');

        $this->assertSame('s', $item->getHotkey());
    }

    public function testSeparatorHasNoHotkey(): void
    {
        $item = SeparatorMenuItem::create();

        $this->assertNull($item->getHotkey());
        $this->assertTrue($item->isSeparator());
    }
}
```

### Testing with MenuDropdown

```php
public function testDropdownNavigationSkipsSeparators(): void
{
    $items = [
        ActionMenuItem::create('First', static fn() => null),
        SeparatorMenuItem::create(),
        ActionMenuItem::create('Third', static fn() => null),
    ];
    $dropdown = new MenuDropdown($items, 0, 1);

    $receivedItem = null;
    $dropdown->onSelect(function (MenuItemInterface $item) use (&$receivedItem): void {
        $receivedItem = $item;
    });

    $dropdown->setFocused(true);
    $dropdown->handleInput('DOWN');
    $dropdown->handleInput('ENTER');

    // Should skip separator and select "Third"
    $this->assertSame('Third', $receivedItem->getLabel());
}
```

## Migration from Old MenuItem

### Before (String-based types)

```php
// Old pattern - avoid
$item = MenuItem::screen('Files', 'files.list');
$item = MenuItem::action('Save', fn() => ...);
$item = MenuItem::separator();

if ($item->isScreen()) {
    $this->navigate($item->screenName);
} elseif ($item->isAction()) {
    ($item->action)();
}
```

### After (Polymorphic classes)

```php
// New pattern - use this
$item = ScreenMenuItem::create('Files', 'files.list');
$item = ActionMenuItem::create('Save', fn() => ...);
$item = SeparatorMenuItem::create();

match (true) {
    $item instanceof ScreenMenuItem => $this->navigate($item->screenName),
    $item instanceof ActionMenuItem => ($item->action)(),
    default => null,
};
```

### Key Differences

| Aspect          | Old                  | New                               |
|-----------------|----------------------|-----------------------------------|
| Type checking   | `$item->isScreen()`  | `$item instanceof ScreenMenuItem` |
| Label access    | `$item->label`       | `$item->getLabel()`               |
| Hotkey access   | `$item->hotkey`      | `$item->getHotkey()`              |
| Factory methods | `MenuItem::screen()` | `ScreenMenuItem::create()`        |
| Type hints      | `MenuItem`           | `MenuItemInterface`               |

## Best Practices

1. **Use static factories**: Prefer `ScreenMenuItem::create()` over `new ScreenMenuItem()` for consistency.

2. **Type hint with interface**: Use `MenuItemInterface` in method signatures for flexibility.

3. **Pattern matching**: Use `match (true)` with `instanceof` for clean type dispatch.

4. **Explicit hotkeys**: Set hotkeys explicitly when the first character would conflict.

5. **Immutable items**: All menu items are `readonly` — create new instances instead of modifying.

## Class Reference

| Class               | Purpose           | Key Properties                               |
|---------------------|-------------------|----------------------------------------------|
| `MenuItemInterface` | Contract          | `getLabel()`, `getHotkey()`, `isSeparator()` |
| `AbstractMenuItem`  | Base class        | `label`, `hotkey`                            |
| `ScreenMenuItem`    | Screen navigation | `screenName`                                 |
| `ActionMenuItem`    | Closure execution | `action`                                     |
| `SubmenuMenuItem`   | Nested menus      | `items`                                      |
| `SeparatorMenuItem` | Visual divider    | (none)                                       |
| `MenuDefinition`    | Top-level menu    | `label`, `fkey`, `items`, `priority`         |
| `MenuBuilder`       | Auto-build menus  | `build()`, `addItem()`                       |
| `MenuDropdown`      | Dropdown UI       | `onSelect()`, `onClose()`                    |
| `MenuSystem`        | Complete menu bar | `render()`, `handleInput()`                  |
