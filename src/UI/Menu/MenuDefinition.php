<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Menu;

/**
 * Menu Definition - represents a top-level menu with dropdown items
 */
final readonly class MenuDefinition
{
    /**
     * @param string $label Menu label (e.g., "Files", "Tools")
     * @param string|null $fkey Function key to activate (e.g., 'F9', 'F10')
     * @param array<MenuItem> $items Dropdown menu items
     * @param int $priority Sort order (lower = further left)
     */
    public function __construct(
        public string $label,
        public ?string $fkey,
        public array $items,
        public int $priority = 100,
    ) {}

    /**
     * Get first non-separator item
     */
    public function getFirstItem(): ?MenuItem
    {
        foreach ($this->items as $item) {
            if (!$item->isSeparator()) {
                return $item;
            }
        }

        return null;
    }
}
