<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Provider;

use Butschster\Commander\SDK\Container\ContainerInterface;
use Butschster\Commander\UI\Screen\ScreenInterface;

/**
 * Modules implement this to provide screens.
 *
 * Screens are UI views that can be navigated to.
 * They appear in menus and can be opened programmatically.
 */
interface ScreenProviderInterface
{
    /**
     * Provide screens to register.
     *
     * Called after services are registered, so container
     * can be used to resolve screen dependencies.
     *
     * @param ContainerInterface $container For resolving dependencies
     * @return iterable<ScreenInterface>
     */
    public function screens(ContainerInterface $container): iterable;
}
