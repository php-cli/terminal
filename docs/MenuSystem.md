# Menu System Documentation

## Overview

The Menu System provides a complete Midnight Commander-style menu bar with dropdown menus, keyboard navigation, and
automatic screen integration. It consists of:

- **MenuSystem** - Top-level component managing the menu bar and active dropdown
- **MenuDropdown** - Popup dropdown menu with item selection
- **MenuBuilder** - Automatic menu generation from ScreenRegistry
- **MenuDefinition** - Menu category with items
- **MenuItem** - Individual menu item (screen, action, separator, submenu)

## Architecture

```
MenuSystem (Top Bar + Dropdown Manager)
  ├─ MenuBar Rendering (F-keys + Labels)
  ├─ MenuDropdown (Active popup)
  │   └─ MenuItem List (with selection)
  └─ ScreenRegistry Integration
```

---

## Quick Start

### 1. Automatic Menu Generation

The easiest way to create a menu system is to use `MenuBuilder` with `ScreenRegistry`:

```php
use Butschster\Commander\UI\Menu\MenuBuilder;
use Butschster\Commander\UI\Screen\ScreenRegistry;

// Create registry and register screens
$registry = new ScreenRegistry();
$registry->register(new CommandsScreen(...));
$registry->register(new FileBrowserScreen(...));
$registry->register(new ComposerManagerScreen(...));

// Build menu system automatically
$menuBuilder = new MenuBuilder($registry);
$menuSystem = $menuBuilder->build();

// Use in Application
$app = new Application();
$app->setMenuSystem($menuSystem);
```

This automatically:

- Groups screens by category (from ScreenMetadata)
- Assigns F-keys to categories
- Creates menu items for each screen
- Adds a Quit menu with F10

### 2. Manual Menu Creation

For custom menus, build menu definitions manually:

```php
use Butschster\Commander\UI\Menu\MenuDefinition;
use Butschster\Commander\UI\Menu\MenuItem;

$menus = [
    'files' => new MenuDefinition(
        label: 'Files',
        fkey: 'F3',
        items: [
            MenuItem::screen('Browse Files', 'file_browser', 'b'),
            MenuItem::separator(),
            MenuItem::action('Refresh', fn() => $this->refresh(), 'r'),
        ],
        priority: 0
    ),
    
    'tools' => new MenuDefinition(
        label: 'Tools',
        fkey: 'F2',
        items: [
            MenuItem::screen('Commands', 'command_browser', 'c'),
            MenuItem::screen('Composer', 'composer_manager', 'm'),
        ],
        priority: 1
    ),
    
    'quit' => new MenuDefinition(
        label: 'Quit',
        fkey: 'F10',
        items: [
            MenuItem::action('Quit', fn() => $app->quit(), 'q'),
        ],
        priority: 999
    ),
];

$menuSystem = new MenuSystem($menus, $registry, $screenManager);
```

---

## MenuSystem API

### Constructor

```php
public function __construct(
    array $menus,                      // Menu definitions
    ScreenRegistry $registry,           // Screen registry
    ScreenManager $screenManager,       // Screen manager
)
```

### Methods

#### `onQuit(callable $callback): void`

Set callback for when quit is requested (typically closes application).

```php
$menuSystem->onQuit(function() {
    $this->cleanup();
    exit(0);
});
```

#### `render(Renderer $renderer, int $x, int $y, int $width, int $height): void`

Renders the menu bar (top line only).

```php
// In screen render method
$menuSystem->render($renderer, 0, 0, $width, 1);
```

#### `renderDropdown(Renderer $renderer, int $x, int $y, int $width, int $height): void`

Renders the active dropdown menu (call AFTER screen content).

```php
// In screen render method
$this->screen->render($renderer, 0, 1, $width, $height - 2);
$menuSystem->renderDropdown($renderer, 0, 1, $width, $height - 2);
```

#### `handleInput(string $key): bool`

Handles F-key presses and delegates to active dropdown.

```php
// In screen handleInput method
if ($menuSystem->handleInput($key)) {
    return true;
}
```

#### `isDropdownOpen(): bool`

Check if a dropdown menu is currently active.

```php
if (!$menuSystem->isDropdownOpen()) {
    // Allow screen to handle input
}
```

#### `closeDropdown(): void`

Close any open dropdown menu.

```php
$menuSystem->closeDropdown();
```

---

## MenuDropdown API

MenuDropdown is created automatically by MenuSystem but can be used standalone.

### Constructor

```php
public function __construct(
    array $items,      // Menu items to display
    int $menuX,        // X position for dropdown
    int $menuY,        // Y position for dropdown
)
```

### Methods

#### `onSelect(callable $callback): void`

Set callback when item is selected.

```php
$dropdown->onSelect(function(MenuItem $item) {
    echo "Selected: {$item->label}\n";
});
```

#### `onClose(callable $callback): void`

Set callback when dropdown closes.

```php
$dropdown->onClose(function() {
    $this->restoreFocus();
});
```

### Keyboard Navigation

- **↑/↓** - Navigate items (skips separators)
- **Enter/Space** - Select current item
- **Escape** - Close dropdown
- **Letter** - Jump to item by hotkey

---

## MenuBuilder API

### Constructor

```php
public function __construct(
    ScreenRegistry $registry
)
```

### Static Factory

```php
MenuBuilder::fromRegistry(ScreenRegistry $registry): self
```

### Methods

#### `withFKeys(array $map): self`

Customize F-key assignments for categories.

```php
$menuBuilder = new MenuBuilder($registry)
    ->withFKeys([
        'files' => 'F3',
        'tools' => 'F2',
        'system' => 'F4',
    ]);
```

#### `build(): array<string, MenuDefinition>`

Generate menu definitions from registry.

```php
$menus = $menuBuilder->build();
```

#### `addItem(string $category, MenuItem $item): self`

Add custom menu item to category.

```php
$menuBuilder
    ->addItem('tools', MenuItem::action('Clear Cache', fn() => $this->clearCache()))
    ->addSeparator('tools')
    ->addItem('tools', MenuItem::screen('Settings', 'settings_screen'));
```

#### `addSeparator(string $category): self`

Add separator line to category.

```php
$menuBuilder->addSeparator('files');
```

---

## MenuDefinition API

Represents a top-level menu category with dropdown items.

### Constructor

```php
public function __construct(
    string $label,           // Menu label (e.g., "Files")
    ?string $fkey,           // F-key to activate (e.g., 'F3')
    array $items,            // Dropdown menu items
    int $priority = 100,     // Sort order (lower = left)
)
```

### Static Factory

#### `fromScreens(...): self`

Create menu from ScreenMetadata array.

```php
$menu = MenuDefinition::fromScreens(
    label: 'Tools',
    fkey: 'F2',
    screens: $registry->getScreensByCategory('tools'),
    priority: 1
);
```

### Methods

#### `getFirstItem(): ?MenuItem`

Get first non-separator item.

```php
$firstItem = $menu->getFirstItem();
```

---

## MenuItem API

Represents a single menu item in a dropdown.

### Types

- **TYPE_SCREEN** - Navigate to screen
- **TYPE_ACTION** - Execute callback
- **TYPE_SEPARATOR** - Visual separator line
- **TYPE_SUBMENU** - Nested menu (future)

### Static Factories

#### `screen(string $label, string $screenName, ?string $hotkey = null): self`

Create screen navigation item.

```php
$item = MenuItem::screen('Browse Files', 'file_browser', 'b');
```

#### `action(string $label, callable $action, ?string $hotkey = null): self`

Create action item.

```php
$item = MenuItem::action('Refresh', fn() => $this->refresh(), 'r');
```

#### `separator(): self`

Create separator line.

```php
$item = MenuItem::separator();
```

#### `submenu(string $label, array $items, ?string $hotkey = null): self`

Create submenu (placeholder for future nested menus).

```php
$item = MenuItem::submenu('Recent', [
    MenuItem::screen('File 1', 'file1'),
    MenuItem::screen('File 2', 'file2'),
]);
```

### Methods

#### `isSeparator(): bool`

Check if item is a separator.

#### `isScreen(): bool`

Check if item navigates to screen.

#### `isAction(): bool`

Check if item executes action.

#### `isSubmenu(): bool`

Check if item has submenu.

#### `getHotkey(): ?string`

Get hotkey for quick access (first letter if not explicitly set).

```php
$hotkey = $item->getHotkey(); // Returns lowercase letter
```

---

## Visual Appearance

### Menu Bar (Top Line)

```
F1Help  F2Tools  F3Files  F9Menu                    F10Quit
```

- **F-keys**: Yellow text on cyan background
- **Labels**: Black text on cyan background
- **Quit**: Right-aligned

### Dropdown Menu

```
┌───────────────────────┐
│ Browse Files       B  │ ← Normal item
│ Edit File          E  │
├───────────────────────┤ ← Separator
│ Refresh            R  │ ← Selected item (white bg)
│ Settings           S  │
└───────────────────────┘
```

- **Normal**: Cyan background, black text
- **Selected**: White background, black text, bold
- **Hotkeys**: Yellow text (right-aligned)
- **Separator**: Gray horizontal line
- **Shadow**: Black shadow on right/bottom edges

---

## Usage Patterns

### Pattern 1: Screen-Based Menu (Automatic)

```php
// 1. Register screens with metadata
$registry = new ScreenRegistry();
$registry->register(new CommandsScreen(...));
$registry->register(new FileBrowserScreen(...));

// 2. Build menu automatically
$menuBuilder = new MenuBuilder($registry);
$menus = $menuBuilder->build();

// 3. Create menu system
$menuSystem = new MenuSystem($menus, $registry, $screenManager);
```

### Pattern 2: Custom Menu with Actions

```php
$menus = [
    'edit' => new MenuDefinition(
        'Edit',
        'F4',
        [
            MenuItem::action('Copy', fn() => $this->copy(), 'c'),
            MenuItem::action('Paste', fn() => $this->paste(), 'p'),
            MenuItem::separator(),
            MenuItem::action('Select All', fn() => $this->selectAll(), 'a'),
        ],
        priority: 2
    ),
];

$menuSystem = new MenuSystem($menus, $registry, $screenManager);
```

### Pattern 3: Mixed Screen + Action Menu

```php
$menus = [
    'files' => new MenuDefinition(
        'Files',
        'F3',
        [
            MenuItem::screen('Browse', 'file_browser', 'b'),
            MenuItem::separator(),
            MenuItem::action('New File', fn() => $this->newFile(), 'n'),
            MenuItem::action('Open', fn() => $this->openFile(), 'o'),
            MenuItem::action('Save', fn() => $this->saveFile(), 's'),
        ],
        priority: 0
    ),
];
```

### Pattern 4: Integration with Application

```php
final class Application
{
    private MenuSystem $menuSystem;
    
    public function setMenuSystem(MenuSystem $menuSystem): void
    {
        $this->menuSystem = $menuSystem;
        
        // Wire up quit callback
        $this->menuSystem->onQuit(function() {
            $this->requestQuit();
        });
    }
    
    private function renderFrame(): void
    {
        $this->renderer->beginFrame();
        
        // 1. Render menu bar (top line)
        $this->menuSystem->render(
            $this->renderer, 
            0, 0, 
            $this->width, 1
        );
        
        // 2. Render current screen
        $currentScreen = $this->screenManager->getCurrentScreen();
        if ($currentScreen !== null) {
            $currentScreen->render(
                $this->renderer, 
                0, 1, 
                $this->width, 
                $this->height - 1
            );
        }
        
        // 3. Render dropdown overlay (AFTER screen)
        $this->menuSystem->renderDropdown(
            $this->renderer, 
            0, 1, 
            $this->width, 
            $this->height - 1
        );
        
        $this->renderer->endFrame();
    }
    
    private function handleInput(string $key): void
    {
        // 1. Try menu system first (F-keys, dropdown navigation)
        if ($this->menuSystem->handleInput($key)) {
            return;
        }
        
        // 2. Delegate to current screen
        $currentScreen = $this->screenManager->getCurrentScreen();
        if ($currentScreen !== null && $currentScreen->handleInput($key)) {
            return;
        }
        
        // 3. Handle global keys
        if ($key === 'CTRL_C') {
            $this->requestQuit();
        }
    }
}
```

---

## Advanced Features

### Custom F-Key Mapping

```php
$menuBuilder = new MenuBuilder($registry)
    ->withFKeys([
        'help' => 'F1',     // Standard
        'tools' => 'F2',    // Custom
        'files' => 'F9',    // Custom
        'system' => 'F4',   // Custom
    ]);
```

### Dynamic Menu Updates

```php
// Add item at runtime
$menuBuilder->addItem('files', MenuItem::action(
    'Export Log',
    fn() => $this->exportLog()
));

// Rebuild menu system
$menus = $menuBuilder->build();
$menuSystem = new MenuSystem($menus, $registry, $screenManager);
```

### Conditional Menu Items

```php
$items = [
    MenuItem::screen('Browse Files', 'file_browser'),
];

// Add conditional items
if ($user->hasPermission('admin')) {
    $items[] = MenuItem::separator();
    $items[] = MenuItem::action('Admin Panel', fn() => $this->openAdmin());
}

$menu = new MenuDefinition('Files', 'F3', $items);
```

### Contextual Actions

```php
MenuItem::action('Save', function() {
    $currentScreen = $this->screenManager->getCurrentScreen();
    
    if ($currentScreen instanceof FileBrowserScreen) {
        $currentScreen->saveCurrentFile();
    } else {
        $this->showError('No file to save');
    }
})
```

---

## Integration with Screens

### Screen with Menu Integration

```php
final class MyScreen implements ScreenInterface
{
    public function render(Renderer $renderer): void
    {
        $size = $renderer->getSize();
        
        // Don't render menu bar here - Application does it
        // Just render screen content
        $this->content->render(
            $renderer, 
            0, 1,  // Start below menu bar
            $size['width'], 
            $size['height'] - 2  // Reserve space for menu + status
        );
    }
    
    public function handleInput(string $key): bool
    {
        // Menu system handles F-keys automatically
        // Just handle screen-specific keys
        return $this->content->handleInput($key);
    }
}
```

### ScreenMetadata for Auto-Menu

```php
final class CommandsScreen implements ScreenInterface
{
    public static function getMetadata(): ScreenMetadata
    {
        return new ScreenMetadata(
            name: 'command_browser',
            title: 'Command Browser',
            description: 'Browse and execute Symfony commands',
            category: 'tools',        // Used for menu grouping
            fkey: null,               // Optional F-key
            displayText: 'Commands'   // Used in menu label
        );
    }
}
```

---

## Keyboard Navigation

### Menu Bar

- **F1-F12** - Open corresponding menu dropdown
- **F10** - Quit (special handling - executes immediately)

### Dropdown Menu

- **↑/↓** - Navigate items (automatically skips separators)
- **Enter/Space** - Select current item
- **Escape** - Close dropdown
- **Letter** - Quick access by hotkey (first letter of item)
- **1-9** - Quick access by number (button index)

---

## Performance Considerations

### Rendering

- Menu bar is static (only changes when menus change)
- Dropdown renders only when open
- Shadow effect uses minimal ANSI codes
- Automatic scrollbar for long menus (>15 items)

### Memory

- Menus built once at startup
- Dropdown created/destroyed on open/close
- MenuItem closures captured efficiently

### Best Practices

```php
// ✅ Good: Build once
$menus = $menuBuilder->build();
$menuSystem = new MenuSystem($menus, $registry, $screenManager);

// ❌ Bad: Rebuild every frame
public function render(...) {
    $menus = $menuBuilder->build();  // Expensive!
}

// ✅ Good: Check dropdown state before handling input
if (!$menuSystem->isDropdownOpen()) {
    // Screen can handle input
}

// ❌ Bad: Always delegate to screen
$screen->handleInput($key);  // Might conflict with dropdown
```

---

## Troubleshooting

### Issue: Menu items not appearing

**Solution:** Ensure screens are registered in ScreenRegistry before building menu:

```php
$registry->register($screen);  // Must be before build()
$menus = $menuBuilder->build();
```

### Issue: F-key not opening menu

**Solution:** Check F-key mapping in MenuBuilder:

```php
$menuBuilder->withFKeys([
    'files' => 'F3',  // Explicit mapping
]);
```

### Issue: Dropdown appears behind screen content

**Solution:** Render dropdown AFTER screen content:

```php
$screen->render($renderer, ...);
$menuSystem->renderDropdown($renderer, ...);  // After screen
```

### Issue: Menu colors incorrect

**Solution:** Ensure ColorScheme is initialized with theme:

```php
ColorScheme::applyTheme(new MidnightTheme());
```

### Issue: Quit menu not working

**Solution:** Wire up onQuit callback:

```php
$menuSystem->onQuit(function() {
    $this->requestQuit();
});
```

---

## Complete Example: Application with Menu System

```php
final class Application
{
    private TerminalManager $terminal;
    private Renderer $renderer;
    private KeyboardHandler $keyboard;
    private ScreenManager $screenManager;
    private ScreenRegistry $registry;
    private MenuSystem $menuSystem;
    private bool $running = false;
    
    public function __construct()
    {
        $this->terminal = new TerminalManager();
        $this->renderer = new Renderer($this->terminal);
        $this->keyboard = new KeyboardHandler();
        $this->screenManager = new ScreenManager();
        $this->registry = new ScreenRegistry();
    }
    
    public function setScreenRegistry(ScreenRegistry $registry): void
    {
        $this->registry = $registry;
    }
    
    public function setMenuSystem(MenuSystem $menuSystem): void
    {
        $this->menuSystem = $menuSystem;
        
        // Wire up quit callback
        $this->menuSystem->onQuit(function() {
            $this->requestQuit();
        });
    }
    
    public function run(ScreenInterface $initialScreen): void
    {
        try {
            $this->terminal->initialize();
            $this->keyboard->enableNonBlocking();
            
            $this->screenManager->pushScreen($initialScreen);
            $this->running = true;
            
            while ($this->running) {
                // Handle input
                $key = $this->keyboard->getKey();
                if ($key !== null) {
                    $this->handleInput($key);
                }
                
                // Update current screen
                $currentScreen = $this->screenManager->getCurrentScreen();
                if ($currentScreen !== null) {
                    $currentScreen->update();
                }
                
                // Render frame
                $this->renderFrame();
                
                // Maintain target FPS
                \usleep(33333); // ~30 FPS
            }
        } finally {
            $this->terminal->cleanup();
        }
    }
    
    public function requestQuit(): void
    {
        $this->running = false;
    }
    
    private function renderFrame(): void
    {
        $size = $this->renderer->getSize();
        
        $this->renderer->beginFrame();
        
        // 1. Render menu bar (top line)
        $this->menuSystem->render(
            $this->renderer, 
            0, 0, 
            $size['width'], 1,
        );
        
        // 2. Render current screen
        $currentScreen = $this->screenManager->getCurrentScreen();
        if ($currentScreen !== null) {
            $currentScreen->render(
                $this->renderer, 
                0, 1, 
                $size['width'], 
                $size['height'] - 1,
            );
        }
        
        // 3. Render dropdown overlay (AFTER screen)
        $this->menuSystem->renderDropdown(
            $this->renderer, 
            0, 1, 
            $size['width'], 
            $size['height'] - 1,
        );
        
        $this->renderer->endFrame();
    }
    
    private function handleInput(string $key): void
    {
        // 1. Global quit
        if ($key === 'CTRL_C') {
            $this->requestQuit();
            return;
        }
        
        // 2. Try menu system (F-keys, dropdown)
        if ($this->menuSystem->handleInput($key)) {
            return;
        }
        
        // 3. Delegate to current screen
        $currentScreen = $this->screenManager->getCurrentScreen();
        if ($currentScreen !== null) {
            $currentScreen->handleInput($key);
        }
    }
}
