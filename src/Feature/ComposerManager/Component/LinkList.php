<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Component;

use Butschster\Commander\UI\Component\Display\Text\TextComponent;

/**
 * Link list component with icons
 */
final class LinkList extends TextComponent
{
    private int $indent = 2;
    private array $iconMap = [
        'issues' => '🐛',
        'docs' => '📚',
        'forum' => '💬',
        'source' => '📦',
        'homepage' => '🌐',
    ];

    /**
     * @param array<string, string> $links Map of link type to URL
     */
    public function __construct(
        private readonly array $links,
    ) {}

    /**
     * Create LinkList from package data (source, homepage, and support links)
     *
     * @param string|null $source Source URL
     * @param string|null $homepage Homepage URL
     * @param array<string, string> $support Support links
     */
    public static function fromPackageData(
        ?string $source = null,
        ?string $homepage = null,
        array $support = [],
    ): self {
        $links = [];

        if ($source !== null) {
            $links['source'] = $source;
        }

        if ($homepage !== null) {
            $links['homepage'] = $homepage;
        }

        $links = \array_merge($links, $support);

        return new self($links);
    }

    /**
     * Set custom icon mapping
     */
    public function icons(array $iconMap): self
    {
        $this->iconMap = \array_merge($this->iconMap, $iconMap);
        return $this;
    }

    /**
     * Set indent level
     */
    public function listIndent(int $indent): self
    {
        $this->indent = $indent;
        return $this;
    }

    protected function render(): string
    {
        if (empty($this->links)) {
            return '';
        }

        $lines = [];
        $indentStr = \str_repeat(' ', $this->indent);

        foreach ($this->links as $type => $url) {
            $icon = $this->iconMap[$type] ?? '🔗';
            $label = \ucfirst((string) $type);
            $lines[] = $indentStr . $icon . ' ' . $label . ': ' . $url;
        }

        return \implode("\n", $lines);
    }
}
