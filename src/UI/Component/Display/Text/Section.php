<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Display\Text;

/**
 * Section component with title and content
 */
final class Section extends TextComponent
{
    private string $separator = '─';
    private int $separatorLength = 57;
    private bool $showSeparator = true;
    private int $spacing = 1;
    private int $marginBottom = 1;

    public function __construct(
        private readonly string $title,
        private readonly string|\Stringable|null $content = null,
    ) {}

    /**
     * Set the separator character
     */
    public function separator(string $char, int $length = 57): self
    {
        $this->separator = $char;
        $this->separatorLength = $length;
        return $this;
    }

    /**
     * Hide the separator line
     */
    public function hideSeparator(): self
    {
        $this->showSeparator = false;
        return $this;
    }

    /**
     * Set spacing (blank lines) after separator
     */
    public function spacing(int $lines): self
    {
        $this->spacing = $lines;
        return $this;
    }

    /**
     * Set bottom margin (blank lines after content)
     */
    public function marginBottom(int $lines): self
    {
        $this->marginBottom = $lines;
        return $this;
    }

    protected function render(): string
    {
        // Check if content is empty or null
        if ($this->content !== null) {
            $renderedContent = (string) $this->content;
            // If content renders to empty string (e.g., displayWhen(false)), don't render section
            if ($renderedContent === '' || \trim($renderedContent) === '') {
                return '';
            }
        }

        $lines = [];

        if ($this->showSeparator) {
            $lines[] = \str_repeat($this->separator, $this->separatorLength);
        }

        $lines[] = '';
        $lines[] = $this->title . ':';

        for ($i = 0; $i < $this->spacing; $i++) {
            $lines[] = '';
        }

        if ($this->content !== null) {
            $lines[] = (string) $this->content;
        }

        // Add bottom margin
        for ($i = 0; $i < $this->marginBottom; $i++) {
            $lines[] = '';
        }

        return \implode("\n", $lines);
    }
}
