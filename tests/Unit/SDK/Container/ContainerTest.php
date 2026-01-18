<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Container;

use Butschster\Commander\SDK\Container\Container;
use Butschster\Commander\SDK\Container\ContainerInterface;
use Butschster\Commander\SDK\Exception\CircularDependencyException;
use Butschster\Commander\SDK\Exception\ServiceNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Container::class)]
final class ContainerTest extends TestCase
{
    #[Test]
    public function it_implements_container_interface(): void
    {
        $container = new Container();

        self::assertInstanceOf(ContainerInterface::class, $container);
    }

    #[Test]
    public function it_returns_false_for_unregistered_service(): void
    {
        $container = new Container();

        self::assertFalse($container->has('missing'));
    }

    #[Test]
    public function it_returns_true_for_registered_singleton(): void
    {
        $container = new Container();
        $container->singleton('service', static fn() => new \stdClass());

        self::assertTrue($container->has('service'));
    }

    #[Test]
    public function it_returns_true_for_registered_binding(): void
    {
        $container = new Container();
        $container->bind('service', static fn() => new \stdClass());

        self::assertTrue($container->has('service'));
    }

    #[Test]
    public function it_returns_true_for_registered_instance(): void
    {
        $container = new Container();
        $container->instance('service', new \stdClass());

        self::assertTrue($container->has('service'));
    }

    #[Test]
    public function singleton_returns_same_instance(): void
    {
        $container = new Container();
        $container->singleton('service', static fn() => new \stdClass());

        $a = $container->get('service');
        $b = $container->get('service');

        self::assertSame($a, $b);
    }

    #[Test]
    public function bind_returns_new_instance_each_time(): void
    {
        $container = new Container();
        $container->bind('service', static fn() => new \stdClass());

        $a = $container->get('service');
        $b = $container->get('service');

        self::assertNotSame($a, $b);
    }

    #[Test]
    public function instance_registers_existing_object(): void
    {
        $container = new Container();
        $obj = new \stdClass();
        $container->instance('service', $obj);

        self::assertSame($obj, $container->get('service'));
    }

    #[Test]
    public function it_throws_on_missing_service(): void
    {
        $container = new Container();

        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('Service not found: missing');

        $container->get('missing');
    }

    #[Test]
    public function it_detects_circular_dependency(): void
    {
        $container = new Container();
        $container->singleton('a', static fn(ContainerInterface $c) => $c->get('b'));
        $container->singleton('b', static fn(ContainerInterface $c) => $c->get('a'));

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage('Circular dependency detected: a -> b -> a');

        $container->get('a');
    }

    #[Test]
    public function it_detects_longer_circular_dependency_chain(): void
    {
        $container = new Container();
        $container->singleton('a', static fn(ContainerInterface $c) => $c->get('b'));
        $container->singleton('b', static fn(ContainerInterface $c) => $c->get('c'));
        $container->singleton('c', static fn(ContainerInterface $c) => $c->get('a'));

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage('Circular dependency detected: a -> b -> c -> a');

        $container->get('a');
    }

    #[Test]
    public function factory_receives_container(): void
    {
        $container = new Container();
        $receivedContainer = null;

        $container->singleton('service', static function (ContainerInterface $c) use (&$receivedContainer) {
            $receivedContainer = $c;
            return new \stdClass();
        });

        $container->get('service');

        self::assertSame($container, $receivedContainer);
    }

    #[Test]
    public function factory_can_resolve_other_services(): void
    {
        $container = new Container();
        $dependency = new \stdClass();
        $dependency->value = 'test';

        $container->instance('dep', $dependency);
        $container->singleton('service', static fn(ContainerInterface $c) => new ServiceWithDependency($c->get('dep')));

        $service = $container->get('service');

        self::assertInstanceOf(ServiceWithDependency::class, $service);
        self::assertSame('test', $service->dependency->value);
    }

    #[Test]
    public function make_creates_instance_without_constructor(): void
    {
        $container = new Container();

        $instance = $container->make(\stdClass::class);

        self::assertInstanceOf(\stdClass::class, $instance);
    }

    #[Test]
    public function make_autowires_dependencies(): void
    {
        $container = new Container();
        $container->singleton(DependencyA::class, static fn() => new DependencyA());

        $service = $container->make(ServiceWithTypedDependency::class);

        self::assertInstanceOf(ServiceWithTypedDependency::class, $service);
        self::assertInstanceOf(DependencyA::class, $service->dependency);
    }

    #[Test]
    public function make_uses_provided_params(): void
    {
        $container = new Container();

        $service = $container->make(ServiceWithValue::class, ['value' => 'test']);

        self::assertSame('test', $service->value);
    }

    #[Test]
    public function make_uses_default_values(): void
    {
        $container = new Container();

        $service = $container->make(ServiceWithDefault::class);

        self::assertSame('default', $service->value);
    }

    #[Test]
    public function make_allows_null_for_nullable_parameters(): void
    {
        $container = new Container();

        $service = $container->make(ServiceWithNullable::class);

        self::assertNull($service->value);
    }

    #[Test]
    public function make_throws_for_unresolvable_parameter(): void
    {
        $container = new Container();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cannot resolve parameter 'value' for");

        $container->make(ServiceWithValue::class);
    }

    #[Test]
    public function make_always_creates_new_instance(): void
    {
        $container = new Container();

        $a = $container->make(ServiceWithDefault::class);
        $b = $container->make(ServiceWithDefault::class);

        self::assertNotSame($a, $b);
    }

    #[Test]
    public function make_prefers_provided_params_over_autowiring(): void
    {
        $container = new Container();
        $container->singleton(DependencyA::class, static fn() => new DependencyA());

        $customDep = new DependencyA();
        $service = $container->make(ServiceWithTypedDependency::class, ['dependency' => $customDep]);

        self::assertSame($customDep, $service->dependency);
    }
}

// Test helper classes

class DependencyA {}

class ServiceWithDependency
{
    public function __construct(
        public \stdClass $dependency,
    ) {}
}

class ServiceWithTypedDependency
{
    public function __construct(
        public DependencyA $dependency,
    ) {}
}

class ServiceWithValue
{
    public function __construct(
        public string $value,
    ) {}
}

class ServiceWithDefault
{
    public function __construct(
        public string $value = 'default',
    ) {}
}

class ServiceWithNullable
{
    public function __construct(
        public ?string $value,
    ) {}
}
