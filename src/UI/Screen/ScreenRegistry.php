<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Screen;

use Butschster\Commander\UI\Screen\Attribute\Metadata;

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

    public function __construct(
        private readonly ScreenManager $screenManager,
    ) {}

    /**
     * Register a screen class
     */
    public function register(ScreenInterface $screen): void
    {
        // Get metadata from attribute or fallback to getMetadata() method (for BC)
        $metadata = $this->extractMetadata($screen);

        // Check for duplicate names
        if (isset($this->screens[$metadata->name])) {
            throw new \RuntimeException(
                "Screen with name '{$metadata->name}' is already registered",
            );
        }

        if ($screen instanceof ScreenManagerAware) {
            $screen->setScreenManager($this->screenManager);
        }

        $this->screens[$metadata->name] = $screen;
        $this->metadata[$metadata->name] = $metadata;
    }

    /**
     * Extract metadata from screen using attribute or method
     */
    private function extractMetadata(ScreenInterface $screen): ScreenMetadata
    {
        $reflection = new \ReflectionClass($screen);
        $attributes = $reflection->getAttributes(Metadata::class);

        // Try to get metadata from attribute first
        if (!empty($attributes)) {
            /** @var Metadata $metadataAttr */
            $metadataAttr = $attributes[0]->newInstance();

            return new ScreenMetadata(
                name: $metadataAttr->name,
                title: $metadataAttr->title,
                description: $metadataAttr->description,
                icon: $metadataAttr->icon,
                category: $metadataAttr->category,
                priority: $metadataAttr->priority,
            );
        }

        throw new \RuntimeException(
            "Screen class {$reflection->getName()} must have either #[Metadata] attribute or getMetadata() method",
        );
    }

    /**
     * Get screen class by name
     */
    public function getScreen(string $name): ?ScreenInterface
    {
        return $this->screens[$name] ?? null;
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
            \usort($screens, static fn($a, $b) => $a->priority <=> $b->priority);
            $grouped[$category] = $screens;
        }

        return $grouped;
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
     */
    public function create(string $name, array $dependencies = []): ScreenInterface
    {
        $class = $this->getScreen($name);

        if ($class === null) {
            throw new \RuntimeException("Screen not found: {$name}");
        }

        return new $class(...$dependencies);
    }
}
