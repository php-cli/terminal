<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Exception;

/**
 * Thrown when a requested service is not found in the container.
 */
final class ServiceNotFoundException extends \RuntimeException
{
    public function __construct(
        public readonly string $serviceId,
    ) {
        parent::__construct(
            \sprintf('Service not found: %s', $serviceId),
        );
    }
}
