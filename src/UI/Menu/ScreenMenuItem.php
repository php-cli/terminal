<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Menu;

/**
 * Menu item that navigates to a screen
 */
final readonly class ScreenMenuItem extends AbstractMenuItem
{
    public function __construct(
        string $label,
        public string $screenName,
        ?string $hotkey = null,
    ) {
        parent::__construct($label, $hotkey);
    }

    public static function create(string $label, string $screenName, ?string $hotkey = null): self
    {
        return new self($label, $screenName, $hotkey);
    }
}
