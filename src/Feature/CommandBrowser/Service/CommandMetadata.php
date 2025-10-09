<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\CommandBrowser\Service;

/**
 * Command metadata structure
 */
final class CommandMetadata
{
    /**
     * @param string $name Command name
     * @param string $description Short description
     * @param string $help Full help text
     * @param array<ArgumentMetadata> $arguments Command arguments
     * @param array<OptionMetadata> $options Command options
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $help,
        public readonly array $arguments,
        public readonly array $options,
    ) {}
}

