<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Provider;

use Butschster\Commander\UI\Menu\MenuDefinition;

/**
 * Modules implement this to provide menu entries.
 *
 * Menus appear in the top menu bar with F-key shortcuts.
 */
interface MenuProviderInterface
{
    /**
     * Provide menu definitions.
     *
     * Each MenuDefinition becomes a top-level menu item
     * with its own dropdown containing menu items.
     *
     * @return iterable<MenuDefinition>
     */
    public function menus(): iterable;
}
