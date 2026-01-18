<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Module;

use Butschster\Commander\SDK\Exception\ModuleConflictException;
use Butschster\Commander\SDK\Exception\ModuleDependencyException;
use Butschster\Commander\SDK\Exception\ModuleNotFoundException;

/**
 * Central registry for module management.
 *
 * Handles registration, dependency validation, and lifecycle.
 */
final class ModuleRegistry
{
    /** @var array<string, ModuleInterface> */
    private array $modules = [];

    /** @var array<string, ModuleMetadata> */
    private array $metadata = [];

    /** @var array<string> Boot order (topologically sorted) */
    private array $bootOrder = [];

    /** @var bool Whether boot() has been called */
    private bool $booted = false;

    /**
     * Register a module.
     *
     * @throws ModuleConflictException If module name already registered
     */
    public function register(ModuleInterface $module): void
    {
        if ($this->booted) {
            throw new \RuntimeException('Cannot register modules after boot');
        }

        $metadata = $module->metadata();
        $name = $metadata->name;

        if (isset($this->modules[$name])) {
            throw new ModuleConflictException($name);
        }

        $this->modules[$name] = $module;
        $this->metadata[$name] = $metadata;
    }

    /**
     * Boot all registered modules in dependency order.
     *
     * @throws ModuleDependencyException If dependencies not met
     */
    public function boot(ModuleContext $context): void
    {
        if ($this->booted) {
            throw new \RuntimeException('Modules already booted');
        }

        // Validate and sort
        $this->validateDependencies();
        $this->bootOrder = $this->topologicalSort();

        // Boot in order
        foreach ($this->bootOrder as $name) {
            $this->modules[$name]->boot($context);
        }

        $this->booted = true;
    }

    /**
     * Shutdown all modules in reverse boot order.
     */
    public function shutdown(): void
    {
        if (!$this->booted) {
            return;
        }

        // Shutdown in reverse order
        $shutdownOrder = \array_reverse($this->bootOrder);

        foreach ($shutdownOrder as $name) {
            try {
                $this->modules[$name]->shutdown();
            } catch (\Throwable) {
                // Log but continue shutting down other modules
                // In real implementation, use a logger
            }
        }

        $this->booted = false;
    }

    /**
     * Check if module is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    /**
     * Get module by name.
     *
     * @throws ModuleNotFoundException
     */
    public function get(string $name): ModuleInterface
    {
        if (!isset($this->modules[$name])) {
            throw new ModuleNotFoundException($name);
        }

        return $this->modules[$name];
    }

    /**
     * Get all registered modules.
     *
     * @return array<string, ModuleInterface>
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * Get all module metadata.
     *
     * @return array<string, ModuleMetadata>
     */
    public function allMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get modules in boot order.
     *
     * @return array<ModuleInterface>
     */
    public function getBootOrder(): array
    {
        return \array_map(
            fn(string $name) => $this->modules[$name],
            $this->bootOrder,
        );
    }

    /**
     * Get modules implementing a specific interface.
     *
     * @template T
     * @param class-string<T> $interface
     * @return array<T>
     */
    public function getImplementing(string $interface): array
    {
        $result = [];

        foreach ($this->bootOrder ?: \array_keys($this->modules) as $name) {
            $module = $this->modules[$name];
            if ($module instanceof $interface) {
                $result[] = $module;
            }
        }

        return $result;
    }

    /**
     * Check if modules have been booted.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Validate all module dependencies are met.
     *
     * @throws ModuleDependencyException
     */
    private function validateDependencies(): void
    {
        foreach ($this->metadata as $name => $metadata) {
            foreach ($metadata->dependencies as $dependency) {
                if (!isset($this->modules[$dependency])) {
                    throw new ModuleDependencyException($name, $dependency);
                }
            }
        }
    }

    /**
     * Topological sort using Kahn's algorithm.
     *
     * @return array<string> Sorted module names
     * @throws ModuleDependencyException On circular dependency
     */
    private function topologicalSort(): array
    {
        // Build in-degree map
        $inDegree = [];
        $dependents = []; // name => [modules that depend on it]

        foreach ($this->metadata as $name => $metadata) {
            $inDegree[$name] = \count($metadata->dependencies);

            foreach ($metadata->dependencies as $dep) {
                $dependents[$dep][] = $name;
            }
        }

        // Start with modules that have no dependencies
        $queue = [];
        foreach ($inDegree as $name => $degree) {
            if ($degree === 0) {
                $queue[] = $name;
            }
        }

        $sorted = [];

        while (!empty($queue)) {
            $name = \array_shift($queue);
            $sorted[] = $name;

            // Reduce in-degree of dependents
            foreach ($dependents[$name] ?? [] as $dependent) {
                $inDegree[$dependent]--;
                if ($inDegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }

        // Check for cycles
        if (\count($sorted) !== \count($this->modules)) {
            $remaining = \array_diff(\array_keys($this->modules), $sorted);
            throw new ModuleDependencyException(
                \reset($remaining),
                'circular dependency detected',
            );
        }

        return $sorted;
    }
}
