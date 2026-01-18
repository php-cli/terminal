<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Module;

use Butschster\Commander\SDK\Container\ContainerInterface;

/**
 * Runtime context passed to modules during boot.
 *
 * Provides access to application services and configuration.
 */
final readonly class ModuleContext
{
    /**
     * @param ContainerInterface $container DI container for service resolution
     * @param array<string, mixed> $config Application configuration
     */
    public function __construct(
        public ContainerInterface $container,
        private array $config = [],
    ) {}

    /**
     * Get configuration value using dot notation.
     *
     * @param string $key Dot-notation key (e.g., 'database.host')
     * @param mixed $default Default if not found
     */
    public function config(string $key, mixed $default = null): mixed
    {
        $keys = \explode('.', $key);
        $value = $this->config;

        foreach ($keys as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
