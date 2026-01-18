# Stage 6: Refactor MenuDropdown

## Objective
Update `MenuDropdown` to work with the polymorphic menu item hierarchy.

---

## Current Code Reference

**File**: `src/UI/Component/Layout/MenuDropdown.php`

### Line 10 (Import)

```php
use Butschster\Commander\UI\Menu\MenuItem;
```

### Line 30 (Constructor)

```php
public function __construct(
    private readonly array $items,
    // ...
)
```

### Lines 45-46 (onSelect callback)

```php
/** @var \Closure(MenuItem): void */
private \Closure $onSelect;
```

### Lines 216-248 (renderItem method)

```php
private function renderItem(
    Renderer $renderer,
    int $x,
    int $y,
    int $width,
    MenuItem $item,
    bool $isSelected,
): void {
    if ($item->isSeparator()) {
        // ...
    }
    
    // ...
    
    if ($item->isSubmenu()) {
        $renderer->writeAt($x + $width - 2, $y, '►', $color);
    }
}
```

---

## Changes Required

### 6.1 Update Imports

```php
// BEFORE
use Butschster\Commander\UI\Menu\MenuItem;

// AFTER
use Butschster\Commander\UI\Menu\MenuItemInterface;
use Butschster\Commander\UI\Menu\SeparatorMenuItem;
use Butschster\Commander\UI\Menu\SubmenuMenuItem;
```

---

### 6.2 Update PHPDoc for Constructor

```php
/**
 * @param array<MenuItemInterface> $items Menu items to display
 */
public function __construct(
    private readonly array $items,
    // ...
)
```

---

### 6.3 Update onSelect Callback Type

```php
// BEFORE
/** @var \Closure(MenuItem): void */
private \Closure $onSelect;

// AFTER
/** @var \Closure(MenuItemInterface): void */
private \Closure $onSelect;
```

---

### 6.4 Update renderItem() Parameter Type

```php
// BEFORE
private function renderItem(
    Renderer $renderer,
    int $x,
    int $y,
    int $width,
    MenuItem $item,
    bool $isSelected,
): void

// AFTER
private function renderItem(
    Renderer $renderer,
    int $x,
    int $y,
    int $width,
    MenuItemInterface $item,
    bool $isSelected,
): void
```

---

### 6.5 Update Submenu Check in renderItem()

```php
// BEFORE (line ~243)
if ($item->isSubmenu()) {
    $renderer->writeAt($x + $width - 2, $y, '►', $color);
}

// AFTER
if ($item instanceof SubmenuMenuItem) {
    $renderer->writeAt($x + $width - 2, $y, '►', $color);
}
```

---

### 6.6 Update Hotkey Access in renderItem()

The `hotkey` property access needs to use the interface method:

```php
// BEFORE (line ~234)
if ($item->hotkey !== null) {
    $hotkey = \strtoupper($item->hotkey) . ' ';
    // ...
}

// AFTER
$hotkey = $item->getHotkey();
if ($hotkey !== null) {
    $hotkeyText = \strtoupper($hotkey) . ' ';
    // ...
}
```

---

### 6.7 Update calculateWidth()

```php
// BEFORE (line ~204)
if ($item->hotkey !== null) {
    $itemWidth += 4;
}

// AFTER
if ($item->getHotkey() !== null) {
    $itemWidth += 4;
}
```

**Note**: The `isSeparator()` calls remain unchanged since it's an interface method.

---

## Full Diff Summary

```diff
 use Butschster\Commander\Infrastructure\Keyboard\Key;
 use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
 use Butschster\Commander\Infrastructure\Terminal\Renderer;
 use Butschster\Commander\UI\Component\AbstractComponent;
-use Butschster\Commander\UI\Menu\MenuItem;
+use Butschster\Commander\UI\Menu\MenuItemInterface;
+use Butschster\Commander\UI\Menu\SeparatorMenuItem;
+use Butschster\Commander\UI\Menu\SubmenuMenuItem;
 use Butschster\Commander\UI\Theme\ColorScheme;
 
-    /** @var \Closure(MenuItem): void */
+    /** @var \Closure(MenuItemInterface): void */
     private \Closure $onSelect;
 
     /**
-     * @param array<MenuItem> $items Menu items to display
+     * @param array<MenuItemInterface> $items Menu items to display
      */
 
     private function renderItem(
         Renderer $renderer,
         int $x,
         int $y,
         int $width,
-        MenuItem $item,
+        MenuItemInterface $item,
         bool $isSelected,
     ): void {
 
-        if ($item->hotkey !== null) {
-            $hotkey = \strtoupper($item->hotkey) . ' ';
+        $hotkey = $item->getHotkey();
+        if ($hotkey !== null) {
+            $hotkeyText = \strtoupper($hotkey) . ' ';
 
-        if ($item->isSubmenu()) {
+        if ($item instanceof SubmenuMenuItem) {
             $renderer->writeAt($x + $width - 2, $y, '►', $color);
         }
 
     // In calculateWidth():
-        if ($item->hotkey !== null) {
+        if ($item->getHotkey() !== null) {
```

---

## Verification

```bash
# Run full test suite
./vendor/bin/phpunit

# Run E2E tests specifically
./vendor/bin/phpunit --testsuite e2e
```

---

## Checklist

- [ ] Imports updated
- [ ] Constructor PHPDoc updated
- [ ] `$onSelect` callback type updated
- [ ] `renderItem()` parameter type updated
- [ ] `$item->hotkey` → `$item->getHotkey()`
- [ ] `$item->isSubmenu()` → `$item instanceof SubmenuMenuItem`
- [ ] `calculateWidth()` updated
- [ ] All tests pass
