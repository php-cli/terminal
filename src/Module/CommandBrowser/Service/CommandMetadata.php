<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\CommandBrowser\Service;

/**
 * Command metadata structure
 */
final readonly class CommandMetadata
{
    /**
     * @param string $name Command name
     * @param string $description Short description
     * @param string $help Full help text
     * @param array<ArgumentMetadata> $arguments Command arguments
     * @param array<OptionMetadata> $options Command options
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $help,
        public array $arguments,
        public array $options,
    ) {}
}
