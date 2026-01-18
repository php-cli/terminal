# Stage 5: Application Refactoring

## Overview

Refactor `Application` class to use `KeyBindingRegistry` for global shortcuts instead
of the manual `$globalShortcuts` array. This centralizes all key handling through
the registry.

## Files

**MODIFY:**
- `src/Application.php` - Use registry for global shortcuts

## Code References

- `src/Application.php:36` - Current `$globalShortcuts` array to replace
- `src/Application.php:119-122` - Current `registerGlobalShortcut()` method
- `src/Application.php:196-213` - Current input handling with global shortcuts
- `src/Application.php:305-320` - Menu shortcut registration

## Implementation Details

### Constructor Changes

```php
final class Application
{
    // REMOVE: private array $globalShortcuts = [];
    
    private readonly KeyBindingRegistryInterface $keyBindings;
    
    /** @var array<string, callable> Action handlers by action ID */
    private array $actionHandlers = [];
    
    public function __construct(
        ?KeyBindingRegistryInterface $keyBindings = null,
    ) {
        // ... existing initialization ...
        
        // Initialize key bindings
        $this->keyBindings = $keyBindings ?? $this->createDefaultRegistry();
        
        // Register core action handlers
        $this->registerCoreActions();
    }
    
    private function createDefaultRegistry(): KeyBindingRegistry
    {
        $registry = new KeyBindingRegistry();
        DefaultKeyBindings::register($registry);
        return $registry;
    }
    
    private function registerCoreActions(): void
    {
        // Register quit handler
        $this->onAction('app.quit', fn() => $this->stop());
    }
}
```

### Action Handler Registration

Replace `registerGlobalShortcut()` with action-based registration:

```php
/**
 * Register handler for an action ID
 * 
 * @param string $actionId Action ID from KeyBinding (e.g., 'app.quit')
 * @param callable $handler Handler callback
 */
public function onAction(string $actionId, callable $handler): void
{
    $this->actionHandlers[$actionId] = $handler;
}

/**
 * Execute action by ID
 */
private function executeAction(string $actionId): bool
{
    if (!isset($this->actionHandlers[$actionId])) {
        return false;
    }
    
    ($this->actionHandlers[$actionId])($this->screenManager);
    return true;
}

// DEPRECATE but keep for backward compatibility:
/**
 * @deprecated Use onAction() with KeyBinding action IDs instead
 */
public function registerGlobalShortcut(string $key, callable $callback): void
{
    // Create ad-hoc binding for backward compatibility
    $combination = KeyCombination::fromString($key);
    $actionId = 'legacy.' . \strtolower(\str_replace(['+', ' '], '_', $key));
    
    $this->keyBindings->register(new KeyBinding(
        $combination,
        $actionId,
        'Legacy shortcut',
        'legacy',
    ));
    
    $this->actionHandlers[$actionId] = $callback;
}
```

### Input Handling Changes

```php
private function handleInput(): void
{
    while (($key = $this->keyboard->getKey()) !== null) {
        // Priority 1: Menu system
        if ($this->menuSystem !== null) {
            $handled = $this->menuSystem->handleInput($key);
            if ($handled) {
                $this->checkScreenChange();
                continue;
            }
        }
        
        // Priority 2: Global key bindings from registry
        $binding = $this->keyBindings->match($key);
        if ($binding !== null) {
            $executed = $this->executeAction($binding->actionId);
            if ($executed) {
                $this->checkScreenChange();
                continue;
            }
        }
        
        // Priority 3: Route to current screen
        $handled = $this->screenManager->handleInput($key);
        $this->checkScreenChange();
        
        // Priority 4: ESC to go back (if not handled)
        if (!$handled && $key === 'ESCAPE') {
            if ($this->screenManager->getDepth() > 1) {
                $this->screenManager->popScreen();
                $this->checkScreenChange();
            }
        }
    }
}
```

### Menu System Integration

Update `setMenuSystem()` to register menu actions:

```php
public function setMenuSystem(array $menus): void
{
    if ($this->screenRegistry === null) {
        throw new \RuntimeException('Screen registry must be set before menu system');
    }
    
    $this->menuSystem = new MenuSystem(
        $menus,
        $this->screenRegistry,
        $this->screenManager,
    );
    
    $this->menuSystem->onQuit(fn() => $this->stop());
    
    // Register screen navigation actions for each menu category
    foreach ($menus as $category => $menu) {
        if ($category === 'quit') {
            continue;
        }
        
        $actionId = 'menu.' . $category;
        $firstItem = $menu->getFirstItem();
        
        if ($firstItem !== null && $firstItem->isScreen()) {
            $screenName = $firstItem->screenName;
            $this->onAction($actionId, function () use ($screenName): void {
                $this->navigateToScreen($screenName);
            });
        }
    }
}

private function navigateToScreen(string $screenName): void
{
    $screen = $this->screenRegistry?->getScreen($screenName);
    if ($screen === null) {
        return;
    }
    
    $current = $this->screenManager->getCurrentScreen();
    if ($current !== null && $current::class === $screen::class) {
        return;
    }
    
    $this->screenManager->pushScreen($screen);
}
```

### Remove Dead Code

After refactoring, remove:
- `registerMenuShortcut()` private method (no longer needed)
- Any remaining references to old `$globalShortcuts` array

## Definition of Done

- [ ] `Application` accepts optional `KeyBindingRegistryInterface` in constructor
- [ ] Default registry is created with `DefaultKeyBindings` if not provided
- [ ] `onAction()` method registers handlers by action ID
- [ ] `executeAction()` looks up and executes handlers
- [ ] `handleInput()` uses registry to match keys
- [ ] `registerGlobalShortcut()` is deprecated but works via legacy bindings
- [ ] Quit works with F12 (from registry)
- [ ] Quit works with Ctrl+Q (from registry)
- [ ] Quit works with Ctrl+C (from registry)
- [ ] Menu F-keys work for navigation
- [ ] Old `$globalShortcuts` array is removed
- [ ] `registerMenuShortcut()` method is removed

## Dependencies

**Requires**: Stage 2 (KeyBindingRegistry), Stage 4 (MenuBuilder uses same registry)
**Enables**: Stage 6 (Screens can use same patterns)
