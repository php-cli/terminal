<?php

declare(strict_types=1);

namespace Tests\Unit\UI\Menu;

use Butschster\Commander\UI\Menu\AbstractMenuItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Concrete test stub for AbstractMenuItem
 */
final readonly class ConcreteMenuItem extends AbstractMenuItem {}

#[CoversClass(AbstractMenuItem::class)]
final class AbstractMenuItemTest extends TestCase
{
    #[Test]
    public function it_returns_label(): void
    {
        $item = new ConcreteMenuItem('Test Label');

        self::assertSame('Test Label', $item->getLabel());
    }

    #[Test]
    public function it_returns_explicit_hotkey_in_lowercase(): void
    {
        $item = new ConcreteMenuItem('Test', 'X');

        self::assertSame('x', $item->getHotkey());
    }

    #[Test]
    public function it_returns_first_char_of_label_when_no_hotkey(): void
    {
        $item = new ConcreteMenuItem('Settings');

        self::assertSame('s', $item->getHotkey());
    }

    #[Test]
    public function it_is_not_separator_by_default(): void
    {
        $item = new ConcreteMenuItem('Test');

        self::assertFalse($item->isSeparator());
    }
}
