<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Menu;

/**
 * Base implementation for menu items with label and hotkey
 */
abstract readonly class AbstractMenuItem implements MenuItemInterface
{
    public function __construct(
        protected string $label,
        protected ?string $hotkey = null,
    ) {}

    #[\Override]
    public function getLabel(): string
    {
        return $this->label;
    }

    #[\Override]
    public function getHotkey(): ?string
    {
        if ($this->hotkey !== null) {
            return \mb_strtolower($this->hotkey);
        }

        if ($this->isSeparator()) {
            return null;
        }

        return \mb_strtolower(\mb_substr($this->label, 0, 1));
    }

    #[\Override]
    public function isSeparator(): bool
    {
        return false;
    }
}
