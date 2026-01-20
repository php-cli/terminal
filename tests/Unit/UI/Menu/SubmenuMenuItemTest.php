<?php

declare(strict_types=1);

namespace Tests\Unit\UI\Menu;

use Butschster\Commander\UI\Menu\ScreenMenuItem;
use Butschster\Commander\UI\Menu\SeparatorMenuItem;
use Butschster\Commander\UI\Menu\SubmenuMenuItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
