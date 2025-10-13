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
     * Create menu from category in ScreenRegistry
     *
     * @param string $label Menu label
     * @param string|null $fkey Function key
     * @param array<\Butschster\Commander\UI\Screen\ScreenMetadata> $screens
     * @param int $priority
     */
    public static function fromScreens(
        string $label,
        ?string $fkey,
        array $screens,
        int $priority = 100,
    ): self {
        $items = [];

        foreach ($screens as $metadata) {
            $items[] = MenuItem::screen(
                $metadata->getDisplayText(),
                $metadata->name,
            );
        }

        return new self($label, $fkey, $items, $priority);
    }

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
