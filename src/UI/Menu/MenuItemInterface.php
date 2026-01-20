<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Menu;

/**
 * Contract for all menu item types
 */
interface MenuItemInterface
{
    /**
     * Get the display label for this menu item
     */
    public function getLabel(): string;

    /**
     * Get the hotkey for quick access
     * Returns lowercase hotkey if set, or first character of label as fallback
     */
    public function getHotkey(): ?string;

    /**
     * Check if this item is a visual separator
     */
    public function isSeparator(): bool;
}
