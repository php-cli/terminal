# Stage 5: Refactor MenuSystem

## Objective
Update `MenuSystem` to use polymorphic dispatch instead of type-checking methods.

---

## Current Code Reference

**File**: `src/UI/Component/Layout/MenuSystem.php`

### Lines 10 (Import)

```php
use Butschster\Commander\UI\Menu\MenuItem;
```

### Lines 88-98 (F-key handling)

```php
if ($menuKey === 'quit') {
    $menu = $this->menus[$menuKey] ?? null;
    if ($menu !== null) {
        $firstItem = $menu->getFirstItem();
        if ($firstItem !== null && $firstItem->isAction()) {
            $this->handleMenuItemSelected($firstItem);
            return true;
        }
    }
}
```

### Lines 259-271 (handleMenuItemSelected)

```php
private function handleMenuItemSelected(MenuItem $item): void
{
    // Close dropdown first
    $this->closeDropdown();

    // Execute item action based on type
    if ($item->isScreen()) {
        $this->navigateToScreen($item->screenName);
    } elseif ($item->isAction()) {
        // Check if this is the Quit action
        if ($item->label === 'Quit') {
            ($this->onQuit)();
        } elseif ($item->action !== null) {
            ($item->action)();
        }
    }
}
```

---

## Changes Required

### 5.1 Update Imports

**Replace**:

```php
// BEFORE
use Butschster\Commander\UI\Menu\MenuItem;

// AFTER
use Butschster\Commander\UI\Menu\ActionMenuItem;
use Butschster\Commander\UI\Menu\MenuItemInterface;
use Butschster\Commander\UI\Menu\ScreenMenuItem;
use Butschster\Commander\UI\Menu\SubmenuMenuItem;
```

---

### 5.2 Update F-key Handling (lines 88-98)

```php
// BEFORE
if ($firstItem !== null && $firstItem->isAction()) {
    $this->handleMenuItemSelected($firstItem);
    return true;
}

// AFTER
if ($firstItem instanceof ActionMenuItem) {
    $this->handleMenuItemSelected($firstItem);
    return true;
}
```

---

### 5.3 Refactor handleMenuItemSelected()

**Replace entire method**:

```php
/**
 * Handle menu item selection
 */
private function handleMenuItemSelected(MenuItemInterface $item): void
{
    // Close dropdown first
    $this->closeDropdown();

    // Execute item action based on type using pattern matching
    match (true) {
        $item instanceof ScreenMenuItem => $this->navigateToScreen($item->screenName),
        $item instanceof ActionMenuItem => $this->executeAction($item),
        $item instanceof SubmenuMenuItem => null, // TODO: Handle submenu opening
        default => null,
    };
}

/**
 * Execute action menu item
 */
private function executeAction(ActionMenuItem $item): void
{
    // Check if this is the Quit action
    if ($item->getLabel() === 'Quit') {
        ($this->onQuit)();
    } else {
        ($item->action)();
    }
}
```

**Note**: Extracted `executeAction()` to keep the match expression clean.

---

## Full Diff Summary

```diff
 use Butschster\Commander\Infrastructure\Terminal\Renderer;
 use Butschster\Commander\UI\Component\AbstractComponent;
+use Butschster\Commander\UI\Menu\ActionMenuItem;
 use Butschster\Commander\UI\Menu\MenuDefinition;
-use Butschster\Commander\UI\Menu\MenuItem;
+use Butschster\Commander\UI\Menu\MenuItemInterface;
+use Butschster\Commander\UI\Menu\ScreenMenuItem;
+use Butschster\Commander\UI\Menu\SubmenuMenuItem;
 use Butschster\Commander\UI\Screen\ScreenManager;

 // ... in handleInput():

-                if ($firstItem !== null && $firstItem->isAction()) {
+                if ($firstItem instanceof ActionMenuItem) {

 // ... handleMenuItemSelected():

-    private function handleMenuItemSelected(MenuItem $item): void
+    private function handleMenuItemSelected(MenuItemInterface $item): void
     {
         $this->closeDropdown();
 
-        if ($item->isScreen()) {
-            $this->navigateToScreen($item->screenName);
-        } elseif ($item->isAction()) {
-            if ($item->label === 'Quit') {
-                ($this->onQuit)();
-            } elseif ($item->action !== null) {
-                ($item->action)();
-            }
-        }
+        match (true) {
+            $item instanceof ScreenMenuItem => $this->navigateToScreen($item->screenName),
+            $item instanceof ActionMenuItem => $this->executeAction($item),
+            $item instanceof SubmenuMenuItem => null,
+            default => null,
+        };
     }
+
+    private function executeAction(ActionMenuItem $item): void
+    {
+        if ($item->getLabel() === 'Quit') {
+            ($this->onQuit)();
+        } else {
+            ($item->action)();
+        }
+    }
```

---

## Verification

```bash
# Run E2E tests for menu functionality
./vendor/bin/phpunit --filter Menu

# Run full test suite
./vendor/bin/phpunit
```

---

## Checklist

- [ ] Imports updated (remove `MenuItem`, add specific classes)
- [ ] F-key handling uses `instanceof ActionMenuItem`
- [ ] `handleMenuItemSelected()` parameter type changed
- [ ] Pattern matching implemented with `match`
- [ ] `executeAction()` helper method added
- [ ] All tests pass
