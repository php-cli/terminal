<?php

declare(strict_types=1);

namespace Tests\Unit\Component\Layout;

use Butschster\Commander\UI\Component\Layout\Modal;
use Tests\TerminalTestCase;

final class ModalTest extends TerminalTestCase
{
    // === Default Callback Tests ===

    public function testWorksWithoutSettingCallback(): void
    {
        $modal = new Modal('Title', 'Content');

        // Should not throw - no-op default handles it
        $modal->setFocused(true);
        $this->assertTrue(true);
    }

    public function testCloseTriggersOnCloseWithNoOpDefault(): void
    {
        $modal = new Modal('Test', 'Content');
        $modal->setFocused(true);

        // Should not throw when onClose is not set
        // Press Enter to activate OK button which calls close()
        $result = $modal->handleInput('ENTER');

        $this->assertTrue($result);
    }

    // === Custom Callback Tests ===

    public function testOnCloseCallbackIsInvoked(): void
    {
        $modal = new Modal('Test', 'Content');

        $called = false;
        $receivedResult = 'not-called';

        $modal->onClose(static function (mixed $result) use (&$called, &$receivedResult): void {
            $called = true;
            $receivedResult = $result;
        });

        $modal->setFocused(true);
        $modal->handleInput('ENTER'); // Activates OK button

        $this->assertTrue($called);
        $this->assertNull($receivedResult); // Info modal passes null
    }

    public function testConfirmModalPassesTrueOnYes(): void
    {
        $modal = Modal::confirm('Confirm', 'Are you sure?');

        $receivedResult = null;
        $modal->onClose(static function (mixed $result) use (&$receivedResult): void {
            $receivedResult = $result;
        });

        $modal->setFocused(true);
        $modal->handleInput('ENTER'); // Yes is first button

        $this->assertTrue($receivedResult);
    }

    public function testConfirmModalPassesFalseOnNo(): void
    {
        $modal = Modal::confirm('Confirm', 'Are you sure?');

        $receivedResult = null;
        $modal->onClose(static function (mixed $result) use (&$receivedResult): void {
            $receivedResult = $result;
        });

        $modal->setFocused(true);
        $modal->handleInput('RIGHT'); // Navigate to No button
        $modal->handleInput('ENTER');

        $this->assertFalse($receivedResult);
    }

    public function testEscapeTriggersLastButton(): void
    {
        $modal = Modal::confirm('Confirm', 'Are you sure?');

        $receivedResult = null;
        $modal->onClose(static function (mixed $result) use (&$receivedResult): void {
            $receivedResult = $result;
        });

        $modal->setFocused(true);
        $modal->handleInput('ESCAPE'); // Should activate last button (No)

        $this->assertFalse($receivedResult);
    }

    // === BC Compatibility Tests ===

    public function testCallbackSetterAcceptsClosure(): void
    {
        $modal = new Modal('Test', 'Content');

        $modal->onClose(static fn() => null);

        $this->assertTrue(true); // No exception thrown
    }

    public function testCallbackSetterAcceptsCallableArray(): void
    {
        $modal = new Modal('Test', 'Content');

        $handler = new class {
            public function handleClose(mixed $result): void {}
        };

        $modal->onClose($handler->handleClose(...));

        $this->assertTrue(true); // No exception thrown
    }

    // === Factory Methods Tests ===

    public function testErrorModalHasOkButton(): void
    {
        $modal = Modal::error('Error', 'Something went wrong');

        $called = false;
        $modal->onClose(static function () use (&$called): void {
            $called = true;
        });

        $modal->setFocused(true);
        $modal->handleInput('ENTER');

        $this->assertTrue($called);
    }

    public function testWarningModalHasOkButton(): void
    {
        $modal = Modal::warning('Warning', 'Be careful');

        $called = false;
        $modal->onClose(static function () use (&$called): void {
            $called = true;
        });

        $modal->setFocused(true);
        $modal->handleInput('ENTER');

        $this->assertTrue($called);
    }

    public function testInfoModalHasOkButton(): void
    {
        $modal = Modal::info('Info', 'Just letting you know');

        $called = false;
        $modal->onClose(static function () use (&$called): void {
            $called = true;
        });

        $modal->setFocused(true);
        $modal->handleInput('ENTER');

        $this->assertTrue($called);
    }

    // === Custom Buttons Tests ===

    public function testCustomButtonsWork(): void
    {
        $modal = new Modal('Custom', 'Choose an option');

        $selectedOption = null;

        $modal->setButtons([
            'Save' => static function () use (&$selectedOption): void {
                $selectedOption = 'save';
            },
            'Discard' => static function () use (&$selectedOption): void {
                $selectedOption = 'discard';
            },
            'Cancel' => static function () use (&$selectedOption): void {
                $selectedOption = 'cancel';
            },
        ]);

        $modal->setFocused(true);
        $modal->handleInput('RIGHT'); // Navigate to Discard
        $modal->handleInput('ENTER');

        $this->assertSame('discard', $selectedOption);
    }

    public function testAddButtonAppends(): void
    {
        $modal = new Modal('Test', 'Content');

        $customCalled = false;
        $modal->addButton('Custom', static function () use (&$customCalled): void {
            $customCalled = true;
        });

        $modal->setFocused(true);
        $modal->handleInput('RIGHT'); // Navigate past OK to Custom
        $modal->handleInput('ENTER');

        $this->assertTrue($customCalled);
    }

    // === Digit Key Tests ===

    public function testDigitKeySelectsButton(): void
    {
        $modal = new Modal('Test', 'Content');

        $secondCalled = false;
        $modal->setButtons([
            'First' => static fn() => null,
            'Second' => static function () use (&$secondCalled): void {
                $secondCalled = true;
            },
        ]);

        $modal->setFocused(true);
        $modal->handleInput('2'); // Press '2' to select second button

        $this->assertTrue($secondCalled);
    }

    // === Navigation Tests ===

    public function testLeftNavigatesButtons(): void
    {
        $modal = Modal::confirm('Test', 'Content');

        $receivedResult = null;
        $modal->onClose(static function (mixed $result) use (&$receivedResult): void {
            $receivedResult = $result;
        });

        $modal->setFocused(true);
        $modal->handleInput('RIGHT'); // Go to No
        $modal->handleInput('LEFT');  // Go back to Yes
        $modal->handleInput('ENTER');

        $this->assertTrue($receivedResult);
    }

    public function testTabNavigatesButtons(): void
    {
        $modal = Modal::confirm('Test', 'Content');

        $receivedResult = null;
        $modal->onClose(static function (mixed $result) use (&$receivedResult): void {
            $receivedResult = $result;
        });

        $modal->setFocused(true);
        $modal->handleInput('TAB'); // Navigate to No
        $modal->handleInput('ENTER');

        $this->assertFalse($receivedResult);
    }

    // === Edge Cases ===

    public function testSpaceKeyActivatesButton(): void
    {
        $modal = new Modal('Test', 'Content');

        $called = false;
        $modal->onClose(static function () use (&$called): void {
            $called = true;
        });

        $modal->setFocused(true);
        $modal->handleInput(' '); // Space character

        $this->assertTrue($called);
    }

    public function testEmptyButtonsReturnsEarly(): void
    {
        $modal = new Modal('Test', 'Content');
        $modal->setButtons([]);

        $modal->setFocused(true);
        $result = $modal->handleInput('ENTER');

        $this->assertFalse($result);
    }

    public function testSetSizeEnforcesMinimums(): void
    {
        $modal = new Modal('Test', 'Content');

        $modal->setSize(10, 5); // Below minimums (30, 10)

        $minSize = $modal->getMinSize();

        // Modal should enforce minimum dimensions
        $this->assertGreaterThanOrEqual(30, $minSize['width'] - 10); // modalWidth >= 30
    }

    public function testMultilineContent(): void
    {
        $content = "Line 1\nLine 2\nLine 3";
        $modal = new Modal('Test', $content);

        $called = false;
        $modal->onClose(static function () use (&$called): void {
            $called = true;
        });

        $modal->setFocused(true);
        $modal->handleInput('ENTER');

        $this->assertTrue($called);
    }
}
