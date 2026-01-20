<?php

declare(strict_types=1);

namespace Tests\Unit\Component\Display;

use Butschster\Commander\UI\Component\Display\ListComponent;
use Tests\TerminalTestCase;

final class ListComponentTest extends TerminalTestCase
{
    // === Default Callback Tests ===

    public function testWorksWithoutSettingCallbacks(): void
    {
        $list = new ListComponent(['item1', 'item2', 'item3']);

        // Should not throw - no-op default handles it
        $this->assertSame('item1', $list->getSelectedItem());
    }

    public function testSetItemsTriggersOnChangeWithNoOpDefault(): void
    {
        $list = new ListComponent();

        // Should not throw when onChange is not set
        $list->setItems(['first', 'second', 'third']);

        $this->assertSame(0, $list->getSelectedIndex());
    }

    public function testHandleEnterTriggersOnSelectWithNoOpDefault(): void
    {
        $list = new ListComponent(['item1', 'item2']);
        $list->setFocused(true);

        // Should not throw when onSelect is not set
        $result = $list->handleInput('ENTER');

        $this->assertTrue($result);
    }

    // === Custom Callback Tests ===

    public function testOnSelectCallbackIsInvoked(): void
    {
        $list = new ListComponent(['item1', 'item2', 'item3']);

        $called = false;
        $receivedItem = null;
        $receivedIndex = null;

        $list->onSelect(static function (string $item, int $index) use (&$called, &$receivedItem, &$receivedIndex): void {
            $called = true;
            $receivedItem = $item;
            $receivedIndex = $index;
        });

        $list->setFocused(true);
        $list->handleInput('ENTER');

        $this->assertTrue($called);
        $this->assertSame('item1', $receivedItem);
        $this->assertSame(0, $receivedIndex);
    }

    public function testOnChangeCallbackIsInvokedOnNavigation(): void
    {
        $list = new ListComponent(['first', 'second', 'third']);

        $calls = [];
        $list->onChange(static function (?string $item, int $index) use (&$calls): void {
            $calls[] = ['item' => $item, 'index' => $index];
        });

        $list->setFocused(true);
        $list->handleInput('DOWN');

        $this->assertCount(1, $calls);
        $this->assertSame('second', $calls[0]['item']);
        $this->assertSame(1, $calls[0]['index']);
    }

    public function testOnChangeCallbackIsInvokedOnSetItems(): void
    {
        $list = new ListComponent();

        $called = false;
        $receivedItem = null;

        $list->onChange(static function (?string $item, int $index) use (&$called, &$receivedItem): void {
            $called = true;
            $receivedItem = $item;
        });

        $list->setItems(['test']);

        $this->assertTrue($called);
        $this->assertSame('test', $receivedItem);
    }

    public function testOnChangeNotCalledWhenIndexDoesNotChange(): void
    {
        $list = new ListComponent(['first', 'second']);

        $callCount = 0;
        $list->onChange(static function () use (&$callCount): void {
            $callCount++;
        });

        $list->setFocused(true);
        // Try to go up when already at top - should not trigger onChange
        $list->handleInput('UP');

        $this->assertSame(0, $callCount);
    }

    // === Multiple Callbacks Tests ===

    public function testBothCallbacksCanBeSetIndependently(): void
    {
        $list = new ListComponent(['first', 'second']);

        $selectCalled = false;
        $changeCalled = false;

        $list->onSelect(static function () use (&$selectCalled): void {
            $selectCalled = true;
        });

        $list->onChange(static function () use (&$changeCalled): void {
            $changeCalled = true;
        });

        $list->setFocused(true);

        // Navigate down - triggers onChange
        $list->handleInput('DOWN');
        $this->assertTrue($changeCalled);
        $this->assertFalse($selectCalled);

        // Press Enter - triggers onSelect
        $list->handleInput('ENTER');
        $this->assertTrue($selectCalled);
    }

    // === BC Compatibility Tests ===

    public function testCallbackSetterAcceptsClosure(): void
    {
        $list = new ListComponent();

        $list->onSelect(static fn() => null);
        $list->onChange(static fn() => null);

        $this->assertTrue(true); // No exception thrown
    }

    public function testCallbackSetterAcceptsCallableArray(): void
    {
        $list = new ListComponent();

        $handler = new class {
            public function handleSelect(string $item, int $index): void {}

            public function handleChange(?string $item, int $index): void {}
        };

        $list->onSelect($handler->handleSelect(...));
        $list->onChange($handler->handleChange(...));

        $this->assertTrue(true); // No exception thrown
    }

    // === Edge Cases ===

    public function testOnSelectNotCalledWhenItemIsNull(): void
    {
        $list = new ListComponent([]);

        $called = false;
        $list->onSelect(static function () use (&$called): void {
            $called = true;
        });

        $list->setFocused(true);
        $list->handleInput('ENTER');

        // onSelect should not be called when there's no selected item
        $this->assertFalse($called);
    }

    public function testOnChangeCalledWithNullOnEmptyList(): void
    {
        $list = new ListComponent();

        $receivedItem = 'not-null';
        $list->onChange(static function (?string $item, int $index) use (&$receivedItem): void {
            $receivedItem = $item;
        });

        $list->setItems([]);

        $this->assertNull($receivedItem);
    }

    public function testNavigationOnEmptyListDoesNotThrow(): void
    {
        $list = new ListComponent([]);
        $list->setFocused(true);

        // Should not throw
        $list->handleInput('DOWN');
        $list->handleInput('UP');

        $this->assertTrue(true);
    }

    // === Navigation Tests ===

    public function testNavigateDownUpdatesSelection(): void
    {
        $list = new ListComponent(['a', 'b', 'c']);
        $list->setFocused(true);

        $list->handleInput('DOWN');

        $this->assertSame(1, $list->getSelectedIndex());
        $this->assertSame('b', $list->getSelectedItem());
    }

    public function testNavigateUpUpdatesSelection(): void
    {
        $list = new ListComponent(['a', 'b', 'c']);
        $list->setFocused(true);

        $list->handleInput('DOWN');
        $list->handleInput('DOWN');
        $list->handleInput('UP');

        $this->assertSame(1, $list->getSelectedIndex());
        $this->assertSame('b', $list->getSelectedItem());
    }
}
