<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Menu;

/**
 * Menu item that contains nested menu items
 */
final readonly class SubmenuMenuItem extends AbstractMenuItem
{
    /**
     * @param string $label Display label
     * @param array<MenuItemInterface> $items Nested menu items
     * @param string|null $hotkey Quick access key
     */
    public function __construct(
        string $label,
        public array $items,
        ?string $hotkey = null,
    ) {
        parent::__construct($label, $hotkey);
    }

    /**
     * @param array<MenuItemInterface> $items
     */
    public static function create(string $label, array $items, ?string $hotkey = null): self
    {
        return new self($label, $items, $hotkey);
    }
}
