<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Screen\Attribute;

use Attribute;

/**
 * Screen metadata attribute for automatic registration and discovery
 *
 * Apply this attribute to screen classes to define their metadata
 * without implementing getMetadata() method.
 *
 * Example:
 * ```
 * #[Metadata(
 *     name: 'file_browser',
 *     title: 'File Browser',
 *     description: 'Browse and manage files and directories',
 *     category: 'files',
 *     priority: 10
 * )]
 * final class FileBrowserScreen implements ScreenInterface
 * {
 *     // ...
 * }
 * ```
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Metadata
{
    public function __construct(
        public string $name,              // Unique identifier (e.g., 'command_browser')
        public string $title,             // Display title (e.g., 'Command Browser')
        public string $description,       // Short description
        public ?string $icon = null,      // Optional icon/symbol (e.g., '📋')
        public ?string $category = null,  // Menu category (e.g., 'tools', 'files')
        public int $priority = 100,       // Sort order within category (lower = higher priority)
    ) {}
}
