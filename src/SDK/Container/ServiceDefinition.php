<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Container;

/**
 * Defines a service binding for the container.
 *
 * Used by ServiceProviderInterface to declare services.
 */
final readonly class ServiceDefinition
{
    /**
     * @param string $id Service identifier (usually class name)
     * @param \Closure $factory Factory: fn(ContainerInterface) => object
     * @param bool $singleton Whether to cache instance
     */
    public function __construct(
        public string $id,
        public \Closure $factory,
        public bool $singleton = true,
    ) {}

    /**
     * Create singleton definition.
     */
    public static function singleton(string $id, \Closure $factory): self
    {
        return new self($id, $factory, singleton: true);
    }

    /**
     * Create transient definition (new instance each call).
     */
    public static function transient(string $id, \Closure $factory): self
    {
        return new self($id, $factory, singleton: false);
    }
}
