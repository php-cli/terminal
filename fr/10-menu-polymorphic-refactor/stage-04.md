# Stage 4: Update MenuDefinition

## Objective
Update `MenuDefinition` to use `MenuItemInterface` type hints.

---

## Current Code Reference

**File**: `src/UI/Menu/MenuDefinition.php`

```php
/**
 * @param string $label Menu label (e.g., "Files", "Tools")
 * @param KeyCombination|null $fkey Function key to activate
 * @param array<MenuItem> $items Dropdown menu items
 * @param int $priority Sort order (lower = further left)
 */
public function __construct(
    public string $label,
    public ?KeyCombination $fkey,
    public array $items,
    public int $priority = 100,
) {}

/**
 * Get first non-separator item
 */
public function getFirstItem(): ?MenuItem
```

---

## Changes Required

### 4.1 Update Import

**Add** at top of file:

```php
use Butschster\Commander\UI\Menu\MenuItemInterface;
```

---

### 4.2 Update Constructor PHPDoc

**Replace** line 14:

```php
// BEFORE
 * @param array<MenuItem> $items Dropdown menu items

// AFTER
 * @param array<MenuItemInterface> $items Dropdown menu items
```

---

### 4.3 Update getFirstItem() Return Type

**Replace** line 24:

```php
// BEFORE
public function getFirstItem(): ?MenuItem

// AFTER
public function getFirstItem(): ?MenuItemInterface
```

---

## Full Updated File

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Menu;

use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;

/**
 * Menu Definition - represents a top-level menu with dropdown items
 */
final readonly class MenuDefinition
{
    /**
     * @param string $label Menu label (e.g., "Files", "Tools")
     * @param KeyCombination|null $fkey Function key to activate
     * @param array<MenuItemInterface> $items Dropdown menu items
     * @param int $priority Sort order (lower = further left)
     */
    public function __construct(
        public string $label,
        public ?KeyCombination $fkey,
        public array $items,
        public int $priority = 100,
    ) {}

    /**
     * Get first non-separator item
     */
    public function getFirstItem(): ?MenuItemInterface
    {
        foreach ($this->items as $item) {
            if (!$item->isSeparator()) {
                return $item;
            }
        }

        return null;
    }
}
```

---

## Verification

```bash
# Run tests
./vendor/bin/phpunit --filter MenuDefinition

# PHPStan
./vendor/bin/phpstan analyse src/UI/Menu/MenuDefinition.php
```

---

## Checklist

- [ ] Import added
- [ ] PHPDoc updated to `array<MenuItemInterface>`
- [ ] Return type updated to `?MenuItemInterface`
- [ ] Tests pass
