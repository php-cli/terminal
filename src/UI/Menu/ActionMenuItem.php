<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Menu;

/**
 * Menu item that executes a closure when selected
 */
final readonly class ActionMenuItem extends AbstractMenuItem
{
    public \Closure $action;

    public function __construct(
        string $label,
        callable $action,
        ?string $hotkey = null,
    ) {
        parent::__construct($label, $hotkey);
        $this->action = $action(...);
    }

    public static function create(string $label, callable $action, ?string $hotkey = null): self
    {
        return new self($label, $action, $hotkey);
    }
}
