<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Module;

/**
 * Immutable module metadata.
 *
 * Contains identification and dependency information.
 */
final readonly class ModuleMetadata
{
    /**
     * @param string $name Unique identifier (e.g., 'file_browser')
     * @param string $title Human-readable title
     * @param string $version Semantic version (e.g., '1.0.0')
     * @param array<string> $dependencies Module names this depends on
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $version = '1.0.0',
        public array $dependencies = [],
    ) {}
}
