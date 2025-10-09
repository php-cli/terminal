<?php

declare(strict_types=1);

namespace Butschster\Commander\Component;

use Butschster\Commander\Service\Renderer;
use Butschster\Commander\Theme\ColorScheme;

/**
 * Checkbox field
 */
final class CheckboxField extends FormField
{
    public function __construct(
        string $name,
        string $label,
        bool $default = false,
    ) {
        parent::__construct($name, $label, $default);
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, bool $focused): int
    {
        $checkbox = $this->value ? '[X]' : '[ ]';
        $text = $checkbox . ' ' . $this->label;

        $color = $focused
            ? ColorScheme::SELECTED_TEXT
            : ColorScheme::NORMAL_TEXT;

        $renderer->writeAt($x, $y, $text, $color);

        return 2; // Checkbox + spacing
    }

    public function handleInput(string $key): bool
    {
        if ($key === ' ' || $key === 'ENTER') {
            $this->value = !$this->value;
            return true;
        }

        return false;
    }
}
