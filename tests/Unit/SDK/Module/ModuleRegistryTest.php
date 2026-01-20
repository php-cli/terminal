<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Module;

use Butschster\Commander\SDK\Container\Container;
use Butschster\Commander\SDK\Exception\ModuleConflictException;
use Butschster\Commander\SDK\Exception\ModuleDependencyException;
use Butschster\Commander\SDK\Exception\ModuleNotFoundException;
use Butschster\Commander\SDK\Module\ModuleContext;
use Butschster\Commander\SDK\Module\ModuleInterface;
use Butschster\Commander\SDK\Module\ModuleMetadata;
use Butschster\Commander\SDK\Module\ModuleRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ModuleRegistry::class)]
final class ModuleRegistryTest extends TestCase
{
    #[Test]
    public function it_registers_module(): void
    {
        $registry = new ModuleRegistry();
        $module = $this->createModule('test');

        $registry->register($module);

        self::assertTrue($registry->has('test'));
        self::assertSame($module, $registry->get('test'));
    }

    #[Test]
    public function it_returns_false_for_unregistered_module(): void
    {
        $registry = new ModuleRegistry();

        self::assertFalse($registry->has('nonexistent'));
    }

    #[Test]
    public function it_throws_on_get_unregistered_module(): void
    {
        $registry = new ModuleRegistry();

        $this->expectException(ModuleNotFoundException::class);
        $this->expectExceptionMessage("Module 'nonexistent' not found");

        $registry->get('nonexistent');
    }

    #[Test]
    public function it_throws_on_duplicate_registration(): void
    {
        $registry = new ModuleRegistry();
        $registry->register($this->createModule('test'));

        $this->expectException(ModuleConflictException::class);
        $this->expectExceptionMessage("Module 'test' is already registered");

        $registry->register($this->createModule('test'));
    }

    #[Test]
    public function it_returns_all_modules(): void
    {
        $registry = new ModuleRegistry();
        $module1 = $this->createModule('first');
        $module2 = $this->createModule('second');

        $registry->register($module1);
        $registry->register($module2);

        $all = $registry->all();

        self::assertCount(2, $all);
        self::assertSame($module1, $all['first']);
        self::assertSame($module2, $all['second']);
    }

    #[Test]
    public function it_returns_all_metadata(): void
    {
        $registry = new ModuleRegistry();
        $registry->register($this->createModule('first'));
        $registry->register($this->createModule('second'));

        $metadata = $registry->allMetadata();

        self::assertCount(2, $metadata);
        self::assertArrayHasKey('first', $metadata);
        self::assertArrayHasKey('second', $metadata);
        self::assertInstanceOf(ModuleMetadata::class, $metadata['first']);
    }

    #[Test]
    public function it_throws_on_missing_dependency(): void
    {
        $registry = new ModuleRegistry();
        $registry->register($this->createModule('child', ['parent']));

        $this->expectException(ModuleDependencyException::class);
        $this->expectExceptionMessage("Module 'child' requires 'parent'");

        $registry->boot($this->createContext());
    }

    #[Test]
    public function it_boots_modules_in_dependency_order(): void
    {
        $bootOrder = [];

        $parent = $this->createModule('parent', [], static function () use (&$bootOrder): void {
            $bootOrder[] = 'parent';
        });

        $child = $this->createModule('child', ['parent'], static function () use (&$bootOrder): void {
            $bootOrder[] = 'child';
        });

        $registry = new ModuleRegistry();
        $registry->register($child);  // Register in wrong order
        $registry->register($parent);

        $registry->boot($this->createContext());

        self::assertSame(['parent', 'child'], $bootOrder);
    }

    #[Test]
    public function it_handles_diamond_dependencies(): void
    {
        $bootOrder = [];

        $a = $this->createModule('a', [], static function () use (&$bootOrder): void {
            $bootOrder[] = 'a';
        });
        $b = $this->createModule('b', ['a'], static function () use (&$bootOrder): void {
            $bootOrder[] = 'b';
        });
        $c = $this->createModule('c', ['a'], static function () use (&$bootOrder): void {
            $bootOrder[] = 'c';
        });
        $d = $this->createModule('d', ['b', 'c'], static function () use (&$bootOrder): void {
            $bootOrder[] = 'd';
        });

        $registry = new ModuleRegistry();
        $registry->register($d);
        $registry->register($c);
        $registry->register($b);
        $registry->register($a);

        $registry->boot($this->createContext());

        // A must be first, D must be last
        self::assertSame('a', $bootOrder[0]);
        self::assertSame('d', $bootOrder[3]);
        // B and C can be in any order between A and D
        self::assertContains('b', [$bootOrder[1], $bootOrder[2]]);
        self::assertContains('c', [$bootOrder[1], $bootOrder[2]]);
    }

    #[Test]
    public function it_shuts_down_in_reverse_order(): void
    {
        $shutdownOrder = [];

        $parent = $this->createModule('parent', [], null, static function () use (&$shutdownOrder): void {
            $shutdownOrder[] = 'parent';
        });

        $child = $this->createModule('child', ['parent'], null, static function () use (&$shutdownOrder): void {
            $shutdownOrder[] = 'child';
        });

        $registry = new ModuleRegistry();
        $registry->register($parent);
        $registry->register($child);
        $registry->boot($this->createContext());

        $registry->shutdown();

        self::assertSame(['child', 'parent'], $shutdownOrder);
    }

    #[Test]
    public function it_continues_shutdown_on_exception(): void
    {
        $shutdownOrder = [];

        $first = $this->createModule('first', [], null, static function () use (&$shutdownOrder): void {
            $shutdownOrder[] = 'first';
        });

        $second = $this->createModule('second', [], null, static function (): never {
            throw new \RuntimeException('Shutdown error');
        });

        $third = $this->createModule('third', [], null, static function () use (&$shutdownOrder): void {
            $shutdownOrder[] = 'third';
        });

        $registry = new ModuleRegistry();
        $registry->register($first);
        $registry->register($second);
        $registry->register($third);
        $registry->boot($this->createContext());

        $registry->shutdown();

        // Both first and third should still be shut down
        self::assertContains('first', $shutdownOrder);
        self::assertContains('third', $shutdownOrder);
    }

    #[Test]
    public function it_detects_circular_dependency(): void
    {
        $registry = new ModuleRegistry();
        $registry->register($this->createModule('a', ['b']));
        $registry->register($this->createModule('b', ['a']));

        $this->expectException(ModuleDependencyException::class);
        $this->expectExceptionMessageMatches('/circular/i');

        $registry->boot($this->createContext());
    }

    #[Test]
    public function it_detects_longer_circular_dependency(): void
    {
        $registry = new ModuleRegistry();
        $registry->register($this->createModule('a', ['c']));
        $registry->register($this->createModule('b', ['a']));
        $registry->register($this->createModule('c', ['b']));

        $this->expectException(ModuleDependencyException::class);
        $this->expectExceptionMessageMatches('/circular/i');

        $registry->boot($this->createContext());
    }

    #[Test]
    public function it_returns_boot_order(): void
    {
        $registry = new ModuleRegistry();
        $parent = $this->createModule('parent');
        $child = $this->createModule('child', ['parent']);

        $registry->register($child);
        $registry->register($parent);
        $registry->boot($this->createContext());

        $bootOrder = $registry->getBootOrder();

        self::assertCount(2, $bootOrder);
        self::assertSame($parent, $bootOrder[0]);
        self::assertSame($child, $bootOrder[1]);
    }

    #[Test]
    public function it_gets_modules_implementing_interface(): void
    {
        $registry = new ModuleRegistry();
        $plain = $this->createModule('plain');
        $withInterface = $this->createModuleWithInterface('with_interface');

        $registry->register($plain);
        $registry->register($withInterface);
        $registry->boot($this->createContext());

        $implementing = $registry->getImplementing(TestProviderInterface::class);

        self::assertCount(1, $implementing);
        self::assertSame($withInterface, $implementing[0]);
    }

    #[Test]
    public function it_cannot_register_after_boot(): void
    {
        $registry = new ModuleRegistry();
        $registry->register($this->createModule('first'));
        $registry->boot($this->createContext());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot register modules after boot');

        $registry->register($this->createModule('second'));
    }

    #[Test]
    public function it_cannot_boot_twice(): void
    {
        $registry = new ModuleRegistry();
        $registry->register($this->createModule('test'));
        $registry->boot($this->createContext());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Modules already booted');

        $registry->boot($this->createContext());
    }

    #[Test]
    public function it_reports_booted_state(): void
    {
        $registry = new ModuleRegistry();
        $registry->register($this->createModule('test'));

        self::assertFalse($registry->isBooted());

        $registry->boot($this->createContext());

        self::assertTrue($registry->isBooted());

        $registry->shutdown();

        self::assertFalse($registry->isBooted());
    }

    #[Test]
    public function shutdown_does_nothing_if_not_booted(): void
    {
        $shutdownCalled = false;
        $module = $this->createModule('test', [], null, static function () use (&$shutdownCalled): void {
            $shutdownCalled = true;
        });

        $registry = new ModuleRegistry();
        $registry->register($module);

        $registry->shutdown();

        self::assertFalse($shutdownCalled);
    }

    /**
     * @param array<string> $deps
     */
    private function createModule(
        string $name,
        array $deps = [],
        ?\Closure $onBoot = null,
        ?\Closure $onShutdown = null,
    ): ModuleInterface {
        return new readonly class($name, $deps, $onBoot, $onShutdown) implements ModuleInterface {
            public function __construct(
                private string $name,
                private array $deps,
                private ?\Closure $onBoot,
                private ?\Closure $onShutdown,
            ) {}

            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata($this->name, $this->name, dependencies: $this->deps);
            }

            public function boot(ModuleContext $context): void
            {
                if ($this->onBoot) {
                    ($this->onBoot)($context);
                }
            }

            public function shutdown(): void
            {
                if ($this->onShutdown) {
                    ($this->onShutdown)();
                }
            }
        };
    }

    private function createModuleWithInterface(string $name): ModuleInterface
    {
        return new readonly class($name) implements ModuleInterface, TestProviderInterface {
            public function __construct(
                private string $name,
            ) {}

            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata($this->name, $this->name);
            }

            public function boot(ModuleContext $context): void {}

            public function shutdown(): void {}

            public function provide(): string
            {
                return 'test';
            }
        };
    }

    private function createContext(): ModuleContext
    {
        return new ModuleContext(new Container());
    }
}

interface TestProviderInterface
{
    public function provide(): string;
}
