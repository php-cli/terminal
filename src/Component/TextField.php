<?php

declare(strict_types=1);

namespace Butschster\Commander\Component;

use Butschster\Commander\Service\Renderer;
use Butschster\Commander\Theme\ColorScheme;

/**
 * Text input field
 */
class TextField extends FormField
{
    private int $cursorPosition = 0;

    public function __construct(
        string $name,
        string $label,
        private bool $required = false,
        mixed $default = null,
    ) {
        parent::__construct($name, $label, $default ?? '');
        $this->cursorPosition = mb_strlen((string) $this->value);
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, bool $focused): int
    {
        // Render label
        $labelText = $this->label . ($this->required ? ' *' : '') . ':';
        $renderer->writeAt($x, $y, $labelText, ColorScheme::NORMAL_TEXT);

        // Render input field
        $inputY = $y + 1;
        $inputWidth = $width - 2;
        $valueStr = (string) $this->value;

        // Display value (with cursor if focused)
        $displayValue = str_pad(
            mb_substr($valueStr, 0, $inputWidth),
            $inputWidth,
        );

        if ($focused) {
            // Show cursor
            if ($this->cursorPosition < mb_strlen($displayValue)) {
                $before = mb_substr($displayValue, 0, $this->cursorPosition);
                $cursor = mb_substr($displayValue, $this->cursorPosition, 1);
                $after = mb_substr($displayValue, $this->cursorPosition + 1);

                $renderer->writeAt($x + 1, $inputY, $before, ColorScheme::INPUT_TEXT);
                $renderer->writeAt(
                    $x + 1 + mb_strlen($before),
                    $inputY,
                    $cursor,
                    ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::FG_BLACK),
                );
                $renderer->writeAt(
                    $x + 1 + mb_strlen($before) + 1,
                    $inputY,
                    $after,
                    ColorScheme::INPUT_TEXT,
                );
            } else {
                $renderer->writeAt($x + 1, $inputY, $displayValue, ColorScheme::INPUT_TEXT);
                $renderer->writeAt(
                    $x + 1 + $this->cursorPosition,
                    $inputY,
                    ' ',
                    ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::FG_BLACK),
                );
            }
        } else {
            $renderer->writeAt($x + 1, $inputY, $displayValue, ColorScheme::INPUT_TEXT);
        }

        return 3; // Label + input + spacing
    }

    public function handleInput(string $key): bool
    {
        $valueStr = (string) $this->value;

        switch ($key) {
            case 'LEFT':
                if ($this->cursorPosition > 0) {
                    $this->cursorPosition--;
                }
                return true;

            case 'RIGHT':
                if ($this->cursorPosition < mb_strlen($valueStr)) {
                    $this->cursorPosition++;
                }
                return true;

            case 'HOME':
                $this->cursorPosition = 0;
                return true;

            case 'END':
                $this->cursorPosition = mb_strlen($valueStr);
                return true;

            case 'BACKSPACE':
                if ($this->cursorPosition > 0) {
                    $before = mb_substr($valueStr, 0, $this->cursorPosition - 1);
                    $after = mb_substr($valueStr, $this->cursorPosition);
                    $this->value = $before . $after;
                    $this->cursorPosition--;
                }
                return true;

            case 'DELETE':
                if ($this->cursorPosition < mb_strlen($valueStr)) {
                    $before = mb_substr($valueStr, 0, $this->cursorPosition);
                    $after = mb_substr($valueStr, $this->cursorPosition + 1);
                    $this->value = $before . $after;
                }
                return true;

            default:
                // Add character if it's printable
                if (mb_strlen($key) === 1 && ord($key) >= 32 && ord($key) < 127) {
                    $before = mb_substr($valueStr, 0, $this->cursorPosition);
                    $after = mb_substr($valueStr, $this->cursorPosition);
                    $this->value = $before . $key . $after;
                    $this->cursorPosition++;
                    return true;
                }
                return false;
        }
    }
}