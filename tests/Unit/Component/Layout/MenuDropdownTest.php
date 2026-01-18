<?php

declare(strict_types=1);

namespace Tests\Unit\Component\Layout;

use Butschster\Commander\UI\Component\Layout\MenuDropdown;
use Butschster\Commander\UI\Menu\MenuItem;
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
            MenuItem::action('Item 1', static fn() => null, 'a'),
            MenuItem::action('Item 2', static fn() => null, 'b'),
        ];
        $dropdown = new MenuDropdown($items, 0, 1);

        $called = false;
        $receivedItem = null;

        $dropdown->onSelect(static function (MenuItem $item) use (&$called, &$receivedItem): void {
            $called = true;
            $receivedItem = $item;
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('ENTER');

        $this->assertTrue($called);
        $this->assertSame('Item 1', $receivedItem->label);
    }

    public function testOnSelectCallbackReceivesNavigatedItem(): void
    {
        $items = [
            MenuItem::action('First', static fn() => null),
            MenuItem::action('Second', static fn() => null),
            MenuItem::action('Third', static fn() => null),
        ];
        $dropdown = new MenuDropdown($items, 0, 1);

        $receivedItem = null;
        $dropdown->onSelect(static function (MenuItem $item) use (&$receivedItem): void {
            $receivedItem = $item;
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('DOWN');
        $dropdown->handleInput('DOWN');
        $dropdown->handleInput('ENTER');

        $this->assertSame('Third', $receivedItem->label);
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
        $items = [MenuItem::action('Test', static fn() => null)];
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
        $items = [MenuItem::action('Test', static fn() => null)];
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
            public function handleSelect(MenuItem $item): void {}

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
            MenuItem::action('First', static fn() => null),
            MenuItem::action('Second', static fn() => null),
        ];
        $dropdown = new MenuDropdown($items, 0, 1);

        $receivedItem = null;
        $dropdown->onSelect(static function (MenuItem $item) use (&$receivedItem): void {
            $receivedItem = $item;
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('DOWN');
        $dropdown->handleInput('ENTER');

        $this->assertSame('Second', $receivedItem->label);
    }

    public function testNavigateSkipsSeparators(): void
    {
        $items = [
            MenuItem::action('First', static fn() => null),
            MenuItem::separator(),
            MenuItem::action('Third', static fn() => null),
        ];
        $dropdown = new MenuDropdown($items, 0, 1);

        $receivedItem = null;
        $dropdown->onSelect(static function (MenuItem $item) use (&$receivedItem): void {
            $receivedItem = $item;
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('DOWN');
        $dropdown->handleInput('ENTER');

        $this->assertSame('Third', $receivedItem->label);
    }

    public function testHotkeySelectsItem(): void
    {
        $items = [
            MenuItem::action('Copy', static fn() => null, 'c'),
            MenuItem::action('Paste', static fn() => null, 'p'),
        ];
        $dropdown = new MenuDropdown($items, 0, 1);

        $receivedItem = null;
        $dropdown->onSelect(static function (MenuItem $item) use (&$receivedItem): void {
            $receivedItem = $item;
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('p');

        $this->assertSame('Paste', $receivedItem->label);
    }

    // === Edge Cases ===

    public function testSeparatorCannotBeSelected(): void
    {
        $items = [
            MenuItem::separator(),
            MenuItem::action('Action', static fn() => null),
        ];
        $dropdown = new MenuDropdown($items, 0, 1);

        $receivedItem = null;
        $dropdown->onSelect(static function (MenuItem $item) use (&$receivedItem): void {
            $receivedItem = $item;
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('ENTER');

        // Should select the action, not the separator
        $this->assertSame('Action', $receivedItem->label);
    }

    public function testSpaceKeySelectsItem(): void
    {
        $items = [MenuItem::action('Test', static fn() => null)];
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
            MenuItem::action('Option 1', static fn() => null, '1'),
            MenuItem::action('Option 2', static fn() => null, '2'),
            MenuItem::action('Option 3', static fn() => null, '3'),
        ], 0, 1);
    }
}
