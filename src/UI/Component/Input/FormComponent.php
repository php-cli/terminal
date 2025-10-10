<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Input;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Form component for collecting command inputs
 */
final class FormComponent extends AbstractComponent
{
    /** @var array<FormField> */
    private array $fields = [];

    private int $focusedFieldIndex = 0;
    private int $scrollOffset = 0;

    /** @var callable|null Callback when form is submitted */
    private $onSubmit = null;

    /** @var callable|null Callback when form is cancelled */
    private $onCancel = null;

    /**
     * Add a text input field
     */
    public function addTextField(string $name, string $label, bool $required = false, mixed $default = null, string $description = ''): void
    {
        $this->fields[] = new TextField($name, $label, $required, $default, $description);
    }

    /**
     * Add a checkbox field
     */
    public function addCheckboxField(string $name, string $label, bool $default = false, string $description = ''): void
    {
        $this->fields[] = new CheckboxField($name, $label, $default, $description);
    }

    /**
     * Add an array input field (comma-separated values)
     */
    public function addArrayField(string $name, string $label, bool $required = false, string $description = ''): void
    {
        $this->fields[] = new ArrayField($name, $label, $required, $description);
    }

    /**
     * Get all form values
     *
     * @return array<string, mixed>
     */
    public function getValues(): array
    {
        $values = [];

        foreach ($this->fields as $field) {
            $values[$field->getName()] = $field->getValue();
        }

        return $values;
    }

    /**
     * Set callback for form submission
     *
     * @param callable(array): void $callback
     */
    public function onSubmit(callable $callback): void
    {
        $this->onSubmit = $callback;
    }

    /**
     * Set callback for form cancellation
     *
     * @param callable(): void $callback
     */
    public function onCancel(callable $callback): void
    {
        $this->onCancel = $callback;
    }

    /**
     * Validate form
     *
     * @return array<string> Error messages
     */
    public function validate(): array
    {
        $errors = [];

        foreach ($this->fields as $field) {
            if ($field instanceof TextField && $field->isRequired() && $field->getValue() === '') {
                $errors[] = "{$field->getLabel()} is required";
            }
        }

        return $errors;
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);

        if (empty($this->fields)) {
            $emptyText = '(No fields)';
            $emptyX = $x + (int) (($width - \mb_strlen($emptyText)) / 2);
            $emptyY = $y + (int) ($height / 2);

            $renderer->writeAt($emptyX, $emptyY, $emptyText, ColorScheme::$NORMAL_TEXT);
            return;
        }

        // Calculate visible range
        $visibleFields = $height - 2; // Reserve space for submit button
        $endIndex = \min($this->scrollOffset + $visibleFields, \count($this->fields));

        // Render fields
        $currentY = $y;

        for ($i = $this->scrollOffset; $i < $endIndex; $i++) {
            $field = $this->fields[$i];
            $isFocused = ($i === $this->focusedFieldIndex && $this->isFocused());

            $fieldHeight = $field->render($renderer, $x, $currentY, $width, $isFocused);
            $currentY += $fieldHeight;

            if ($currentY >= $y + $height - 2) {
                break;
            }
        }

        // Render submit/cancel buttons at bottom
        if ($currentY < $y + $height - 1) {
            $this->renderButtons($renderer, $x, $y + $height - 2, $width);
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        if (empty($this->fields)) {
            return false;
        }

        switch ($key) {
            case 'UP':
                if ($this->focusedFieldIndex > 0) {
                    $this->focusedFieldIndex--;
                    $this->adjustScroll();
                }
                return true;

            case 'DOWN':
            case 'TAB':
                if ($this->focusedFieldIndex < \count($this->fields) - 1) {
                    $this->focusedFieldIndex++;
                    $this->adjustScroll();
                }
                return true;

            case 'F2':
            case 'ENTER':
                // Validate and submit
                $errors = $this->validate();

                if (empty($errors)) {
                    if ($this->onSubmit !== null) {
                        ($this->onSubmit)($this->getValues());
                    }
                }
                return true;

            case 'ESCAPE':
                if ($this->onCancel !== null) {
                    ($this->onCancel)();
                }
                return true;

            default:
                // Delegate to focused field
                $field = $this->fields[$this->focusedFieldIndex];
                return $field->handleInput($key);
        }
    }

    #[\Override]
    public function getMinSize(): array
    {
        return ['width' => 40, 'height' => 10];
    }

    private function renderButtons(Renderer $renderer, int $x, int $y, int $width): void
    {
        $buttonsText = '[F2] Execute   [ESC] Cancel';
        $buttonsX = $x + (int) (($width - \mb_strlen($buttonsText)) / 2);

        $renderer->writeAt(
            $buttonsX,
            $y,
            $buttonsText,
            ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_YELLOW),
        );
    }

    private function adjustScroll(): void
    {
        $visibleFields = $this->height - 2;

        if ($this->focusedFieldIndex < $this->scrollOffset) {
            $this->scrollOffset = $this->focusedFieldIndex;
        } elseif ($this->focusedFieldIndex >= $this->scrollOffset + $visibleFields) {
            $this->scrollOffset = $this->focusedFieldIndex - $visibleFields + 1;
        }
    }
}
