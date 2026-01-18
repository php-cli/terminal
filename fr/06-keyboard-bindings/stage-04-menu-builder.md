# Stage 4: MenuBuilder Refactoring

## Overview

Refactor `MenuBuilder` and related menu classes to use `KeyBindingRegistry` instead
of hardcoded F-key mappings. This enables dynamic key assignment from the registry.

## Files

**MODIFY:**
- `src/UI/Menu/MenuBuilder.php` - Inject registry, remove hardcoded F-keys
- `src/UI/Menu/MenuDefinition.php` - Accept KeyCombination instead of string
- `src/UI/Component/Layout/MenuSystem.php` - Use KeyCombination for matching

## Code References

- `src/UI/Menu/MenuBuilder.php:20-25` - Current DEFAULT_FKEY_MAP to remove
- `src/UI/Menu/MenuBuilder.php:85-91` - Hardcoded F10 for quit
- `src/UI/Menu/MenuDefinition.php:14` - Current string fkey parameter
- `src/UI/Component/Layout/MenuSystem.php:100-103` - F10 special handling

## Implementation Details

### MenuDefinition Changes

Update to accept `KeyCombination` instead of string:

```php
final readonly class MenuDefinition
{
    /**
     * @param string $label Menu label (e.g., "Files", "Tools")
     * @param KeyCombination|null $fkey Function key to activate
     * @param array<MenuItem> $items Dropdown menu items
     * @param int $priority Sort order (lower = further left)
     */
    public function __construct(
        public string $label,
        public ?KeyCombination $fkey,  // Changed from ?string
        public array $items,
        public int $priority = 0,
    ) {}
    
    // ... existing methods unchanged
}
```

### MenuBuilder Changes

```php
final class MenuBuilder
{
    // REMOVE this constant:
    // private const array DEFAULT_FKEY_MAP = [...]
    
    public function __construct(
        private readonly ScreenRegistry $registry,
        private readonly KeyBindingRegistryInterface $keyBindings,  // NEW
    ) {}
    
    public function build(): array
    {
        $screensByCategory = $this->registry->getByCategory();
        $priority = 0;
        
        foreach ($screensByCategory as $category => $screens) {
            if (empty($screens)) {
                continue;
            }
            
            // Get F-key from registry instead of hardcoded map
            $actionId = 'menu.' . $category;
            $binding = $this->keyBindings->getByActionId($actionId);
            $fkey = $binding?->combination;
            
            // Create menu items from screens
            $items = [];
            foreach ($screens as $metadata) {
                $items[] = MenuItem::screen(
                    $metadata->getDisplayText(),
                    $metadata->name,
                );
            }
            
            $this->menus[$category] = new MenuDefinition(
                \ucfirst($category),
                $fkey,  // Now KeyCombination|null
                $items,
                $priority++,
            );
        }
        
        // Quit menu - get F-key from registry
        $quitBinding = $this->keyBindings->getByActionId('app.quit');
        $this->menus['quit'] = new MenuDefinition(
            'Quit',
            $quitBinding?->combination,  // Will be F12 from DefaultKeyBindings
            [
                MenuItem::action('Quit', static function (): void {}, 'q'),
            ],
            999,
        );
        
        return $this->menus;
    }
}
```

### MenuSystem Changes

Update F-key handling to use `KeyCombination`:

```php
final class MenuSystem extends AbstractComponent
{
    /** @var array<string, KeyCombination> F-key to menu key mapping */
    private array $fkeyMap = [];
    
    private function initializeMenus(): void
    {
        $this->sortedMenus = $this->menus;
        \uasort($this->sortedMenus, static fn($a, $b) => $a->priority <=> $b->priority);
        
        // Build F-key map using KeyCombination
        foreach ($this->sortedMenus as $categoryKey => $menu) {
            if ($menu->fkey !== null) {
                $this->fkeyMap[$menu->fkey->toRawKey()] = $categoryKey;
            }
        }
    }
    
    public function handleInput(string $key): bool
    {
        // ... existing dropdown handling ...
        
        // Handle F-key menu activation using raw key matching
        if (isset($this->fkeyMap[$key])) {
            $menuKey = $this->fkeyMap[$key];
            
            // Special handling for quit - check against registry actionId
            $menu = $this->menus[$menuKey] ?? null;
            if ($menu !== null && $menuKey === 'quit') {
                $firstItem = $menu->getFirstItem();
                if ($firstItem !== null && $firstItem->isAction()) {
                    $this->handleMenuItemSelected($firstItem);
                    return true;
                }
            }
            
            $this->openMenu($menuKey);
            return true;
        }
        
        return false;
    }
    
    private function renderMenuBar(Renderer $renderer, int $x, int $y, int $width): void
    {
        // ... existing code ...
        
        // Render F-key using KeyCombination's __toString()
        if ($menu->fkey !== null) {
            $fkeyText = (string) $menu->fkey;  // "F12", "Ctrl+Q", etc.
            $renderer->writeAt($currentX, $y, $fkeyText, $textColor);
            $currentX += \mb_strlen($fkeyText);
        }
        
        // ... rest of rendering ...
    }
}
```

### Application Changes (Minimal)

Update `setMenuSystem()` in Application to pass registry:

```php
// Application::setMenuSystem() should work with updated MenuBuilder
// No changes needed if MenuBuilder is created externally
```

## Definition of Done

- [ ] `MenuDefinition` accepts `KeyCombination|null` for fkey
- [ ] `MenuBuilder` constructor accepts `KeyBindingRegistryInterface`
- [ ] `MenuBuilder` removes `DEFAULT_FKEY_MAP` constant
- [ ] `MenuBuilder::build()` gets F-keys from registry
- [ ] Quit menu uses `app.quit` binding (F12)
- [ ] `MenuSystem` builds fkeyMap from `KeyCombination`
- [ ] `MenuSystem` uses `toRawKey()` for input matching
- [ ] Menu bar displays F-keys correctly (F1, F2, F3, F12 for quit)
- [ ] All menu F-keys work when pressed
- [ ] No hardcoded F10 references remain in menu code

## Dependencies

**Requires**: Stage 2 (KeyBindingRegistry)
**Enables**: Stage 5 (Application can use same registry)
