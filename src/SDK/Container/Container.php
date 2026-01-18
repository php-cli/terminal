<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Container;

use Butschster\Commander\SDK\Exception\CircularDependencyException;
use Butschster\Commander\SDK\Exception\ServiceNotFoundException;

/**
 * Simple DI container implementation.
 *
 * Supports singleton and transient bindings, constructor autowiring,
 * and circular dependency detection.
 */
final class Container implements ContainerInterface
{
    /** @var array<string, \Closure> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, bool> */
    private array $singletons = [];

    /** @var array<string, bool> Track resolution stack for circular detection */
    private array $resolving = [];

    /**
     * Register a factory (transient - new instance each time).
     */
    public function bind(string $id, \Closure $factory): void
    {
        $this->factories[$id] = $factory;
        $this->singletons[$id] = false;
    }

    /**
     * Register a singleton (same instance reused).
     */
    public function singleton(string $id, \Closure $factory): void
    {
        $this->factories[$id] = $factory;
        $this->singletons[$id] = true;
    }

    /**
     * Register an existing instance.
     */
    public function instance(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
        $this->singletons[$id] = true;
    }

    /**
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress InvalidReturnType
     */
    public function get(string $id): object
    {
        // Return cached singleton
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Check if registered
        if (!isset($this->factories[$id])) {
            throw new ServiceNotFoundException($id);
        }

        // Detect circular dependency
        if (isset($this->resolving[$id])) {
            $chain = \array_keys($this->resolving);
            $chain[] = $id;
            throw new CircularDependencyException($chain);
        }

        // Mark as resolving
        $this->resolving[$id] = true;

        try {
            $instance = ($this->factories[$id])($this);

            // Cache if singleton
            if ($this->singletons[$id]) {
                $this->instances[$id] = $instance;
            }

            return $instance;
        } finally {
            unset($this->resolving[$id]);
        }
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->instances[$id]);
    }

    public function make(string $class, array $params = []): object
    {
        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();

            // Use provided param if exists
            if (\array_key_exists($name, $params)) {
                $args[] = $params[$name];
                continue;
            }

            // Try to autowire by type
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
                if ($this->has($typeName)) {
                    $args[] = $this->get($typeName);
                    continue;
                }
            }

            // Use default if available
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            // Allow nullable
            if ($param->allowsNull()) {
                $args[] = null;
                continue;
            }

            throw new \RuntimeException(
                \sprintf("Cannot resolve parameter '%s' for %s", $name, $class),
            );
        }

        return $reflection->newInstanceArgs($args);
    }
}
