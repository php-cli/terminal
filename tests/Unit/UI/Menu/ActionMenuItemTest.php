<?php

declare(strict_types=1);

namespace Tests\Unit\UI\Menu;

use Butschster\Commander\UI\Menu\ActionMenuItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ActionMenuItem::class)]
final class ActionMenuItemTest extends TestCase
{
    #[Test]
    public function it_creates_with_closure(): void
    {
        $executed = false;
        $item = new ActionMenuItem('Save', static function () use (&$executed): void {
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
        $item = ActionMenuItem::create('Test', static fn(): null => null);

        self::assertFalse($item->isSeparator());
    }
}
