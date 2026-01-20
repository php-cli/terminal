<?php

declare(strict_types=1);

namespace Tests\Unit\UI\Menu;

use Butschster\Commander\UI\Menu\ScreenMenuItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
