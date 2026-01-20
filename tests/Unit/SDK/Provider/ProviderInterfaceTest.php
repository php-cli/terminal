<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Provider;

use Butschster\Commander\Infrastructure\Keyboard\KeyBinding;
use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\SDK\Container\Container;
use Butschster\Commander\SDK\Container\ContainerInterface;
use Butschster\Commander\SDK\Container\ServiceDefinition;
use Butschster\Commander\SDK\Module\ModuleContext;
use Butschster\Commander\SDK\Module\ModuleInterface;
use Butschster\Commander\SDK\Module\ModuleMetadata;
use Butschster\Commander\SDK\Provider\KeyBindingProviderInterface;
use Butschster\Commander\SDK\Provider\MenuProviderInterface;
use Butschster\Commander\SDK\Provider\ScreenProviderInterface;
use Butschster\Commander\SDK\Provider\ServiceProviderInterface;
use Butschster\Commander\UI\Menu\MenuDefinition;
use Butschster\Commander\UI\Screen\ScreenInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ScreenProviderInterface::class)]
#[CoversClass(MenuProviderInterface::class)]
#[CoversClass(KeyBindingProviderInterface::class)]
#[CoversClass(ServiceProviderInterface::class)]
final class ProviderInterfaceTest extends TestCase
{
    #[Test]
    public function screen_provider_yields_screens(): void
    {
        $module = new class implements ModuleInterface, ScreenProviderInterface {
            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata('test', 'Test');
            }

            public function screens(ContainerInterface $container): iterable
            {
                yield $this->createMockScreen('screen1');
                yield $this->createMockScreen('screen2');
            }

            public function boot(ModuleContext $context): void {}

            public function shutdown(): void {}

            private function createMockScreen(string $name): ScreenInterface
            {
                return new readonly class($name) implements ScreenInterface {
                    public function __construct(
                        private string $name,
                    ) {}

                    public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void {}

                    public function handleInput(string $key): bool
                    {
                        return false;
                    }

                    public function onActivate(): void {}

                    public function onDeactivate(): void {}

                    public function update(): void {}

                    public function getTitle(): string
                    {
                        return $this->name;
                    }
                };
            }
        };

        $container = new Container();
        $screens = \iterator_to_array($module->screens($container));

        self::assertCount(2, $screens);
        self::assertContainsOnlyInstancesOf(ScreenInterface::class, $screens);
        self::assertSame('screen1', $screens[0]->getTitle());
        self::assertSame('screen2', $screens[1]->getTitle());
    }

    #[Test]
    public function screen_provider_can_use_container(): void
    {
        $module = new class implements ModuleInterface, ScreenProviderInterface {
            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata('test', 'Test');
            }

            public function screens(ContainerInterface $container): iterable
            {
                $service = $container->get('test.service');
                yield new readonly class($service->value) implements ScreenInterface {
                    public function __construct(
                        public string $serviceValue,
                    ) {}

                    public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void {}

                    public function handleInput(string $key): bool
                    {
                        return false;
                    }

                    public function onActivate(): void {}

                    public function onDeactivate(): void {}

                    public function update(): void {}

                    public function getTitle(): string
                    {
                        return 'Test';
                    }
                };
            }

            public function boot(ModuleContext $context): void {}

            public function shutdown(): void {}
        };

        $container = new Container();
        $service = new \stdClass();
        $service->value = 'injected';
        $container->instance('test.service', $service);

        $screens = \iterator_to_array($module->screens($container));

        self::assertCount(1, $screens);
        self::assertSame('injected', $screens[0]->serviceValue);
    }

    #[Test]
    public function menu_provider_yields_menu_definitions(): void
    {
        $module = new class implements ModuleInterface, MenuProviderInterface {
            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata('test', 'Test');
            }

            public function menus(): iterable
            {
                yield new MenuDefinition('Menu 1', null, [], 10);
                yield new MenuDefinition('Menu 2', null, [], 20);
            }

            public function boot(ModuleContext $context): void {}

            public function shutdown(): void {}
        };

        $menus = \iterator_to_array($module->menus());

        self::assertCount(2, $menus);
        self::assertContainsOnlyInstancesOf(MenuDefinition::class, $menus);
        self::assertSame('Menu 1', $menus[0]->label);
        self::assertSame(10, $menus[0]->priority);
        self::assertSame('Menu 2', $menus[1]->label);
        self::assertSame(20, $menus[1]->priority);
    }

    #[Test]
    public function menu_provider_can_yield_with_fkey(): void
    {
        $module = new class implements ModuleInterface, MenuProviderInterface {
            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata('test', 'Test');
            }

            public function menus(): iterable
            {
                yield new MenuDefinition(
                    'Files',
                    KeyCombination::fromString('F1'),
                    [],
                    10,
                );
            }

            public function boot(ModuleContext $context): void {}

            public function shutdown(): void {}
        };

        $menus = \iterator_to_array($module->menus());

        self::assertCount(1, $menus);
        self::assertSame('Files', $menus[0]->label);
        self::assertNotNull($menus[0]->fkey);
    }

    #[Test]
    public function service_provider_yields_definitions(): void
    {
        $module = new class implements ModuleInterface, ServiceProviderInterface {
            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata('test', 'Test');
            }

            public function services(): iterable
            {
                yield ServiceDefinition::singleton('service1', static fn() => new \stdClass());
                yield ServiceDefinition::transient('service2', static fn() => new \stdClass());
            }

            public function boot(ModuleContext $context): void {}

            public function shutdown(): void {}
        };

        $services = \iterator_to_array($module->services());

        self::assertCount(2, $services);
        self::assertContainsOnlyInstancesOf(ServiceDefinition::class, $services);
        self::assertSame('service1', $services[0]->id);
        self::assertTrue($services[0]->singleton);
        self::assertSame('service2', $services[1]->id);
        self::assertFalse($services[1]->singleton);
    }

    #[Test]
    public function key_binding_provider_yields_bindings(): void
    {
        $module = new class implements ModuleInterface, KeyBindingProviderInterface {
            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata('test', 'Test');
            }

            public function keyBindings(): iterable
            {
                yield new KeyBinding(
                    KeyCombination::fromString('Ctrl+S'),
                    'test.save',
                    'Save',
                    'test',
                );
                yield new KeyBinding(
                    KeyCombination::fromString('Ctrl+O'),
                    'test.open',
                    'Open',
                    'test',
                );
            }

            public function boot(ModuleContext $context): void {}

            public function shutdown(): void {}
        };

        $bindings = \iterator_to_array($module->keyBindings());

        self::assertCount(2, $bindings);
        self::assertContainsOnlyInstancesOf(KeyBinding::class, $bindings);
        self::assertSame('test.save', $bindings[0]->actionId);
        self::assertSame('Save', $bindings[0]->description);
        self::assertSame('test.open', $bindings[1]->actionId);
    }

    #[Test]
    public function module_can_implement_multiple_providers(): void
    {
        $module = new class implements
            ModuleInterface,
            ScreenProviderInterface,
            MenuProviderInterface,
            ServiceProviderInterface {
            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata('multi', 'Multi Provider');
            }

            public function screens(ContainerInterface $c): iterable
            {
                return [];
            }

            public function menus(): iterable
            {
                return [];
            }

            public function services(): iterable
            {
                return [];
            }

            public function boot(ModuleContext $context): void {}

            public function shutdown(): void {}
        };

        self::assertInstanceOf(ModuleInterface::class, $module);
        self::assertInstanceOf(ScreenProviderInterface::class, $module);
        self::assertInstanceOf(MenuProviderInterface::class, $module);
        self::assertInstanceOf(ServiceProviderInterface::class, $module);
        self::assertNotInstanceOf(KeyBindingProviderInterface::class, $module);
    }

    #[Test]
    public function module_can_implement_all_providers(): void
    {
        $module = new class implements
            ModuleInterface,
            ScreenProviderInterface,
            MenuProviderInterface,
            KeyBindingProviderInterface,
            ServiceProviderInterface {
            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata('full', 'Full Module');
            }

            public function screens(ContainerInterface $c): iterable
            {
                return [];
            }

            public function menus(): iterable
            {
                return [];
            }

            public function keyBindings(): iterable
            {
                return [];
            }

            public function services(): iterable
            {
                return [];
            }

            public function boot(ModuleContext $context): void {}

            public function shutdown(): void {}
        };

        self::assertInstanceOf(ModuleInterface::class, $module);
        self::assertInstanceOf(ScreenProviderInterface::class, $module);
        self::assertInstanceOf(MenuProviderInterface::class, $module);
        self::assertInstanceOf(KeyBindingProviderInterface::class, $module);
        self::assertInstanceOf(ServiceProviderInterface::class, $module);
    }

    #[Test]
    public function module_can_implement_only_module_interface(): void
    {
        $module = new class implements ModuleInterface {
            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata('minimal', 'Minimal Module');
            }

            public function boot(ModuleContext $context): void {}

            public function shutdown(): void {}
        };

        self::assertInstanceOf(ModuleInterface::class, $module);
        self::assertNotInstanceOf(ScreenProviderInterface::class, $module);
        self::assertNotInstanceOf(MenuProviderInterface::class, $module);
        self::assertNotInstanceOf(KeyBindingProviderInterface::class, $module);
        self::assertNotInstanceOf(ServiceProviderInterface::class, $module);
    }

    #[Test]
    public function providers_can_return_empty_iterables(): void
    {
        $module = new class implements
            ModuleInterface,
            ScreenProviderInterface,
            MenuProviderInterface,
            KeyBindingProviderInterface,
            ServiceProviderInterface {
            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata('empty', 'Empty Providers');
            }

            public function screens(ContainerInterface $c): iterable
            {
                return [];
            }

            public function menus(): iterable
            {
                return [];
            }

            public function keyBindings(): iterable
            {
                return [];
            }

            public function services(): iterable
            {
                return [];
            }

            public function boot(ModuleContext $context): void {}

            public function shutdown(): void {}
        };

        $container = new Container();

        self::assertCount(0, \iterator_to_array($module->screens($container)));
        self::assertCount(0, \iterator_to_array($module->menus()));
        self::assertCount(0, \iterator_to_array($module->keyBindings()));
        self::assertCount(0, \iterator_to_array($module->services()));
    }
}
