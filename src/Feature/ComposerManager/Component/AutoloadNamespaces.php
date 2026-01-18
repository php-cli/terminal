<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Component;

use Butschster\Commander\UI\Component\Display\Text\TextComponent;

/**
 * Component for displaying autoload namespaces with paths
 */
final class AutoloadNamespaces extends TextComponent
{
    private string $arrow = '→';
    private int $indent = 4;

    /**
     * @param array<string, string|array<string>> $namespaces Map of namespace to path(s)
     */
    public function __construct(
        private readonly array $namespaces,
    ) {}

    /**
     * Set the arrow character
     */
    public function arrow(string $arrow): self
    {
        $this->arrow = $arrow;
        return $this;
    }

    /**
     * Set indent for paths
     */
    public function pathIndent(int $indent): self
    {
        $this->indent = $indent;
        return $this;
    }

    #[\Override]
    protected function render(): string
    {
        if (empty($this->namespaces)) {
            return '';
        }

        $lines = [];
        $indentStr = \str_repeat(' ', $this->indent);

        foreach ($this->namespaces as $namespace => $paths) {
            $pathsList = \is_array($paths) ? \implode(', ', $paths) : $paths;
            $lines[] = "  {$namespace}";
            $lines[] = $indentStr . $this->arrow . ' ' . $pathsList;
            $lines[] = '';
        }

        // Remove last empty line
        \array_pop($lines);

        return \implode("\n", $lines);
    }
}
