<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Screen;

/**
 * Screen Registry - discovers and manages all registered screens
 *
 * Similar to Symfony's CommandRegistry, but for screens.
 * Automatically discovers screens implementing ScreenInterface.
 */
final class ScreenRegistry
{
    /** @var array<string, ScreenInterface> */
    private array $screens = [];

    /** @var array<string, ScreenMetadata> */
    private array $metadata = [];

    /**
     * Register a screen class
     */
    public function register(ScreenInterface $screen): void
    {
        // Get metadata from screen
        $metadata = $screen->getMetadata();

        // Check for duplicate names
        if (isset($this->screens[$metadata->name])) {
            throw new \RuntimeException(
                "Screen with name '{$metadata->name}' is already registered",
            );
        }

        $this->screens[$metadata->name] = $screen;
        $this->metadata[$metadata->name] = $metadata;
    }

    /**
     * Get screen class by name
     */
    public function getScreen(string $name): ?ScreenInterface
    {
        return $this->screens[$name] ?? null;
    }

    /**
     * Get screen metadata by name
     */
    public function getMetadata(string $name): ?ScreenMetadata
    {
        return $this->metadata[$name] ?? null;
    }

    /**
     * Get all registered screen names
     *
     * @return array<string>
     */
    public function getNames(): array
    {
        return \array_keys($this->screens);
    }

    /**
     * Get all registered screens grouped by category
     *
     * @return array<string, array<ScreenMetadata>>
     */
    public function getByCategory(): array
    {
        $grouped = [];

        foreach ($this->metadata as $metadata) {
            $category = $metadata->category ?? 'uncategorized';
            $grouped[$category][] = $metadata;
        }

        // Sort each category by priority
        foreach ($grouped as $category => $screens) {
            \usort($screens, fn($a, $b) => $a->priority <=> $b->priority);
            $grouped[$category] = $screens;
        }

        return $grouped;
    }

    /**
     * Get screens in a specific category
     *
     * @return array<ScreenMetadata>
     */
    public function getCategory(string $category): array
    {
        $screens = [];

        foreach ($this->metadata as $metadata) {
            if ($metadata->category === $category) {
                $screens[] = $metadata;
            }
        }

        // Sort by priority
        \usort($screens, fn($a, $b) => $a->priority <=> $b->priority);

        return $screens;
    }

    /**
     * Check if screen is registered
     */
    public function has(string $name): bool
    {
        return isset($this->screens[$name]);
    }

    /**
     * Count registered screens
     */
    public function count(): int
    {
        return \count($this->screens);
    }

    /**
     * Create screen instance with dependencies
     *
     * @param string $name Screen name
     * @param array<mixed> $dependencies Constructor dependencies
     * @return ScreenInterface
     */
    public function create(string $name, array $dependencies = []): ScreenInterface
    {
        $class = $this->getScreen($name);

        if ($class === null) {
            throw new \RuntimeException("Screen not found: {$name}");
        }

        return new $class(...$dependencies);
    }

    /**
     * Get all metadata sorted by priority within categories
     *
     * @return array<ScreenMetadata>
     */
    public function getAllMetadata(): array
    {
        $metadata = \array_values($this->metadata);

        // Sort by category first, then by priority
        \usort($metadata, function ($a, $b) {
            $categoryCompare = ($a->category ?? 'z') <=> ($b->category ?? 'z');
            if ($categoryCompare !== 0) {
                return $categoryCompare;
            }

            return $a->priority <=> $b->priority;
        });

        return $metadata;
    }
}
