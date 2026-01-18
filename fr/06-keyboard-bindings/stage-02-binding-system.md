# Stage 2: Binding System (KeyBinding & Registry)

## Overview

Create the binding infrastructure that links key combinations to actions. This provides
a centralized registry for all keyboard shortcuts, replacing scattered hardcoded bindings.

## Files

**CREATE:**
- `src/Infrastructure/Keyboard/KeyBinding.php` - DTO linking combination to action
- `src/Infrastructure/Keyboard/KeyBindingRegistryInterface.php` - Contract for registry
- `src/Infrastructure/Keyboard/KeyBindingRegistry.php` - Central registry implementation
- `src/Infrastructure/Keyboard/DefaultKeyBindings.php` - Default application bindings

## Code References

- `src/Application.php:36` - Current `$globalShortcuts` array we're replacing
- `src/Application.php:119-122` - Current `registerGlobalShortcut()` pattern
- `src/UI/Menu/MenuBuilder.php:20-25` - Current `DEFAULT_FKEY_MAP` we're replacing

## Implementation Details

### KeyBinding DTO

Immutable data object representing a single keyboard binding:

```php
final readonly class KeyBinding
{
    public function __construct(
        public KeyCombination $combination,
        public string $actionId,        // Unique ID like 'app.quit', 'menu.help'
        public string $description,      // Human-readable: "Quit application"
        public string $category = 'global', // For grouping: 'global', 'navigation', 'menu'
        public int $priority = 0,        // For ordering in help screen
    ) {}
    
    // Check if this binding matches a raw key
    public function matches(string $rawKey): bool
    {
        return $this->combination->matches($rawKey);
    }
}
```

### KeyBindingRegistryInterface

```php
interface KeyBindingRegistryInterface
{
    /**
     * Register a new key binding
     */
    public function register(KeyBinding $binding): void;
    
    /**
     * Find binding that matches raw key input
     */
    public function match(string $rawKey): ?KeyBinding;
    
    /**
     * Get binding by action ID
     */
    public function getByActionId(string $actionId): ?KeyBinding;
    
    /**
     * Get all bindings for a category
     * @return array<KeyBinding>
     */
    public function getByCategory(string $category): array;
    
    /**
     * Get all registered bindings
     * @return array<KeyBinding>
     */
    public function all(): array;
}
```

### KeyBindingRegistry Implementation

```php
final class KeyBindingRegistry implements KeyBindingRegistryInterface
{
    /** @var array<string, KeyBinding> Indexed by actionId */
    private array $bindings = [];
    
    public function register(KeyBinding $binding): void
    {
        // Allow multiple bindings for same action (e.g., F12 and Ctrl+Q both quit)
        $this->bindings[] = $binding;
    }
    
    public function match(string $rawKey): ?KeyBinding
    {
        foreach ($this->bindings as $binding) {
            if ($binding->matches($rawKey)) {
                return $binding;
            }
        }
        return null;
    }
    
    // ... other methods
}
```

### DefaultKeyBindings

Contains all default application bindings. **Important: Use F12 instead of F10 for quit!**

```php
final class DefaultKeyBindings
{
    public static function register(KeyBindingRegistryInterface $registry): void
    {
        // === Global Actions ===
        
        // Quit - PRIMARY: F12 (avoids GNOME Terminal conflict)
        $registry->register(new KeyBinding(
            KeyCombination::key(Key::F12),
            'app.quit',
            'Quit application',
            'global',
            priority: 100,
        ));
        
        // Quit - ALTERNATIVE: Ctrl+Q (universal)
        $registry->register(new KeyBinding(
            KeyCombination::ctrl(Key::Q),
            'app.quit',
            'Quit application',
            'global',
            priority: 101,
        ));
        
        // Quit - ALTERNATIVE: Ctrl+C (interrupt)
        $registry->register(new KeyBinding(
            KeyCombination::ctrl(Key::C),
            'app.quit',
            'Quit application',
            'global',
            priority: 102,
        ));
        
        // === Menu Navigation ===
        
        $registry->register(new KeyBinding(
            KeyCombination::key(Key::F1),
            'menu.help',
            'Help',
            'menu',
            priority: 1,
        ));
        
        $registry->register(new KeyBinding(
            KeyCombination::key(Key::F2),
            'menu.tools',
            'Tools menu',
            'menu',
            priority: 2,
        ));
        
        $registry->register(new KeyBinding(
            KeyCombination::key(Key::F3),
            'menu.files',
            'Files menu',
            'menu',
            priority: 3,
        ));
        
        $registry->register(new KeyBinding(
            KeyCombination::key(Key::F4),
            'menu.system',
            'System menu',
            'menu',
            priority: 4,
        ));
        
        $registry->register(new KeyBinding(
            KeyCombination::key(Key::F5),
            'menu.composer',
            'Composer menu',
            'menu',
            priority: 5,
        ));
        
        // === Navigation (informational, used by components) ===
        
        $registry->register(new KeyBinding(
            KeyCombination::key(Key::ESCAPE),
            'nav.back',
            'Go back / Close',
            'navigation',
        ));
        
        $registry->register(new KeyBinding(
            KeyCombination::key(Key::TAB),
            'nav.next_panel',
            'Switch panel',
            'navigation',
        ));
    }
}
```

### Action ID Naming Convention

Use dotted notation for action IDs:
- `app.*` - Application-level actions (quit, minimize)
- `menu.*` - Menu activation (help, tools, files)
- `nav.*` - Navigation (back, next_panel, search)
- `edit.*` - Editing (copy, paste, cut, undo)

## Definition of Done

- [ ] `KeyBinding` DTO is immutable and has `matches()` method
- [ ] `KeyBindingRegistryInterface` defines all required methods
- [ ] `KeyBindingRegistry` implements interface correctly
- [ ] `KeyBindingRegistry::match()` returns first matching binding
- [ ] `KeyBindingRegistry::getByActionId()` works for lookups
- [ ] `KeyBindingRegistry::getByCategory()` returns sorted by priority
- [ ] `DefaultKeyBindings` uses F12 for quit (not F10!)
- [ ] `DefaultKeyBindings` includes Ctrl+Q and Ctrl+C as quit alternatives
- [ ] All menu F-keys (F1-F5) are registered
- [ ] Code compiles without errors

## Dependencies

**Requires**: Stage 1 (Key, KeyCombination)
**Enables**: Stage 3 (KeyboardHandler Integration), Stage 4 (MenuBuilder)
