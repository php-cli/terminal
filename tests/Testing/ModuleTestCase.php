<?php

declare(strict_types=1);

namespace Tests\Testing;

use Butschster\Commander\SDK\Builder\BuiltApplication;
use Butschster\Commander\SDK\Container\Container;
use Butschster\Commander\SDK\Container\ContainerInterface;
use Butschster\Commander\SDK\Module\ModuleContext;
use Butschster\Commander\SDK\Module\ModuleInterface;
use Butschster\Commander\SDK\Module\ModuleMetadata;
use Butschster\Commander\SDK\Module\ModuleRegistry;
use Butschster\Commander\SDK\Provider\ServiceProviderInterface;
use Butschster\Commander\UI\Screen\ScreenManager;
use Tests\TerminalTestCase;

/**
 * Base test case for module tests.
 *
 * Provides helpers for creating containers, contexts, and testing modules.
 */
abstract class ModuleTestCase extends TerminalTestCase
{
    protected Container $container;
    protected ScreenManager $screenManager;
    protected ModuleRegistry $moduleRegistry;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();
        $this->screenManager = new ScreenManager();
        $this->moduleRegistry = new ModuleRegistry();

        // Register core services
        $this->container->instance(ScreenManager::class, $this->screenManager);
        $this->container->instance(ContainerInterface::class, $this->container);
    }

    /**
     * Create module context with optional config.
     *
     * @param array<string, mixed> $config
     */
    protected function createContext(array $config = []): ModuleContext
    {
        return new ModuleContext(
            $this->container,
            $config,
        );
    }

    /**
     * Boot a module and return the container.
     *
     * @param array<string, mixed> $config
     */
    protected function bootModule(
        ModuleInterface $module,
        array $config = [],
    ): ContainerInterface {
        // Register services if provider
        if ($module instanceof ServiceProviderInterface) {
            foreach ($module->services() as $definition) {
                if ($definition->singleton) {
                    $this->container->singleton($definition->id, $definition->factory);
                } else {
                    $this->container->bind($definition->id, $definition->factory);
                }
            }
        }

        // Boot module
        $context = $this->createContext($config);
        $module->boot($context);

        return $this->container;
    }

    /**
     * Create a mock module for testing.
     *
     * @param array<string> $dependencies
     */
    protected function createMockModule(
        string $name,
        array $dependencies = [],
        ?\Closure $onBoot = null,
        ?\Closure $onShutdown = null,
    ): ModuleInterface {
        return new readonly class($name, $dependencies, $onBoot, $onShutdown) implements ModuleInterface {
            public function __construct(
                private string $name,
                private array $deps,
                private ?\Closure $onBoot,
                private ?\Closure $onShutdown,
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

            public function shutdown(): void
            {
                if ($this->onShutdown !== null) {
                    ($this->onShutdown)();
                }
            }
        };
    }

    /**
     * Run a built application with virtual terminal.
     */
    protected function runBuiltApp(BuiltApplication $app): void
    {
        $this->driver->initialize();

        $innerApp = $app->getInnerApplication();
        $screenManager = $innerApp->getScreenManager();
        $renderer = $innerApp->getRenderer();

        // Get initial screen
        $initialScreenName = $app->getInitialScreenName();
        if ($initialScreenName !== null) {
            $initialScreen = $app->getScreenRegistry()->getScreen($initialScreenName);
            if ($initialScreen !== null) {
                $screenManager->pushScreen($initialScreen);
            }
        }

        $maxIterations = 1000;
        $iterations = 0;

        while ($this->driver->hasInput() && $iterations < $maxIterations) {
            $iterations++;

            while (($key = $this->driver->readInput()) !== null) {
                $screenManager->handleInput($key);
            }

            $screenManager->update();
            $renderer->beginFrame();

            $size = $renderer->getSize();
            $screenManager->render($renderer, 0, 0, $size['width'], $size['height']);

            $renderer->endFrame();
        }

        // Final render
        $renderer->beginFrame();
        $size = $renderer->getSize();
        $screenManager->render($renderer, 0, 0, $size['width'], $size['height']);
        $renderer->endFrame();
    }

    /**
     * Assert module is registered.
     */
    protected function assertModuleRegistered(string $name): void
    {
        self::assertTrue(
            $this->moduleRegistry->has($name),
            "Module '{$name}' should be registered",
        );
    }

    /**
     * Assert service exists in container.
     */
    protected function assertServiceExists(string $id): void
    {
        self::assertTrue(
            $this->container->has($id),
            "Service '{$id}' should exist in container",
        );
    }
}
