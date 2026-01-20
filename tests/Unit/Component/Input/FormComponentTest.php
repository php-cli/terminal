<?php

declare(strict_types=1);

namespace Tests\Unit\Component\Input;

use Butschster\Commander\UI\Component\Input\FormComponent;
use Tests\TerminalTestCase;

final class FormComponentTest extends TerminalTestCase
{
    // === Default Callback Tests ===

    public function testWorksWithoutSettingCallbacks(): void
    {
        $form = new FormComponent();
        $form->addTextField('name', 'Name');

        // Should not throw - no-op default handles it
        $this->assertSame(['name' => ''], $form->getValues());
    }

    public function testHandleSubmitTriggersOnSubmitWithNoOpDefault(): void
    {
        $form = new FormComponent();
        $form->addTextField('name', 'Name', required: false);
        $form->setFocused(true);

        // Should not throw when onSubmit is not set
        $result = $form->handleInput('ENTER');

        $this->assertTrue($result);
    }

    public function testHandleCancelTriggersOnCancelWithNoOpDefault(): void
    {
        $form = new FormComponent();
        $form->addTextField('name', 'Name');
        $form->setFocused(true);

        // Should not throw when onCancel is not set
        $result = $form->handleInput('ESCAPE');

        $this->assertTrue($result);
    }

    // === Custom Callback Tests ===

    public function testOnSubmitCallbackIsInvoked(): void
    {
        $form = new FormComponent();
        $form->addTextField('username', 'Username', required: false, default: 'john');
        $form->addTextField('email', 'Email', required: false, default: 'john@test.com');

        $called = false;
        $receivedValues = null;

        $form->onSubmit(static function (array $values) use (&$called, &$receivedValues): void {
            $called = true;
            $receivedValues = $values;
        });

        $form->setFocused(true);
        $form->handleInput('ENTER');

        $this->assertTrue($called);
        $this->assertSame([
            'username' => 'john',
            'email' => 'john@test.com',
        ], $receivedValues);
    }

    public function testOnCancelCallbackIsInvoked(): void
    {
        $form = new FormComponent();
        $form->addTextField('name', 'Name');

        $called = false;

        $form->onCancel(static function () use (&$called): void {
            $called = true;
        });

        $form->setFocused(true);
        $form->handleInput('ESCAPE');

        $this->assertTrue($called);
    }

    public function testOnSubmitNotCalledOnValidationFailure(): void
    {
        $form = new FormComponent();
        $form->addTextField('name', 'Name', required: true); // Required but empty

        $called = false;
        $form->onSubmit(static function () use (&$called): void {
            $called = true;
        });

        $form->setFocused(true);
        $form->handleInput('ENTER');

        // Should not be called because validation fails
        $this->assertFalse($called);
    }

    // === Multiple Callbacks Tests ===

    public function testBothCallbacksCanBeSetIndependently(): void
    {
        $form = new FormComponent();
        $form->addTextField('name', 'Name', required: false);

        $submitCalled = false;
        $cancelCalled = false;

        $form->onSubmit(static function () use (&$submitCalled): void {
            $submitCalled = true;
        });

        $form->onCancel(static function () use (&$cancelCalled): void {
            $cancelCalled = true;
        });

        $form->setFocused(true);

        // Press Escape - triggers onCancel
        $form->handleInput('ESCAPE');
        $this->assertTrue($cancelCalled);
        $this->assertFalse($submitCalled);

        // Press Enter - triggers onSubmit
        $form->handleInput('ENTER');
        $this->assertTrue($submitCalled);
    }

    // === BC Compatibility Tests ===

    public function testCallbackSetterAcceptsClosure(): void
    {
        $form = new FormComponent();

        $form->onSubmit(static fn() => null);
        $form->onCancel(static fn() => null);

        $this->assertTrue(true); // No exception thrown
    }

    public function testCallbackSetterAcceptsCallableArray(): void
    {
        $form = new FormComponent();

        $handler = new class {
            public function handleSubmit(array $values): void {}

            public function handleCancel(): void {}
        };

        $form->onSubmit($handler->handleSubmit(...));
        $form->onCancel($handler->handleCancel(...));

        $this->assertTrue(true); // No exception thrown
    }

    // === Edge Cases ===

    public function testEmptyFormSubmits(): void
    {
        $form = new FormComponent();

        $called = false;
        $form->onSubmit(static function (array $values) use (&$called): void {
            $called = true;
        });

        $form->setFocused(true);

        // Empty form - handleInput returns false for empty fields
        $result = $form->handleInput('ENTER');

        $this->assertFalse($result); // No fields to process
    }

    public function testFormWithCheckboxField(): void
    {
        $form = new FormComponent();
        $form->addCheckboxField('agree', 'I agree', default: true);

        $receivedValues = null;
        $form->onSubmit(static function (array $values) use (&$receivedValues): void {
            $receivedValues = $values;
        });

        $form->setFocused(true);
        $form->handleInput('ENTER');

        $this->assertSame(['agree' => true], $receivedValues);
    }

    public function testFormWithArrayField(): void
    {
        $form = new FormComponent();
        $form->addArrayField('tags', 'Tags', required: false);

        $receivedValues = null;
        $form->onSubmit(static function (array $values) use (&$receivedValues): void {
            $receivedValues = $values;
        });

        $form->setFocused(true);
        $form->handleInput('ENTER');

        $this->assertArrayHasKey('tags', $receivedValues);
    }

    // === Validation Tests ===

    public function testValidationReturnsErrorsForRequiredFields(): void
    {
        $form = new FormComponent();
        $form->addTextField('name', 'Name', required: true);
        $form->addTextField('email', 'Email', required: true);

        $errors = $form->validate();

        $this->assertCount(2, $errors);
        $this->assertContains('Name is required', $errors);
        $this->assertContains('Email is required', $errors);
    }

    public function testValidationPassesWithFilledRequiredFields(): void
    {
        $form = new FormComponent();
        $form->addTextField('name', 'Name', required: true, default: 'John');

        $errors = $form->validate();

        $this->assertEmpty($errors);
    }
}
