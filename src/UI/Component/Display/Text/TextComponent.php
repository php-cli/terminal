<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Display\Text;

/**
 * Base class for text-based components that can be rendered as strings
 * and passed to TextDisplay components.
 */
abstract class TextComponent implements \Stringable
{
    private bool $shouldDisplay = true;
    private int $indentLevel = 0;
    private string $indentChar = '  ';

    /**
     * Static factory method for fluent API
     *
     * @psalm-suppress TooManyArguments Arguments are forwarded to child constructors
     */
    public static function create(mixed ...$args): static
    {
        return new static(...$args);
    }

    /**
     * Conditionally display this component
     */
    public function displayWhen(mixed $condition): static
    {
        $this->shouldDisplay = (bool) $condition;
        return $this;
    }

    /**
     * Set indentation level
     */
    public function indent(int $level = 1): static
    {
        $this->indentLevel = $level;
        return $this;
    }

    /**
     * Set indentation character(s)
     */
    public function indentWith(string $char): static
    {
        $this->indentChar = $char;
        return $this;
    }

    /**
     * Convert component to string
     */
    #[\Override]
    final public function __toString(): string
    {
        if (!$this->shouldDisplay) {
            return '';
        }

        $rendered = $this->render();

        if ($this->indentLevel > 0 && $rendered !== '') {
            $indent = \str_repeat($this->indentChar, $this->indentLevel);
            $lines = \explode("\n", $rendered);
            $lines = \array_map(
                static fn(string $line) => $line === '' ? '' : $indent . $line,
                $lines,
            );
            $rendered = \implode("\n", $lines);
        }

        return $rendered . "\n";
    }

    /**
     * Render the component content
     *
     * @return string The rendered text content
     */
    abstract protected function render(): string;

    /**
     * Get the indent string for current level
     */
    protected function getIndent(): string
    {
        return \str_repeat($this->indentChar, $this->indentLevel);
    }
}
