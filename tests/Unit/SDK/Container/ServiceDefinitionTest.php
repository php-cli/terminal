<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Container;

use Butschster\Commander\SDK\Container\ServiceDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ServiceDefinition::class)]
final class ServiceDefinitionTest extends TestCase
{
    #[Test]
    public function it_can_be_created_with_constructor(): void
    {
        $factory = static fn() => new \stdClass();
        $definition = new ServiceDefinition('my.service', $factory, singleton: true);

        self::assertSame('my.service', $definition->id);
        self::assertSame($factory, $definition->factory);
        self::assertTrue($definition->singleton);
    }

    #[Test]
    public function it_defaults_to_singleton(): void
    {
        $factory = static fn() => new \stdClass();
        $definition = new ServiceDefinition('my.service', $factory);

        self::assertTrue($definition->singleton);
    }

    #[Test]
    public function singleton_factory_creates_singleton_definition(): void
    {
        $factory = static fn() => new \stdClass();
        $definition = ServiceDefinition::singleton('my.service', $factory);

        self::assertSame('my.service', $definition->id);
        self::assertSame($factory, $definition->factory);
        self::assertTrue($definition->singleton);
    }

    #[Test]
    public function transient_factory_creates_non_singleton_definition(): void
    {
        $factory = static fn() => new \stdClass();
        $definition = ServiceDefinition::transient('my.service', $factory);

        self::assertSame('my.service', $definition->id);
        self::assertSame($factory, $definition->factory);
        self::assertFalse($definition->singleton);
    }

    #[Test]
    public function factory_can_use_class_name_as_id(): void
    {
        $factory = static fn() => new \stdClass();
        $definition = ServiceDefinition::singleton(\stdClass::class, $factory);

        self::assertSame(\stdClass::class, $definition->id);
    }
}
