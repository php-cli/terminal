<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\CommandBrowser\Service;

/**
 * Argument metadata structure
 */
final readonly class ArgumentMetadata
{
    public function __construct(
        public string $name,
        public string $description,
        public bool $required,
        public bool $isArray,
        public mixed $default,
    ) {}
}
