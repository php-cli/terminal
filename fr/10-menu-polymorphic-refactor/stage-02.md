# Stage 2: Create Concrete Item Classes

## Objective
Implement all four concrete menu item types.

---

## Tasks

### 2.1 Create ScreenMenuItem

**File**: `src/UI/Menu/ScreenMenuItem.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Menu;

/**
 * Menu item that navigates to a screen
 */
final readonly class ScreenMenuItem extends AbstractMenuItem
{
    public function __construct(
        string $label,
        public string $screenName,
        ?string $hotkey = null,
    ) {
        parent::__construct($label, $hotkey);
    }

    public static function create(string $label, string $screenName, ?string $hotkey = null): self
    {
        return new self($label, $screenName, $hotkey);
    }
}
```

---

### 2.2 Create ActionMenuItem

**File**: `src/UI/Menu/ActionMenuItem.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Menu;

/**
 * Menu item that executes a closure when selected
 */
final readonly class ActionMenuItem extends AbstractMenuItem
{
    public \Closure $action;

    public function __construct(
        string $label,
        callable $action,
        ?string $hotkey = null,
    ) {
        parent::__construct($label, $hotkey);
        $this->action = $action(...);
    }

    public static function create(string $label, callable $action, ?string $hotkey = null): self
    {
        return new self($label, $action, $hotkey);
    }
}
```

**Note**: Constructor accepts `callable` and converts to `\Closure` for type safety.

---

### 2.3 Create SubmenuMenuItem

**File**: `src/UI/Menu/SubmenuMenuItem.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Menu;

/**
 * Menu item that contains nested menu items
 */
final readonly class SubmenuMenuItem extends AbstractMenuItem
{
    /**
     * @param string $label Display label
     * @param array<MenuItemInterface> $items Nested menu items
     * @param string|null $hotkey Quick access key
     */
    public function __construct(
        string $label,
        public array $items,
        ?string $hotkey = null,
    ) {
        parent::__construct($label, $hotkey);
    }

    /**
     * @param array<MenuItemInterface> $items
     */
    public static function create(string $label, array $items, ?string $hotkey = null): self
    {
        return new self($label, $items, $hotkey);
    }
}
```

---

### 2.4 Create SeparatorMenuItem

**File**: `src/UI/Menu/SeparatorMenuItem.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Menu;

/**
 * Visual separator between menu items
 */
final readonly class SeparatorMenuItem extends AbstractMenuItem
{
    private const string SEPARATOR_LABEL = '─────────';

    public function __construct()
    {
        parent::__construct(self::SEPARATOR_LABEL, null);
    }

    public static function create(): self
    {
        return new self();
    }

    #[\Override]
    public function isSeparator(): bool
    {
        return true;
    }

    #[\Override]
    public function getHotkey(): ?string
    {
        return null;
    }
}
```

---

## Unit Tests

### ScreenMenuItemTest

**File**: `tests/Unit/UI/Menu/ScreenMenuItemTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\UI\Menu;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Butschster\Commander\UI\Menu\ScreenMenuItem;

#[CoversClass(ScreenMenuItem::class)]
final class ScreenMenuItemTest extends TestCase
{
    #[Test]
    public function it_creates_via_constructor(): void
    {
        $item = new ScreenMenuItem('Files', 'files.list', 'f');

        self::assertSame('Files', $item->getLabel());
        self::assertSame('files.list', $item->screenName);
        self::assertSame('f', $item->getHotkey());
        self::assertFalse($item->isSeparator());
    }

    #[Test]
    public function it_creates_via_static_factory(): void
    {
        $item = ScreenMenuItem::create('Tools', 'tools.main');

        self::assertSame('Tools', $item->getLabel());
        self::assertSame('tools.main', $item->screenName);
        self::assertSame('t', $item->getHotkey());
    }
}
```

### ActionMenuItemTest

**File**: `tests/Unit/UI/Menu/ActionMenuItemTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\UI\Menu;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Butschster\Commander\UI\Menu\ActionMenuItem;

#[CoversClass(ActionMenuItem::class)]
final class ActionMenuItemTest extends TestCase
{
    #[Test]
    public function it_creates_with_closure(): void
    {
        $executed = false;
        $item = new ActionMenuItem('Save', function () use (&$executed) {
            $executed = true;
        });

        ($item->action)();

        self::assertTrue($executed);
        self::assertSame('Save', $item->getLabel());
    }

    #[Test]
    public function it_converts_callable_to_closure(): void
    {
        $item = ActionMenuItem::create('Test', 'strtoupper');

        self::assertInstanceOf(\Closure::class, $item->action);
    }

    #[Test]
    public function it_is_not_separator(): void
    {
        $item = ActionMenuItem::create('Test', fn() => null);

        self::assertFalse($item->isSeparator());
    }
}
```

### SubmenuMenuItemTest

**File**: `tests/Unit/UI/Menu/SubmenuMenuItemTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\UI\Menu;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Butschster\Commander\UI\Menu\SubmenuMenuItem;
use Butschster\Commander\UI\Menu\ScreenMenuItem;
use Butschster\Commander\UI\Menu\SeparatorMenuItem;

#[CoversClass(SubmenuMenuItem::class)]
final class SubmenuMenuItemTest extends TestCase
{
    #[Test]
    public function it_creates_with_nested_items(): void
    {
        $items = [
            ScreenMenuItem::create('Option 1', 'screen.1'),
            SeparatorMenuItem::create(),
            ScreenMenuItem::create('Option 2', 'screen.2'),
        ];

        $submenu = SubmenuMenuItem::create('More...', $items, 'm');

        self::assertSame('More...', $submenu->getLabel());
        self::assertSame('m', $submenu->getHotkey());
        self::assertCount(3, $submenu->items);
        self::assertFalse($submenu->isSeparator());
    }

    #[Test]
    public function it_allows_empty_items_array(): void
    {
        $submenu = SubmenuMenuItem::create('Empty', []);

        self::assertCount(0, $submenu->items);
    }
}
```

### SeparatorMenuItemTest

**File**: `tests/Unit/UI/Menu/SeparatorMenuItemTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\UI\Menu;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Butschster\Commander\UI\Menu\SeparatorMenuItem;

#[CoversClass(SeparatorMenuItem::class)]
final class SeparatorMenuItemTest extends TestCase
{
    #[Test]
    public function it_is_separator(): void
    {
        $item = SeparatorMenuItem::create();

        self::assertTrue($item->isSeparator());
    }

    #[Test]
    public function it_has_no_hotkey(): void
    {
        $item = SeparatorMenuItem::create();

        self::assertNull($item->getHotkey());
    }

    #[Test]
    public function it_has_separator_label(): void
    {
        $item = SeparatorMenuItem::create();

        self::assertSame('─────────', $item->getLabel());
    }
}
```

---

## Verification

```bash
# Run all new tests
./vendor/bin/phpunit tests/Unit/UI/Menu/

# PHPStan check
./vendor/bin/phpstan analyse src/UI/Menu/
```

---

## Checklist

- [ ] `ScreenMenuItem.php` created
- [ ] `ActionMenuItem.php` created
- [ ] `SubmenuMenuItem.php` created
- [ ] `SeparatorMenuItem.php` created
- [ ] All unit tests created and passing
- [ ] PHPStan passes
