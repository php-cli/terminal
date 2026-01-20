<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Exception;

/**
 * Thrown when a requested module is not found in the registry.
 */
final class ModuleNotFoundException extends ModuleException
{
    public function __construct(
        public readonly string $moduleName,
    ) {
        parent::__construct(
            \sprintf("Module '%s' not found", $moduleName),
        );
    }
}
