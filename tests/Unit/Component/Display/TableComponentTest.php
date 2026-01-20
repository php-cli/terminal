<?php

declare(strict_types=1);

namespace Tests\Unit\Component\Display;

use Butschster\Commander\UI\Component\Display\TableColumn;
use Butschster\Commander\UI\Component\Display\TableComponent;
use Tests\TerminalTestCase;

final class TableComponentTest extends TerminalTestCase
{
    // === Default Callback Tests ===

    public function testWorksWithoutSettingCallbacks(): void
    {
        $table = $this->createTable();
        $table->setRows([['name' => 'test', 'value' => '123']]);

        // Should not throw - no-op default handles it
        $this->assertSame(['name' => 'test', 'value' => '123'], $table->getSelectedRow());
    }

    public function testSetRowsTriggersOnChangeWithNoOpDefault(): void
    {
        $table = $this->createTable();

        // Should not throw when onChange is not set
        $table->setRows([
            ['name' => 'first', 'value' => '1'],
            ['name' => 'second', 'value' => '2'],
        ]);

        $this->assertSame(0, $table->getSelectedIndex());
    }

    public function testSetSelectedIndexTriggersOnChangeWithNoOpDefault(): void
    {
        $table = $this->createTable();
        $table->setRows([
            ['name' => 'first', 'value' => '1'],
            ['name' => 'second', 'value' => '2'],
        ]);

        // Should not throw when onChange is not set
        $table->setSelectedIndex(1);

        $this->assertSame(1, $table->getSelectedIndex());
    }

    public function testHandleEnterTriggersOnSelectWithNoOpDefault(): void
    {
        $table = $this->createTable();
        $table->setRows([['name' => 'test', 'value' => '123']]);
        $table->setFocused(true);

        // Should not throw when onSelect is not set
        $result = $table->handleInput('ENTER');

        $this->assertTrue($result);
    }

    // === Custom Callback Tests ===

    public function testOnSelectCallbackIsInvoked(): void
    {
        $table = $this->createTable();
        $table->setRows([['name' => 'test', 'value' => '123']]);

        $called = false;
        $receivedRow = null;
        $receivedIndex = null;

        $table->onSelect(static function (array $row, int $index) use (&$called, &$receivedRow, &$receivedIndex): void {
            $called = true;
            $receivedRow = $row;
            $receivedIndex = $index;
        });

        $table->setFocused(true);
        $table->handleInput('ENTER');

        $this->assertTrue($called);
        $this->assertSame(['name' => 'test', 'value' => '123'], $receivedRow);
        $this->assertSame(0, $receivedIndex);
    }

    public function testOnChangeCallbackIsInvokedOnNavigation(): void
    {
        $table = $this->createTable();
        $table->setRows([
            ['name' => 'first', 'value' => '1'],
            ['name' => 'second', 'value' => '2'],
        ]);

        $calls = [];
        $table->onChange(static function (array $row, int $index) use (&$calls): void {
            $calls[] = ['row' => $row, 'index' => $index];
        });

        $table->setFocused(true);
        $table->handleInput('DOWN');

        $this->assertCount(1, $calls);
        $this->assertSame(['name' => 'second', 'value' => '2'], $calls[0]['row']);
        $this->assertSame(1, $calls[0]['index']);
    }

    public function testOnChangeCallbackIsInvokedOnSetRows(): void
    {
        $table = $this->createTable();

        $called = false;
        $table->onChange(static function () use (&$called): void {
            $called = true;
        });

        $table->setRows([['name' => 'test', 'value' => '123']]);

        $this->assertTrue($called);
    }

    public function testOnChangeCallbackIsInvokedOnSetSelectedIndex(): void
    {
        $table = $this->createTable();
        $table->setRows([
            ['name' => 'first', 'value' => '1'],
            ['name' => 'second', 'value' => '2'],
        ]);

        $calls = [];
        $table->onChange(static function (array $row, int $index) use (&$calls): void {
            $calls[] = ['row' => $row, 'index' => $index];
        });

        $table->setSelectedIndex(1);

        $this->assertCount(1, $calls);
        $this->assertSame(1, $calls[0]['index']);
    }

    public function testOnChangeNotCalledWhenIndexDoesNotChange(): void
    {
        $table = $this->createTable();
        $table->setRows([
            ['name' => 'first', 'value' => '1'],
            ['name' => 'second', 'value' => '2'],
        ]);

        $callCount = 0;
        $table->onChange(static function () use (&$callCount): void {
            $callCount++;
        });

        $table->setFocused(true);
        // Try to go up when already at top - should not trigger onChange
        $table->handleInput('UP');

        $this->assertSame(0, $callCount);
    }

    // === Multiple Callbacks Tests ===

    public function testBothCallbacksCanBeSetIndependently(): void
    {
        $table = $this->createTable();
        $table->setRows([
            ['name' => 'first', 'value' => '1'],
            ['name' => 'second', 'value' => '2'],
        ]);

        $selectCalled = false;
        $changeCalled = false;

        $table->onSelect(static function () use (&$selectCalled): void {
            $selectCalled = true;
        });

        $table->onChange(static function () use (&$changeCalled): void {
            $changeCalled = true;
        });

        $table->setFocused(true);

        // Navigate down - triggers onChange
        $table->handleInput('DOWN');
        $this->assertTrue($changeCalled);
        $this->assertFalse($selectCalled);

        // Press Enter - triggers onSelect
        $table->handleInput('ENTER');
        $this->assertTrue($selectCalled);
    }

    // === BC Compatibility Tests ===

    public function testCallbackSetterAcceptsClosure(): void
    {
        $table = $this->createTable();

        $table->onSelect(static fn() => null);
        $table->onChange(static fn() => null);

        $this->assertTrue(true); // No exception thrown
    }

    public function testCallbackSetterAcceptsCallableArray(): void
    {
        $table = $this->createTable();

        $handler = new class {
            public function handle(array $row, int $index): void {}
        };

        $table->onSelect($handler->handle(...));
        $table->onChange($handler->handle(...));

        $this->assertTrue(true); // No exception thrown
    }

    // === Edge Cases ===

    public function testEmptyRowsDoNotTriggerOnChange(): void
    {
        $table = $this->createTable();

        $called = false;
        $table->onChange(static function () use (&$called): void {
            $called = true;
        });

        $table->setRows([]);

        $this->assertFalse($called);
    }

    public function testNavigationOnEmptyTableDoesNotThrow(): void
    {
        $table = $this->createTable();
        $table->setRows([]);
        $table->setFocused(true);

        $result = $table->handleInput('DOWN');

        $this->assertFalse($result);
    }

    public function testEnterOnEmptyTableDoesNotThrow(): void
    {
        $table = $this->createTable();
        $table->setRows([]);
        $table->setFocused(true);

        $result = $table->handleInput('ENTER');

        $this->assertFalse($result);
    }

    // === Helper Methods ===

    private function createTable(): TableComponent
    {
        return new TableComponent([
            new TableColumn('name', 'Name', '*'),
            new TableColumn('value', 'Value', 20),
        ]);
    }
}
