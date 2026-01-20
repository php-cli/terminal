<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Keyboard;

/**
 * Contract for keyboard binding registry.
 *
 * Provides a centralized store for keyboard shortcuts, allowing
 * registration, lookup by key input, and retrieval by action or category.
 */
interface KeyBindingRegistryInterface
{
    /**
     * Register a new key binding.
     */
    public function register(KeyBinding $binding): void;

    /**
     * Find binding that matches raw key input.
     *
     * @param string $rawKey The raw key string from KeyboardHandler
     * @return KeyBinding|null The matching binding, or null if none found
     */
    public function match(string $rawKey): ?KeyBinding;

    /**
     * Get all bindings for a specific action ID.
     *
     * Multiple bindings can exist for the same action (e.g., F12 and Ctrl+Q both quit).
     *
     * @return array<KeyBinding>
     */
    public function getByActionId(string $actionId): array;

    /**
     * Get the primary binding for an action (first registered).
     */
    public function getPrimaryByActionId(string $actionId): ?KeyBinding;

    /**
     * Get all bindings for a category, sorted by priority.
     *
     * @return array<KeyBinding>
     */
    public function getByCategory(string $category): array;

    /**
     * Get all registered bindings.
     *
     * @return array<KeyBinding>
     */
    public function all(): array;
}
