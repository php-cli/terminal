<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Provider;

use Butschster\Commander\SDK\Container\ServiceDefinition;

/**
 * Modules implement this to provide services.
 *
 * Services are registered in the container and can be
 * injected into screens and other services.
 */
interface ServiceProviderInterface
{
    /**
     * Provide service definitions.
     *
     * Called before boot() so services are available
     * during module initialization.
     *
     * @return iterable<ServiceDefinition>
     */
    public function services(): iterable;
}
