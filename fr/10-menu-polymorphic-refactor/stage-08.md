# Stage 8: Cleanup and Verification

## Objective
Remove the old `MenuItem` class and perform final verification.

---

## Tasks

### 8.1 Delete Old MenuItem Class

**Delete**: `src/UI/Menu/MenuItem.php`

```bash
rm src/UI/Menu/MenuItem.php
```

---

### 8.2 Search for Remaining References

Run these searches to ensure no code still references the old class:

```bash
# Search for any remaining MenuItem references (excluding new classes)
grep -r "MenuItem::" src/ --include="*.php"
grep -r "use.*\\\\MenuItem;" src/ --include="*.php"
grep -r "isScreen\(\)" src/ --include="*.php"
grep -r "isAction\(\)" src/ --include="*.php"
grep -r "isSubmenu\(\)" src/ --include="*.php"
```

**Expected Result**: No matches found.

---

### 8.3 Update Any Remaining References

If any references are found, update them:

| Pattern | Replacement |
|---------|-------------|
| `MenuItem::screen()` | `ScreenMenuItem::create()` |
| `MenuItem::action()` | `ActionMenuItem::create()` |
| `MenuItem::separator()` | `SeparatorMenuItem::create()` |
| `MenuItem::submenu()` | `SubmenuMenuItem::create()` |
| `$item->isScreen()` | `$item instanceof ScreenMenuItem` |
| `$item->isAction()` | `$item instanceof ActionMenuItem` |
| `$item->isSubmenu()` | `$item instanceof SubmenuMenuItem` |
| `$item->isSeparator()` | Keep as-is (interface method) |
| `$item->screenName` | Keep as-is (property exists on `ScreenMenuItem`) |
| `$item->action` | Keep as-is (property exists on `ActionMenuItem`) |
| `$item->submenu` | `$item->items` (renamed on `SubmenuMenuItem`) |
| `$item->label` | `$item->getLabel()` (use interface method) |
| `$item->hotkey` | `$item->getHotkey()` (use interface method) |

---

### 8.4 Run Full Test Suite

```bash
# Unit tests
./vendor/bin/phpunit --testsuite unit

# Integration tests
./vendor/bin/phpunit --testsuite integration

# E2E tests
./vendor/bin/phpunit --testsuite e2e

# All tests
./vendor/bin/phpunit
```

---

### 8.5 Run Static Analysis

```bash
# PHPStan
./vendor/bin/phpstan analyse src/ --level 8

# Check specifically the Menu module
./vendor/bin/phpstan analyse src/UI/Menu/ src/UI/Component/Layout/MenuSystem.php src/UI/Component/Layout/MenuDropdown.php
```

---

### 8.6 Verify Directory Structure

Final structure should be:

```
src/UI/Menu/
├── MenuItemInterface.php     [NEW]
├── AbstractMenuItem.php      [NEW]
├── ScreenMenuItem.php        [NEW]
├── ActionMenuItem.php        [NEW]
├── SubmenuMenuItem.php       [NEW]
├── SeparatorMenuItem.php     [NEW]
├── MenuDefinition.php        [MODIFIED]
└── MenuBuilder.php           [MODIFIED]
```

**Deleted**: `MenuItem.php`

---

### 8.7 Update Documentation (Optional)

If any documentation references the old `MenuItem` class:

1. Update README examples
2. Update inline code comments
3. Update any external documentation

---

## Summary of Changes

| File | Change Type | Description |
|------|-------------|-------------|
| `MenuItem.php` | **DELETED** | Replaced by class hierarchy |
| `MenuItemInterface.php` | **NEW** | Contract interface |
| `AbstractMenuItem.php` | **NEW** | Shared base implementation |
| `ScreenMenuItem.php` | **NEW** | Screen navigation item |
| `ActionMenuItem.php` | **NEW** | Action execution item |
| `SubmenuMenuItem.php` | **NEW** | Nested menu item |
| `SeparatorMenuItem.php` | **NEW** | Visual separator |
| `MenuDefinition.php` | MODIFIED | Type hints updated |
| `MenuBuilder.php` | MODIFIED | Uses new factories |
| `MenuSystem.php` | MODIFIED | Pattern matching |
| `MenuDropdown.php` | MODIFIED | Interface types |
| `MenuDropdownTest.php` | MODIFIED | Updated for new classes |

---

## Verification Commands Summary

```bash
# 1. Delete old file
rm src/UI/Menu/MenuItem.php

# 2. Search for remaining references
grep -rn "MenuItem::" src/ tests/ --include="*.php" | grep -v "MenuItem::" | head -20

# 3. Run all tests
./vendor/bin/phpunit

# 4. Run static analysis
./vendor/bin/phpstan analyse src/

# 5. Verify file structure
ls -la src/UI/Menu/
```

---

## Checklist

- [ ] `MenuItem.php` deleted
- [ ] No remaining references to old class
- [ ] All unit tests pass
- [ ] All integration tests pass
- [ ] All E2E tests pass (Stage 9)
- [ ] PHPStan level 8 passes
- [ ] Directory structure confirmed
- [ ] Documentation updated (if needed)
