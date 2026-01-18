<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Keyboard;

/**
 * Immutable data object representing a single keyboard binding.
 *
 * Links a key combination to an action identifier with metadata.
 */
final readonly class KeyBinding
{
    public function __construct(
        public KeyCombination $combination,
        public string $actionId,
        public string $description,
        public string $category = 'global',
        public int $priority = 0,
    ) {}

    /**
     * Check if this binding matches a raw KeyboardHandler output string.
     */
    public function matches(string $rawKey): bool
    {
        return $this->combination->matches($rawKey);
    }
}
