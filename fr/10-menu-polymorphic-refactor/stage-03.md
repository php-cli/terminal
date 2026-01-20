# Stage 3: Update MenuBuilder

## Objective
Migrate `MenuBuilder` to use the new polymorphic menu item classes.

---

## Current Code Reference

**File**: `src/UI/Menu/MenuBuilder.php:44-60`

```php
// Current implementation uses static factory methods
$items[] = MenuItem::screen(
    $metadata->getDisplayText(),
    $metadata->name,
);

// ... and for quit menu
MenuItem::action('Quit', static function (): void {
    // ...
}, 'q'),
```

---

## Changes Required

### 3.1 Update Imports

**Add** at top of file:

```php
use Butschster\Commander\UI\Menu\ActionMenuItem;
use Butschster\Commander\UI\Menu\MenuItemInterface;
use Butschster\Commander\UI\Menu\ScreenMenuItem;
use Butschster\Commander\UI\Menu\SeparatorMenuItem;
```

**Remove**:

```php
// This import will be removed after full migration
// use Butschster\Commander\UI\Menu\MenuItem;
```

---

### 3.2 Update build() Method

**Replace** lines 44-47:

```php
// BEFORE
$items[] = MenuItem::screen(
    $metadata->getDisplayText(),
    $metadata->name,
);

// AFTER
$items[] = ScreenMenuItem::create(
    $metadata->getDisplayText(),
    $metadata->name,
);
```

**Replace** lines 56-60:

```php
// BEFORE
MenuItem::action('Quit', static function (): void {
    // This is a marker action that MenuSystem will recognize
    // It will be handled by Application to actually stop
}, 'q'),

// AFTER
ActionMenuItem::create('Quit', static function (): void {
    // This is a marker action that MenuSystem will recognize
    // It will be handled by Application to actually stop
}, 'q'),
```

---

### 3.3 Update addItem() Method

**Change** parameter type at line 74:

```php
// BEFORE
public function addItem(string $category, MenuItem $item): self

// AFTER
public function addItem(string $category, MenuItemInterface $item): self
```

---

### 3.4 Update addSeparator() Method

**Replace** line 92:

```php
// BEFORE
return $this->addItem($category, MenuItem::separator());

// AFTER
return $this->addItem($category, SeparatorMenuItem::create());
```

---

## Full Diff Summary

```diff
 <?php
 
 declare(strict_types=1);
 
 namespace Butschster\Commander\UI\Menu;
 
 use Butschster\Commander\Infrastructure\Keyboard\KeyBindingRegistryInterface;
+use Butschster\Commander\UI\Menu\ActionMenuItem;
+use Butschster\Commander\UI\Menu\MenuItemInterface;
+use Butschster\Commander\UI\Menu\ScreenMenuItem;
+use Butschster\Commander\UI\Menu\SeparatorMenuItem;
 use Butschster\Commander\UI\Screen\ScreenRegistry;
 
 // ... in build() method:
 
-            $items[] = MenuItem::screen(
+            $items[] = ScreenMenuItem::create(
                 $metadata->getDisplayText(),
                 $metadata->name,
             );
 
 // ... quit menu:
 
-            MenuItem::action('Quit', static function (): void {
+            ActionMenuItem::create('Quit', static function (): void {
 
 // ... addItem():
 
-    public function addItem(string $category, MenuItem $item): self
+    public function addItem(string $category, MenuItemInterface $item): self
 
 // ... addSeparator():
 
-        return $this->addItem($category, MenuItem::separator());
+        return $this->addItem($category, SeparatorMenuItem::create());
```

---

## Verification

```bash
# Run MenuBuilder tests
./vendor/bin/phpunit --filter MenuBuilder

# Run full test suite to catch regressions
./vendor/bin/phpunit
```

---

## Checklist

- [ ] Imports updated
- [ ] `MenuItem::screen()` → `ScreenMenuItem::create()`
- [ ] `MenuItem::action()` → `ActionMenuItem::create()`
- [ ] `MenuItem::separator()` → `SeparatorMenuItem::create()`
- [ ] Parameter type updated to `MenuItemInterface`
- [ ] Tests pass
