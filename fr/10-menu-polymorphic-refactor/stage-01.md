# Stage 1: Create Interface and Base Class

## Objective
Establish the foundation for the polymorphic menu item hierarchy.

---

## Tasks

### 1.1 Create MenuItemInterface

**File**: `src/UI/Menu/MenuItemInterface.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Menu;

/**
 * Contract for all menu item types
 */
interface MenuItemInterface
{
    /**
     * Get the display label for this menu item
     */
    public function getLabel(): string;

    /**
     * Get the hotkey for quick access
     * Returns lowercase hotkey if set, or first character of label as fallback
     */
    public function getHotkey(): ?string;

    /**
     * Check if this item is a visual separator
     */
    public function isSeparator(): bool;
}
```

---

### 1.2 Create AbstractMenuItem

**File**: `src/UI/Menu/AbstractMenuItem.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Menu;

/**
 * Base implementation for menu items with label and hotkey
 */
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

---

## Unit Tests

**File**: `tests/Unit/UI/Menu/AbstractMenuItemTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\UI\Menu;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Butschster\Commander\UI\Menu\AbstractMenuItem;

#[CoversClass(AbstractMenuItem::class)]
final class AbstractMenuItemTest extends TestCase
{
    #[Test]
    public function it_returns_label(): void
    {
        $item = new class('Test Label') extends AbstractMenuItem {};
        
        self::assertSame('Test Label', $item->getLabel());
    }

    #[Test]
    public function it_returns_explicit_hotkey_in_lowercase(): void
    {
        $item = new class('Test', 'X') extends AbstractMenuItem {};
        
        self::assertSame('x', $item->getHotkey());
    }

    #[Test]
    public function it_returns_first_char_of_label_when_no_hotkey(): void
    {
        $item = new class('Settings') extends AbstractMenuItem {};
        
        self::assertSame('s', $item->getHotkey());
    }

    #[Test]
    public function it_is_not_separator_by_default(): void
    {
        $item = new class('Test') extends AbstractMenuItem {};
        
        self::assertFalse($item->isSeparator());
    }
}
```

---

## Verification

```bash
# Run the new test
./vendor/bin/phpunit tests/Unit/UI/Menu/AbstractMenuItemTest.php

# Verify no syntax errors
php -l src/UI/Menu/MenuItemInterface.php
php -l src/UI/Menu/AbstractMenuItem.php
```

---

## Checklist

- [ ] `MenuItemInterface.php` created
- [ ] `AbstractMenuItem.php` created
- [ ] `AbstractMenuItemTest.php` created and passing
- [ ] PHPStan passes on new files
