<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Container;

use Butschster\Commander\SDK\Exception\ServiceNotFoundException;

/**
 * Simple DI container contract.
 *
 * Intentionally minimal - supports get, has, and make.
 * Not PSR-11 compliant to avoid external dependency.
 */
interface ContainerInterface
{
    /**
     * Get service by ID.
     *
     * @template T of object
     * @param class-string<T>|string $id Service identifier
     * @return T
     * @throws ServiceNotFoundException If service not registered
     */
    public function get(string $id): object;

    /**
     * Check if service is registered.
     */
    public function has(string $id): bool;

    /**
     * Create instance with autowired dependencies.
     *
     * Unlike get(), this always creates a new instance.
     * Use for classes not registered as services.
     *
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, mixed> $params Override constructor params
     * @return T
     */
    public function make(string $class, array $params = []): object;
}
