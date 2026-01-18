<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\ComposerManager\Component;

use Butschster\Commander\Feature\ComposerManager\Component\SearchFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchFilter::class)]
final class SearchFilterTest extends TestCase
{
    #[Test]
    public function isActive_returns_false_initially(): void
    {
        $filter = new SearchFilter(static fn() => null);

        self::assertFalse($filter->isActive());
    }

    #[Test]
    public function activate_enables_input_mode(): void
    {
        $filter = new SearchFilter(static fn() => null);

        $filter->activate();

        self::assertTrue($filter->isActive());
    }

    #[Test]
    public function deactivate_disables_input_mode(): void
    {
        $filter = new SearchFilter(static fn() => null);
        $filter->activate();

        $filter->deactivate();

        self::assertFalse($filter->isActive());
    }

    #[Test]
    public function typing_updates_query(): void
    {
        $receivedQuery = '';
        $filter = new SearchFilter(static function (string $query) use (&$receivedQuery): void {
            $receivedQuery = $query;
        });

        $filter->activate();
        $filter->handleInput('s');
        $filter->handleInput('y');
        $filter->handleInput('m');

        self::assertSame('sym', $filter->getQuery());
        self::assertSame('sym', $receivedQuery);
    }

    #[Test]
    public function backspace_removes_character(): void
    {
        $filter = new SearchFilter(static fn() => null);
        $filter->activate();
        $filter->handleInput('a');
        $filter->handleInput('b');
        $filter->handleInput('c');

        $filter->handleInput('BACKSPACE');

        self::assertSame('ab', $filter->getQuery());
    }

    #[Test]
    public function escape_clears_query_first(): void
    {
        $filter = new SearchFilter(static fn() => null);
        $filter->activate();
        $filter->handleInput('t');
        $filter->handleInput('e');

        $filter->handleInput('ESCAPE');

        self::assertSame('', $filter->getQuery());
        self::assertTrue($filter->isActive()); // Still active
    }

    #[Test]
    public function escape_on_empty_deactivates(): void
    {
        $filter = new SearchFilter(static fn() => null);
        $filter->activate();

        $filter->handleInput('ESCAPE');

        self::assertFalse($filter->isActive());
    }

    #[Test]
    public function enter_deactivates(): void
    {
        $filter = new SearchFilter(static fn() => null);
        $filter->activate();
        $filter->handleInput('x');

        $filter->handleInput('ENTER');

        self::assertFalse($filter->isActive());
        self::assertSame('x', $filter->getQuery()); // Query preserved
    }

    #[Test]
    public function clear_resets_query_and_calls_callback(): void
    {
        $callbackCalled = false;
        $filter = new SearchFilter(static function (string $query) use (&$callbackCalled): void {
            if ($query === '') {
                $callbackCalled = true;
            }
        });

        $filter->activate();
        $filter->handleInput('t');
        $filter->clear();

        self::assertSame('', $filter->getQuery());
        self::assertTrue($callbackCalled);
    }

    #[Test]
    public function hasFilter_returns_true_when_query_not_empty(): void
    {
        $filter = new SearchFilter(static fn() => null);
        $filter->activate();
        $filter->handleInput('x');

        self::assertTrue($filter->hasFilter());
    }

    #[Test]
    public function hasFilter_returns_false_when_query_empty(): void
    {
        $filter = new SearchFilter(static fn() => null);

        self::assertFalse($filter->hasFilter());
    }

    #[Test]
    public function callback_fires_on_each_keystroke(): void
    {
        $callCount = 0;
        $filter = new SearchFilter(static function () use (&$callCount): void {
            $callCount++;
        });

        $filter->activate();
        $filter->handleInput('a');
        $filter->handleInput('b');
        $filter->handleInput('c');

        self::assertSame(3, $callCount);
    }

    #[Test]
    public function handleInput_returns_false_when_not_active(): void
    {
        $filter = new SearchFilter(static fn() => null);

        $result = $filter->handleInput('a');

        self::assertFalse($result);
    }

    #[Test]
    public function getMinSize_returns_expected_dimensions(): void
    {
        $filter = new SearchFilter(static fn() => null);

        $size = $filter->getMinSize();

        self::assertSame(['width' => 20, 'height' => 1], $size);
    }

    #[Test]
    public function isFocused_returns_true_when_active(): void
    {
        $filter = new SearchFilter(static fn() => null);
        $filter->activate();

        self::assertTrue($filter->isFocused());
    }

    #[Test]
    public function isFocused_returns_false_when_not_active_and_not_focused(): void
    {
        $filter = new SearchFilter(static fn() => null);

        self::assertFalse($filter->isFocused());
    }

    #[Test]
    public function setFocused_changes_focus_state(): void
    {
        $filter = new SearchFilter(static fn() => null);

        $filter->setFocused(true);

        self::assertTrue($filter->isFocused());
    }
}
