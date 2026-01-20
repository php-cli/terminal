<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Display\Text;

/**
 * Container component for stacking multiple text components vertically
 */
final class Container extends TextComponent
{
    private int $spacing = 1;

    /**
     * @param array<string|\Stringable|null> $children
     */
    public function __construct(
        private readonly array $children = [],
    ) {}

    /**
     * Set spacing (blank lines) between children
     */
    public function spacing(int $lines): self
    {
        $this->spacing = $lines;
        return $this;
    }

    #[\Override]
    protected function render(): string
    {
        $parts = [];
        $spacer = \str_repeat("\n", $this->spacing);

        foreach ($this->children as $child) {
            if ($child === null) {
                continue;
            }

            $rendered = (string) $child;
            if ($rendered !== '') {
                $parts[] = $rendered;
            }
        }

        return \implode($spacer, $parts);
    }
}
