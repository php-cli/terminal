<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Keyboard;

/**
 * Central registry for all keyboard bindings.
 *
 * Stores bindings and provides lookup methods by raw key input,
 * action ID, or category.
 */
final class KeyBindingRegistry implements KeyBindingRegistryInterface
{
    /** @var array<KeyBinding> */
    private array $bindings = [];

    public function register(KeyBinding $binding): void
    {
        $this->bindings[] = $binding;
    }

    public function match(string $rawKey): ?KeyBinding
    {
        foreach ($this->bindings as $binding) {
            if ($binding->matches($rawKey)) {
                return $binding;
            }
        }

        return null;
    }

    public function getByActionId(string $actionId): array
    {
        return \array_values(\array_filter(
            $this->bindings,
            static fn(KeyBinding $b) => $b->actionId === $actionId,
        ));
    }

    public function getPrimaryByActionId(string $actionId): ?KeyBinding
    {
        foreach ($this->bindings as $binding) {
            if ($binding->actionId === $actionId) {
                return $binding;
            }
        }

        return null;
    }

    public function getByCategory(string $category): array
    {
        $filtered = \array_filter(
            $this->bindings,
            static fn(KeyBinding $b) => $b->category === $category,
        );

        \usort($filtered, static fn(KeyBinding $a, KeyBinding $b) => $a->priority <=> $b->priority);

        return $filtered;
    }

    public function all(): array
    {
        return $this->bindings;
    }
}
