<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\CommandBrowser\Service;

/**
 * Option metadata structure
 */
final class OptionMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $shortcut,
        public readonly string $description,
        public readonly bool $acceptValue,
        public readonly bool $isValueRequired,
        public readonly bool $isArray,
        public readonly mixed $default,
    ) {}
}

