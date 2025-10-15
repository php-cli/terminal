<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Display\Text;

/**
 * Key-value pair component
 */
final class KeyValue extends TextComponent
{
    private string $separator = ': ';
    private bool $bold = false;

    public function __construct(
        private readonly string $key,
        private readonly string $value,
    ) {}

    /**
     * Set the separator between key and value
     */
    public function separator(string $separator): self
    {
        $this->separator = $separator;
        return $this;
    }

    /**
     * Make the key bold (using ANSI codes would require renderer integration)
     */
    public function bold(): self
    {
        $this->bold = $this->bold;
        return $this;
    }

    protected function render(): string
    {
        return $this->key . $this->separator . $this->value;
    }
}
