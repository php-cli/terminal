<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\CommandBrowser\Service;

/**
 * Argument metadata structure
 */
final class ArgumentMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly bool $required,
        public readonly bool $isArray,
        public readonly mixed $default,
    ) {}
}
