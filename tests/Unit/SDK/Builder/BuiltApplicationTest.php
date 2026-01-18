<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Builder;

use Butschster\Commander\Application;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\SDK\Builder\ApplicationBuilder;
use Butschster\Commander\SDK\Builder\BuiltApplication;
use Butschster\Commander\SDK\Container\ContainerInterface;
use Butschster\Commander\SDK\Module\ModuleContext;
use Butschster\Commander\SDK\Module\ModuleInterface;
use Butschster\Commander\SDK\Module\ModuleMetadata;
use Butschster\Commander\SDK\Module\ModuleRegistry;
use Butschster\Commander\SDK\Provider\ScreenProviderInterface;
use Butschster\Commander\UI\Screen\Attribute\Metadata;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\UI\Screen\ScreenRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(BuiltApplication::class)]
final class BuiltApplicationTest extends TestCase
{
    #[Test]
    public function it_provides_container_access(): void
    {
        $app = $this->createBuiltApp();

        self::assertInstanceOf(ContainerInterface::class, $app->getContainer());
    }

    #[Test]
    public function it_provides_module_registry_access(): void
    {
        $app = $this->createBuiltApp();

        self::assertInstanceOf(ModuleRegistry::class, $app->getModuleRegistry());
    }

    #[Test]
    public function it_provides_screen_registry_access(): void
    {
        $app = $this->createBuiltApp();

        self::assertInstanceOf(ScreenRegistry::class, $app->getScreenRegistry());
    }

    #[Test]
    public function it_provides_inner_application_access(): void
    {
        $app = $this->createBuiltApp();

        self::assertInstanceOf(Application::class, $app->getInnerApplication());
    }

    #[Test]
    public function it_provides_initial_screen_name(): void
    {
        $app = $this->createBuiltApp();

        self::assertSame('built_app_test_screen', $app->getInitialScreenName());
    }

    #[Test]
    public function it_calls_module_shutdown_when_finished(): void
    {
        $shutdownCalled = false;

        $module = new BuiltAppShutdownTestModule($shutdownCalled);

        $app = ApplicationBuilder::create()
            ->withModule($module)
            ->build();

        // Manually trigger shutdown through the registry
        $app->getModuleRegistry()->shutdown();

        self::assertTrue($shutdownCalled);
    }

    private function createBuiltApp(): BuiltApplication
    {
        return ApplicationBuilder::create()
            ->withModule(new BuiltAppTestScreenModule('test'))
            ->build();
    }
}

// Helper classes for tests

#[Metadata(name: 'built_app_test_screen', title: 'Built App Test', description: 'Test screen')]
class BuiltAppTestScreen implements ScreenInterface
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
        return 'Built App Test';
    }
}

#[Metadata(name: 'shutdown_test_screen', title: 'Shutdown Test', description: 'Test screen')]
class BuiltAppShutdownTestScreen implements ScreenInterface
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
        return 'Shutdown Test';
    }
}

class BuiltAppTestScreenModule implements ModuleInterface, ScreenProviderInterface
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
        yield new BuiltAppTestScreen();
    }

    public function boot(ModuleContext $context): void {}

    public function shutdown(): void {}
}

class BuiltAppShutdownTestModule implements ModuleInterface, ScreenProviderInterface
{
    public function __construct(
        private bool &$shutdownCalled,
    ) {}

    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata('shutdown_test', 'Shutdown Test');
    }

    public function screens(ContainerInterface $container): iterable
    {
        yield new BuiltAppShutdownTestScreen();
    }

    public function boot(ModuleContext $context): void {}

    public function shutdown(): void
    {
        $this->shutdownCalled = true;
    }
}
