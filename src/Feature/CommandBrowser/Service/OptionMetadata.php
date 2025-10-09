<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\CommandBrowser\Service;

/**
 * Option metadata structure
 */
final readonly class OptionMetadata
{
    public function __construct(
        public string $name,
        public ?string $shortcut,
        public string $description,
        public bool $acceptValue,
        public bool $isValueRequired,
        public bool $isArray,
        public mixed $default,
    ) {}
}
