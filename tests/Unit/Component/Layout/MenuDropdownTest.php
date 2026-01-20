<?php

declare(strict_types=1);

namespace Tests\Unit\Component\Layout;

use Butschster\Commander\UI\Component\Layout\MenuDropdown;
use Butschster\Commander\UI\Menu\ActionMenuItem;
use Butschster\Commander\UI\Menu\MenuItemInterface;
use Butschster\Commander\UI\Menu\SeparatorMenuItem;
use Tests\TerminalTestCase;

final class MenuDropdownTest extends TerminalTestCase
{
    // === Default Callback Tests ===

    public function testWorksWithoutSettingCallbacks(): void
    {
        $dropdown = $this->createDropdown();

        // Should not throw - no-op default handles it
        $dropdown->setFocused(true);
        $this->assertTrue(true);
    }

    public function testSelectCurrentItemTriggersOnSelectWithNoOpDefault(): void
    {
        $dropdown = $this->createDropdown();
        $dropdown->setFocused(true);

        // Should not throw when onSelect is not set
        $result = $dropdown->handleInput('ENTER');

        $this->assertTrue($result);
    }

    public function testCloseTriggersOnCloseWithNoOpDefault(): void
    {
        $dropdown = $this->createDropdown();
        $dropdown->setFocused(true);

        // Should not throw when onClose is not set
        $result = $dropdown->handleInput('ESCAPE');

        $this->assertTrue($result);
    }

    // === Custom Callback Tests ===

    public function testOnSelectCallbackIsInvoked(): void
    {
        $items = [
            ActionMenuItem::create('Item 1', static fn() => null, 'a'),
            ActionMenuItem::create('Item 2', static fn() => null, 'b'),
        ];
        $dropdown = new MenuDropdown($items, 0, 1);

        $called = false;
        $receivedItem = null;

        $dropdown->onSelect(static function (MenuItemInterface $item) use (&$called, &$receivedItem): void {
            $called = true;
            $receivedItem = $item;
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('ENTER');

        $this->assertTrue($called);
        $this->assertSame('Item 1', $receivedItem->getLabel());
    }

    public function testOnSelectCallbackReceivesNavigatedItem(): void
    {
        $items = [
            ActionMenuItem::create('First', static fn() => null),
            ActionMenuItem::create('Second', static fn() => null),
            ActionMenuItem::create('Third', static fn() => null),
        ];
        $dropdown = new MenuDropdown($items, 0, 1);

        $receivedItem = null;
        $dropdown->onSelect(static function (MenuItemInterface $item) use (&$receivedItem): void {
            $receivedItem = $item;
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('DOWN');
        $dropdown->handleInput('DOWN');
        $dropdown->handleInput('ENTER');

        $this->assertSame('Third', $receivedItem->getLabel());
    }

    public function testOnCloseCallbackIsInvoked(): void
    {
        $dropdown = $this->createDropdown();

        $called = false;
        $dropdown->onClose(static function () use (&$called): void {
            $called = true;
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('ESCAPE');

        $this->assertTrue($called);
    }

    public function testOnCloseCalledAfterItemSelection(): void
    {
        $items = [ActionMenuItem::create('Test', static fn() => null)];
        $dropdown = new MenuDropdown($items, 0, 1);

        $closeCalled = false;
        $dropdown->onClose(static function () use (&$closeCalled): void {
            $closeCalled = true;
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('ENTER');

        $this->assertTrue($closeCalled);
    }

    // === Multiple Callbacks Tests ===

    public function testBothCallbacksCanBeSetIndependently(): void
    {
        $items = [ActionMenuItem::create('Test', static fn() => null)];
        $dropdown = new MenuDropdown($items, 0, 1);

        $selectCalled = false;
        $closeCalled = false;

        $dropdown->onSelect(static function () use (&$selectCalled): void {
            $selectCalled = true;
        });

        $dropdown->onClose(static function () use (&$closeCalled): void {
            $closeCalled = true;
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('ENTER');

        $this->assertTrue($selectCalled);
        $this->assertTrue($closeCalled);
    }

    // === BC Compatibility Tests ===

    public function testCallbackSetterAcceptsClosure(): void
    {
        $dropdown = $this->createDropdown();

        $dropdown->onSelect(static fn() => null);
        $dropdown->onClose(static fn() => null);

        $this->assertTrue(true); // No exception thrown
    }

    public function testCallbackSetterAcceptsCallableArray(): void
    {
        $dropdown = $this->createDropdown();

        $handler = new class {
            public function handleSelect(MenuItemInterface $item): void {}

            public function handleClose(): void {}
        };

        $dropdown->onSelect($handler->handleSelect(...));
        $dropdown->onClose($handler->handleClose(...));

        $this->assertTrue(true); // No exception thrown
    }

    // === Navigation Tests ===

    public function testNavigateDownMovesSelection(): void
    {
        $items = [
            ActionMenuItem::create('First', static fn() => null),
            ActionMenuItem::create('Second', static fn() => null),
        ];
        $dropdown = new MenuDropdown($items, 0, 1);

        $receivedItem = null;
        $dropdown->onSelect(static function (MenuItemInterface $item) use (&$receivedItem): void {
            $receivedItem = $item;
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('DOWN');
        $dropdown->handleInput('ENTER');

        $this->assertSame('Second', $receivedItem->getLabel());
    }

    public function testNavigateSkipsSeparators(): void
    {
        $items = [
            ActionMenuItem::create('First', static fn() => null),
            SeparatorMenuItem::create(),
            ActionMenuItem::create('Third', static fn() => null),
        ];
        $dropdown = new MenuDropdown($items, 0, 1);

        $receivedItem = null;
        $dropdown->onSelect(static function (MenuItemInterface $item) use (&$receivedItem): void {
            $receivedItem = $item;
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('DOWN');
        $dropdown->handleInput('ENTER');

        $this->assertSame('Third', $receivedItem->getLabel());
    }

    public function testHotkeySelectsItem(): void
    {
        $items = [
            ActionMenuItem::create('Copy', static fn() => null, 'c'),
            ActionMenuItem::create('Paste', static fn() => null, 'p'),
        ];
        $dropdown = new MenuDropdown($items, 0, 1);

        $receivedItem = null;
        $dropdown->onSelect(static function (MenuItemInterface $item) use (&$receivedItem): void {
            $receivedItem = $item;
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('p');

        $this->assertSame('Paste', $receivedItem->getLabel());
    }

    // === Edge Cases ===

    public function testSeparatorCannotBeSelected(): void
    {
        $items = [
            SeparatorMenuItem::create(),
            ActionMenuItem::create('Action', static fn() => null),
        ];
        $dropdown = new MenuDropdown($items, 0, 1);

        $receivedItem = null;
        $dropdown->onSelect(static function (MenuItemInterface $item) use (&$receivedItem): void {
            $receivedItem = $item;
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('ENTER');

        // Should select the action, not the separator
        $this->assertSame('Action', $receivedItem->getLabel());
    }

    public function testSpaceKeySelectsItem(): void
    {
        $items = [ActionMenuItem::create('Test', static fn() => null)];
        $dropdown = new MenuDropdown($items, 0, 1);

        $called = false;
        $dropdown->onSelect(static function () use (&$called): void {
            $called = true;
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput(' '); // Space character

        $this->assertTrue($called);
    }

    // === Helper Methods ===

    private function createDropdown(): MenuDropdown
    {
        return new MenuDropdown([
            ActionMenuItem::create('Option 1', static fn() => null, '1'),
            ActionMenuItem::create('Option 2', static fn() => null, '2'),
            ActionMenuItem::create('Option 3', static fn() => null, '3'),
        ], 0, 1);
    }
}
