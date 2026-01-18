<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Input;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Checkbox field
 */
final class CheckboxField extends FormField
{
    public function __construct(
        string $name,
        string $label,
        bool $default = false,
        string $description = '',
    ) {
        parent::__construct($name, $label, $default, $description);
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, bool $focused): int
    {
        $currentY = $y;

        $checkbox = $this->value ? '[X]' : '[ ]';

        $color = $focused
            ? ColorScheme::$SELECTED_TEXT
            : ColorScheme::$NORMAL_TEXT;

        // Render description first (if present)
        if ($this->description !== '') {
            $descText = $checkbox . ' ' . $this->description;
            $renderer->writeAt(
                $x,
                $currentY,
                \mb_substr($descText, 0, $width),
                $color,
            );
            $currentY++;

            // Render field name (muted) with indent
            $nameText = '    ' . $this->label; // 4 spaces indent
            $renderer->writeAt(
                $x,
                $currentY,
                \mb_substr($nameText, 0, $width),
                ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_GRAY),
            );
            $currentY++;
        } else {
            // No description, render checkbox with label on one line
            $nameText = $checkbox . ' ' . $this->label;
            $renderer->writeAt($x, $currentY, $nameText, $color);
            $currentY++;
        }

        // Return height: description line (if present) + name line + spacing
        return ($this->description !== '' ? 3 : 2);
    }

    public function handleInput(string $key): bool
    {
        $input = KeyInput::from($key);

        // Toggle on space or enter
        if ($input->isSpace() || $input->is(Key::ENTER)) {
            $this->value = !$this->value;
            return true;
        }

        return false;
    }
}
