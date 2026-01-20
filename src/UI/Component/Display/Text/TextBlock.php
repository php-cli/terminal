<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Display\Text;

/**
 * Simple text block component with optional word wrapping
 */
final class TextBlock extends TextComponent
{
    public function __construct(
        private readonly string $text,
        private readonly int $wrapWidth = 0,
        private readonly int $indentWidth = 2,
    ) {}

    /**
     * Create a blank line
     */
    public static function newLine(): self
    {
        return new self('');
    }

    public static function repeat($symbol = '─', int $times = 50): self
    {
        return new self("\n\n" . \str_repeat((string) $symbol, $times) . "\n");
    }

    /**
     * Create a text block from an array of values joined by a separator
     *
     * @param array<string> $values
     */
    public static function implode(array $values, string $separator = ', '): self
    {
        return new self(\implode($separator, $values));
    }

    #[\Override]
    protected function render(): string
    {
        if ($this->wrapWidth > 0) {
            return $this->wordWrap($this->text, $this->wrapWidth);
        }

        return $this->text;
    }

    private function wordWrap(string $text, int $width): string
    {
        $lines = \explode("\n", \wordwrap($text, $width, "\n", false));
        return \implode(
            "\n",
            \array_map(
                fn(string $line) => \str_repeat(' ', $this->indentWidth) . $line,
                $lines,
            ),
        );
    }
}
