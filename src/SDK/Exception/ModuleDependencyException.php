<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Exception;

/**
 * Thrown when a module's required dependency is not registered.
 */
final class ModuleDependencyException extends ModuleException
{
    public function __construct(
        public readonly string $moduleName,
        public readonly string $missingDependency,
    ) {
        parent::__construct(
            \sprintf(
                "Module '%s' requires '%s' which is not registered",
                $moduleName,
                $missingDependency,
            ),
        );
    }
}
