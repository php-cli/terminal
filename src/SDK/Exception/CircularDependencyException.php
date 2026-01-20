<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Exception;

/**
 * Thrown when a circular dependency is detected between modules.
 */
final class CircularDependencyException extends ModuleException
{
    /**
     * @param array<string> $chain The dependency chain that forms the cycle
     */
    public function __construct(
        public readonly array $chain,
    ) {
        parent::__construct(
            \sprintf(
                'Circular dependency detected: %s',
                \implode(' -> ', $chain),
            ),
        );
    }
}
