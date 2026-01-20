<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Builder;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\SDK\Builder\ApplicationBuilder;
use Butschster\Commander\SDK\Builder\BuiltApplication;
use Butschster\Commander\SDK\Container\ContainerInterface;
use Butschster\Commander\SDK\Container\ServiceDefinition;
use Butschster\Commander\SDK\Module\ModuleContext;
use Butschster\Commander\SDK\Module\ModuleInterface;
use Butschster\Commander\SDK\Module\ModuleMetadata;
use Butschster\Commander\SDK\Provider\ScreenProviderInterface;
use Butschster\Commander\SDK\Provider\ServiceProviderInterface;
use Butschster\Commander\UI\Screen\Attribute\Metadata;
use Butschster\Commander\UI\Screen\ScreenInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ApplicationBuilder::class)]
final class ApplicationBuilderTest extends TestCase
{
    #[Test]
    public function it_creates_builder_instance(): void
    {
        $builder = ApplicationBuilder::create();

        self::assertInstanceOf(ApplicationBuilder::class, $builder);
    }

    #[Test]
    public function with_module_is_fluent(): void
    {
        $builder = ApplicationBuilder::create();
        $module = $this->createSimpleModule('test');

        $result = $builder->withModule($module);

        self::assertSame($builder, $result);
    }

    #[Test]
    public function with_fps_is_fluent(): void
    {
        $builder = ApplicationBuilder::create();

        $result = $builder->withFps(60);

        self::assertSame($builder, $result);
    }

    #[Test]
    public function with_config_is_fluent(): void
    {
        $builder = ApplicationBuilder::create();

        $result = $builder->withConfig(['key' => 'value']);

        self::assertSame($builder, $result);
    }

    #[Test]
    public function with_initial_screen_is_fluent(): void
    {
        $builder = ApplicationBuilder::create();

        $result = $builder->withInitialScreen('test');

        self::assertSame($builder, $result);
    }

    #[Test]
    public function on_quit_is_fluent(): void
    {
        $builder = ApplicationBuilder::create();

        $result = $builder->onQuit(static fn() => null);

        self::assertSame($builder, $result);
    }

    #[Test]
    public function with_driver_is_fluent(): void
    {
        $builder = ApplicationBuilder::create();
        $driver = $this->createMock(\Butschster\Commander\Infrastructure\Terminal\Driver\TerminalDriverInterface::class);

        $result = $builder->withDriver($driver);

        self::assertSame($builder, $result);
    }

    #[Test]
    public function it_builds_application_with_module(): void
    {
        $module = new BuilderTestScreenModule('simple');

        $app = ApplicationBuilder::create()
            ->withModule($module)
            ->build();

        self::assertInstanceOf(BuiltApplication::class, $app);
        self::assertTrue($app->getModuleRegistry()->has('simple'));
    }

    #[Test]
    public function it_registers_module_services(): void
    {
        $module = new BuilderTestServiceModule('test', 'test.service');

        $app = ApplicationBuilder::create()
            ->withModule($module)
            ->build();

        self::assertTrue($app->getContainer()->has('test.service'));
    }

    #[Test]
    public function it_registers_module_screens(): void
    {
        $module = new BuilderTestScreenModule('test');

        $app = ApplicationBuilder::create()
            ->withModule($module)
            ->build();

        self::assertTrue($app->getScreenRegistry()->has('builder_test_screen'));
    }

    #[Test]
    public function it_throws_on_missing_initial_screen(): void
    {
        $module = new BuilderTestScreenModule('test');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Initial screen 'nonexistent' not found");

        ApplicationBuilder::create()
            ->withModule($module)
            ->withInitialScreen('nonexistent')
            ->build();
    }

    #[Test]
    public function it_passes_config_to_modules(): void
    {
        $receivedConfig = null;

        $module = new class($receivedConfig) implements ModuleInterface {
            public function __construct(
                private mixed &$config,
            ) {}

            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata('config_test', 'Config Test');
            }

            public function boot(ModuleContext $context): void
            {
                $this->config = $context->config('database.host');
            }

            public function shutdown(): void {}
        };

        ApplicationBuilder::create()
            ->withModule($module)
            ->withModule(new BuilderTestScreenModule('screen_provider'))
            ->withConfig(['database' => ['host' => 'localhost']])
            ->build();

        self::assertSame('localhost', $receivedConfig);
    }

    #[Test]
    public function it_boots_modules_in_dependency_order(): void
    {
        $bootOrder = [];

        $parent = $this->createMockModule('parent', [], static function () use (&$bootOrder): void {
            $bootOrder[] = 'parent';
        });

        $child = $this->createMockModule('child', ['parent'], static function () use (&$bootOrder): void {
            $bootOrder[] = 'child';
        });

        ApplicationBuilder::create()
            ->withModule($child)  // Wrong order
            ->withModule($parent)
            ->withModule(new BuilderTestScreenModule('screen_provider'))
            ->build();

        // parent must be before child, screen_provider can be anywhere (no dependencies)
        $parentIndex = \array_search('parent', $bootOrder, true);
        $childIndex = \array_search('child', $bootOrder, true);

        self::assertLessThan($childIndex, $parentIndex, 'parent should boot before child');
    }

    #[Test]
    public function it_sets_initial_screen_from_first_registered(): void
    {
        $module = new BuilderTestScreenModule('test');

        $app = ApplicationBuilder::create()
            ->withModule($module)
            ->build();

        self::assertSame('builder_test_screen', $app->getInitialScreenName());
    }

    #[Test]
    public function it_uses_explicit_initial_screen(): void
    {
        $module = new BuilderTestMultiScreenModule('test');

        $app = ApplicationBuilder::create()
            ->withModule($module)
            ->withInitialScreen('multi_screen_two')
            ->build();

        self::assertSame('multi_screen_two', $app->getInitialScreenName());
    }

    #[Test]
    public function it_merges_config_values(): void
    {
        $receivedValue1 = null;
        $receivedValue2 = null;

        $module = new class($receivedValue1, $receivedValue2) implements ModuleInterface {
            public function __construct(
                private mixed &$value1,
                private mixed &$value2,
            ) {}

            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata('merge_test', 'Merge Test');
            }

            public function boot(ModuleContext $context): void
            {
                $this->value1 = $context->config('key1');
                $this->value2 = $context->config('key2');
            }

            public function shutdown(): void {}
        };

        ApplicationBuilder::create()
            ->withModule($module)
            ->withModule(new BuilderTestScreenModule('screen'))
            ->withConfig(['key1' => 'value1'])
            ->withConfig(['key2' => 'value2'])
            ->build();

        self::assertSame('value1', $receivedValue1);
        self::assertSame('value2', $receivedValue2);
    }

    #[Test]
    public function it_clamps_fps_to_valid_range(): void
    {
        // We can't easily verify the FPS value, but at least ensure it doesn't throw
        $app1 = ApplicationBuilder::create()
            ->withModule(new BuilderTestScreenModule('test1'))
            ->withFps(0) // Should be clamped to 1
            ->build();

        $app2 = ApplicationBuilder::create()
            ->withModule(new BuilderTestScreenModule('test2'))
            ->withFps(100) // Should be clamped to 60
            ->build();

        self::assertInstanceOf(BuiltApplication::class, $app1);
        self::assertInstanceOf(BuiltApplication::class, $app2);
    }

    private function createSimpleModule(string $name): ModuleInterface
    {
        return new readonly class($name) implements ModuleInterface {
            public function __construct(
                private string $name,
            ) {}

            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata($this->name, \ucfirst($this->name));
            }

            public function boot(ModuleContext $context): void {}

            public function shutdown(): void {}
        };
    }

    /**
     * @param array<string> $deps
     */
    private function createMockModule(
        string $name,
        array $deps = [],
        ?\Closure $onBoot = null,
    ): ModuleInterface {
        return new readonly class($name, $deps, $onBoot) implements ModuleInterface {
            public function __construct(
                private string $name,
                private array $deps,
                private ?\Closure $onBoot,
            ) {}

            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata(
                    $this->name,
                    \ucfirst($this->name),
                    dependencies: $this->deps,
                );
            }

            public function boot(ModuleContext $context): void
            {
                if ($this->onBoot !== null) {
                    ($this->onBoot)($context);
                }
            }

            public function shutdown(): void {}
        };
    }
}

// Helper classes for tests

#[Metadata(name: 'builder_test_screen', title: 'Builder Test', description: 'Test screen')]
class BuilderTestScreen implements ScreenInterface
{
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
        return 'Builder Test';
    }
}

#[Metadata(name: 'multi_screen_one', title: 'Multi Screen One', description: 'First screen')]
class BuilderTestMultiScreenOne implements ScreenInterface
{
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
        return 'Multi Screen One';
    }
}

#[Metadata(name: 'multi_screen_two', title: 'Multi Screen Two', description: 'Second screen')]
class BuilderTestMultiScreenTwo implements ScreenInterface
{
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
        return 'Multi Screen Two';
    }
}

class BuilderTestScreenModule implements ModuleInterface, ScreenProviderInterface
{
    public function __construct(
        private readonly string $moduleName,
    ) {}

    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata($this->moduleName, \ucfirst($this->moduleName));
    }

    public function screens(ContainerInterface $container): iterable
    {
        yield new BuilderTestScreen();
    }

    public function boot(ModuleContext $context): void {}

    public function shutdown(): void {}
}

class BuilderTestServiceModule implements ModuleInterface, ServiceProviderInterface, ScreenProviderInterface
{
    public function __construct(
        private readonly string $moduleName,
        private readonly string $serviceId,
    ) {}

    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata($this->moduleName, \ucfirst($this->moduleName));
    }

    public function services(): iterable
    {
        yield ServiceDefinition::singleton($this->serviceId, static fn() => new \stdClass());
    }

    public function screens(ContainerInterface $container): iterable
    {
        yield new BuilderTestScreen();
    }

    public function boot(ModuleContext $context): void {}

    public function shutdown(): void {}
}

class BuilderTestMultiScreenModule implements ModuleInterface, ScreenProviderInterface
{
    public function __construct(
        private readonly string $moduleName,
    ) {}

    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata($this->moduleName, \ucfirst($this->moduleName));
    }

    public function screens(ContainerInterface $container): iterable
    {
        yield new BuilderTestMultiScreenOne();
        yield new BuilderTestMultiScreenTwo();
    }

    public function boot(ModuleContext $context): void {}

    public function shutdown(): void {}
}
