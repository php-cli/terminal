<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Menu;

use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;

/**
 * Menu Definition - represents a top-level menu with dropdown items
 */
final readonly class MenuDefinition
{
    /**
     * @param string $label Menu label (e.g., "Files", "Tools")
     * @param KeyCombination|null $fkey Function key to activate
     * @param array<MenuItemInterface> $items Dropdown menu items
     * @param int $priority Sort order (lower = further left)
     */
    public function __construct(
        public string $label,
        public ?KeyCombination $fkey,
        public array $items,
        public int $priority = 100,
    ) {}

    /**
     * Get first non-separator item
     */
    public function getFirstItem(): ?MenuItemInterface
    {
        foreach ($this->items as $item) {
            if (!$item->isSeparator()) {
                return $item;
            }
        }

        return null;
    }
}
