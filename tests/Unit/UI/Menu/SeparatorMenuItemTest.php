<?php

declare(strict_types=1);

namespace Tests\Unit\UI\Menu;

use Butschster\Commander\UI\Menu\SeparatorMenuItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
