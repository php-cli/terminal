<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Exception;

/**
 * Thrown when a module with the same name is already registered.
 */
final class ModuleConflictException extends ModuleException
{
    public function __construct(
        public readonly string $moduleName,
    ) {
        parent::__construct(
            \sprintf("Module '%s' is already registered", $moduleName),
        );
    }
}
