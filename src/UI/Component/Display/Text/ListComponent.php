<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Display\Text;

/**
 * Bullet list component
 */
class ListComponent extends TextComponent
{
    private string $bullet = '•';
    private int $indent = 2;

    /**
     * @param array<string|\Stringable> $items
     */
    public function __construct(
        private readonly array $items,
    ) {}

    /**
     * Set the bullet character
     */
    public function bullet(string $bullet): self
    {
        $this->bullet = $bullet;
        return $this;
    }

    /**
     * Set the indent level for items
     */
    public function listIndent(int $indent): self
    {
        $this->indent = $indent;
        return $this;
    }

    protected function render(): string
    {
        if (empty($this->items)) {
            return '';
        }

        $lines = [];
        $indentStr = \str_repeat(' ', $this->indent);

        foreach ($this->items as $item) {
            $itemStr = (string) $item;
            $lines[] = $indentStr . $this->bullet . ' ' . $itemStr;
        }

        return \implode("\n", $lines);
    }
}
