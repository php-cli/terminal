<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Input;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Text input field
 */
class TextField extends FormField
{
    private int $cursorPosition = 0;

    public function __construct(
        string $name,
        string $label,
        private readonly bool $required = false,
        mixed $default = null,
        string $description = '',
    ) {
        parent::__construct($name, $label, $default ?? '', $description);
        $this->cursorPosition = \mb_strlen((string) $this->value);
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    #[\Override]
    public function render(Renderer $renderer, int $x, int $y, int $width, bool $focused): int
    {
        $currentY = $y;

        // Render description first (if present)
        if ($this->description !== '') {
            $descText = $this->description . ($this->required ? ' *' : '');
            $renderer->writeAt(
                $x,
                $currentY,
                \mb_substr($descText, 0, $width),
                ColorScheme::$NORMAL_TEXT,
            );
            $currentY++;
        }

        // Render field name (muted) - use normal background with gray text
        $nameText = '  ' . $this->label;
        $renderer->writeAt(
            $x,
            $currentY,
            \mb_substr($nameText, 0, $width),
            ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_GRAY),
        );
        $currentY++;

        // Render input field
        $inputWidth = $width - 2;
        $valueStr = (string) $this->value;

        // Display value (with cursor if focused)
        $displayValue = \str_pad(
            \mb_substr($valueStr, 0, $inputWidth),
            $inputWidth,
        );

        if ($focused) {
            // Show cursor
            if ($this->cursorPosition < \mb_strlen($displayValue)) {
                $before = \mb_substr($displayValue, 0, $this->cursorPosition);
                $cursor = \mb_substr($displayValue, $this->cursorPosition, 1);
                $after = \mb_substr($displayValue, $this->cursorPosition + 1);

                $renderer->writeAt($x + 1, $currentY, $before, ColorScheme::$INPUT_TEXT);
                $renderer->writeAt(
                    $x + 1 + \mb_strlen($before),
                    $currentY,
                    $cursor,
                    ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::FG_BLACK),
                );
                $renderer->writeAt(
                    $x + 1 + \mb_strlen($before) + 1,
                    $currentY,
                    $after,
                    ColorScheme::$INPUT_TEXT,
                );
            } else {
                $renderer->writeAt($x + 1, $currentY, $displayValue, ColorScheme::$INPUT_TEXT);
                $renderer->writeAt(
                    $x + 1 + $this->cursorPosition,
                    $currentY,
                    ' ',
                    ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::FG_BLACK),
                );
            }
        } else {
            $renderer->writeAt($x + 1, $currentY, $displayValue, ColorScheme::$INPUT_TEXT);
        }
        $currentY++;

        // Return height: name line + description line (if present) + input line + spacing
        return ($this->description !== '' ? 4 : 3);
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        $input = KeyInput::from($key);
        $valueStr = (string) $this->value;

        return match (true) {
            $input->is(Key::LEFT) => $this->cursorPosition > 0 ? --$this->cursorPosition !== null : true,
            $input->is(Key::RIGHT) => $this->cursorPosition < \mb_strlen($valueStr) ? ++$this->cursorPosition !== null : true,
            $input->is(Key::HOME) => ($this->cursorPosition = 0) !== null,
            $input->is(Key::END) => ($this->cursorPosition = \mb_strlen($valueStr)) !== null,
            $input->is(Key::BACKSPACE) => $this->handleBackspace($valueStr),
            $input->is(Key::DELETE) => $this->handleDelete($valueStr),
            default => $this->handleCharacterInput($key, $valueStr),
        };
    }

    private function handleBackspace(string $valueStr): bool
    {
        if ($this->cursorPosition > 0) {
            $before = \mb_substr($valueStr, 0, $this->cursorPosition - 1);
            $after = \mb_substr($valueStr, $this->cursorPosition);
            $this->value = $before . $after;
            $this->cursorPosition--;
        }
        return true;
    }

    private function handleDelete(string $valueStr): bool
    {
        if ($this->cursorPosition < \mb_strlen($valueStr)) {
            $before = \mb_substr($valueStr, 0, $this->cursorPosition);
            $after = \mb_substr($valueStr, $this->cursorPosition + 1);
            $this->value = $before . $after;
        }
        return true;
    }

    private function handleCharacterInput(string $key, string $valueStr): bool
    {
        // Add character if it's printable
        if (\mb_strlen($key) === 1 && \ord($key) >= 32 && \ord($key) < 127) {
            $before = \mb_substr($valueStr, 0, $this->cursorPosition);
            $after = \mb_substr($valueStr, $this->cursorPosition);
            $this->value = $before . $key . $after;
            $this->cursorPosition++;
            return true;
        }
        return false;
    }
}
