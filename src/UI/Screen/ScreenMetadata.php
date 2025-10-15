<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Screen;

/**
 * Screen metadata for automatic registration and discovery
 */
final readonly class ScreenMetadata
{
    public function __construct(
        public string $name,              // Unique identifier (e.g., 'command_browser')
        public string $title,             // Display title (e.g., 'Command Browser')
        public string $description,       // Short description
        public ?string $icon = null,      // Optional icon/symbol (e.g., '📋')
        public ?string $category = null,  // Menu category (e.g., 'tools', 'files')
        public int $priority = 100,       // Sort order within category (lower = higher priority)
    ) {}

    /**
     * Create metadata for a screen in Files category
     */
    public static function files(
        string $name,
        string $title,
        string $description,
        ?string $icon = null,
        int $priority = 100,
    ): self {
        return new self($name, $title, $description, $icon, 'files', $priority);
    }

    /**
     * Create metadata for a screen in Tools category
     */
    public static function tools(
        string $name,
        string $title,
        string $description,
        ?string $icon = null,
        int $priority = 100,
    ): self {
        return new self($name, $title, $description, $icon, 'tools', $priority);
    }

    /**
     * Create metadata for a screen in System category
     */
    public static function system(
        string $name,
        string $title,
        string $description,
        ?string $icon = null,
        int $priority = 100,
    ): self {
        return new self($name, $title, $description, $icon, 'system', $priority);
    }

    /**
     * Create metadata for a screen in Help category
     */
    public static function help(
        string $name,
        string $title,
        string $description,
        ?string $icon = null,
        int $priority = 100,
    ): self {
        return new self($name, $title, $description, $icon, 'help', $priority);
    }

    /**
     * Get display text with icon
     */
    public function getDisplayText(): string
    {
        return $this->icon !== null
            ? "{$this->icon} {$this->title}"
            : $this->title;
    }
}
