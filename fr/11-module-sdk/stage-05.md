# Stage 5: Application Builder

## Overview

Implement the `ApplicationBuilder` fluent API that wires everything together: modules, container, registry, screens,
menus, and key bindings. This is the main integration point that makes the SDK usable.

**Why this stage**: This is where all previous stages come together. The builder provides the clean, user-facing API.

## Files

**CREATE:**

```
src/SDK/Builder/
├── ApplicationBuilder.php     - Fluent builder API
└── BuiltApplication.php       - Wrapper around configured Application
```

**CREATE (Tests):**

```
tests/Unit/SDK/Builder/
├── ApplicationBuilderTest.php
└── BuiltApplicationTest.php

tests/Integration/SDK/
└── ApplicationBuilderIntegrationTest.php

tests/Testing/
└── ModuleTestCase.php         - Base test class for module tests
```

## Code References

### Existing Code to Integrate With

- `src/Application.php:27-45` - Application constructor and initialization
- `src/Application.php:72-78` - `setScreenRegistry()`
- `src/Application.php:86-99` - `setMenuSystem()`
- `src/Application.php:101-116` - `onAction()` for key bindings
- `src/UI/Screen/ScreenRegistry.php:30-48` - Screen registration
- `src/UI/Menu/MenuBuilder.php:29-72` - Menu building logic to port
- `src/UI/Theme/ColorScheme.php:15-25` - Theme application
- `console:32-65` - Current bootstrap pattern to replace

### Integration Flow

```
ApplicationBuilder::create()
    │
    ├─→ withModule() → collects modules
    ├─→ withTheme() → stores theme
    ├─→ withConfig() → stores config
    ├─→ withFps() → stores fps
    ├─→ withInitialScreen() → stores screen name
    │
    └─→ build()
         │
         ├─→ Create Container
         ├─→ Register core services (ScreenManager, etc.)
         │
         ├─→ Create ModuleRegistry
         ├─→ Register all modules
         │
         ├─→ Collect services from ServiceProviderInterface
         ├─→ Register services in Container
         │
         ├─→ Boot all modules
         │
         ├─→ Collect screens from ScreenProviderInterface
         ├─→ Register in ScreenRegistry
         │
         ├─→ Collect menus from MenuProviderInterface
         ├─→ Add built-in Quit menu
         ├─→ Sort by priority
         │
         ├─→ Collect key bindings from KeyBindingProviderInterface
         ├─→ Register in KeyBindingRegistry
         │
         ├─→ Apply theme
         ├─→ Create Application
         ├─→ Wire everything
         │
         └─→ Return BuiltApplication
```

## Implementation Details

### ApplicationBuilder

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Builder;

use Butschster\Commander\Application;
use Butschster\Commander\Infrastructure\Keyboard\KeyBinding;
use Butschster\Commander\Infrastructure\Keyboard\KeyBindingRegistry;
use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;
use Butschster\Commander\SDK\Container\Container;
use Butschster\Commander\SDK\Container\ContainerInterface;
use Butschster\Commander\SDK\Module\ModuleContext;
use Butschster\Commander\SDK\Module\ModuleInterface;
use Butschster\Commander\SDK\Module\ModuleRegistry;
use Butschster\Commander\SDK\Provider\KeyBindingProviderInterface;
use Butschster\Commander\SDK\Provider\MenuProviderInterface;
use Butschster\Commander\SDK\Provider\ScreenProviderInterface;
use Butschster\Commander\SDK\Provider\ServiceProviderInterface;
use Butschster\Commander\UI\Menu\ActionMenuItem;
use Butschster\Commander\UI\Menu\MenuDefinition;
use Butschster\Commander\UI\Screen\ScreenManager;
use Butschster\Commander\UI\Screen\ScreenRegistry;
use Butschster\Commander\UI\Theme\ColorScheme;
use Butschster\Commander\UI\Theme\ThemeInterface;

/**
 * Fluent builder for creating configured applications.
 * 
 * Usage:
 * ```php
 * $app = ApplicationBuilder::create()
 *     ->withModule(new FileBrowserModule())
 *     ->withTheme(new MidnightTheme())
 *     ->withConfig(['database.dsn' => '...'])
 *     ->build();
 * 
 * $app->run();
 * ```

*/
final class ApplicationBuilder
{
/** @var array<ModuleInterface> */
private array $modules = [];

    private ?ThemeInterface $theme = null;
    private int $fps = 30;
    
    /** @var array<string, mixed> */
    private array $config = [];
    
    private ?string $initialScreen = null;
    
    /** @var \Closure|null */
    private ?\Closure $onQuit = null;
    
    private function __construct() {}
    
    /**
     * Create a new builder instance.
     */
    public static function create(): self
    {
        return new self();
    }
    
    /**
     * Register a module.
     */
    public function withModule(ModuleInterface $module): self
    {
        $this->modules[] = $module;
        return $this;
    }
    
    /**
     * Set application theme.
     */
    public function withTheme(ThemeInterface $theme): self
    {
        $this->theme = $theme;
        return $this;
    }
    
    /**
     * Set target FPS (1-60).
     */
    public function withFps(int $fps): self
    {
        $this->fps = max(1, min(60, $fps));
        return $this;
    }
    
    /**
     * Provide configuration values.
     * 
     * @param array<string, mixed> $config
     */
    public function withConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }
    
    /**
     * Set initial screen to show.
     * 
     * @param string $screenName Screen name from #[Metadata] attribute
     */
    public function withInitialScreen(string $screenName): self
    {
        $this->initialScreen = $screenName;
        return $this;
    }
    
    /**
     * Set callback for quit action.
     */
    public function onQuit(callable $callback): self
    {
        $this->onQuit = $callback(...);
        return $this;
    }
    
    /**
     * Build the configured application.
     * 
     * @throws \RuntimeException If configuration is invalid
     */
    public function build(): BuiltApplication
    {
        // 1. Create container
        $container = new Container();
        
        // 2. Create module registry
        $moduleRegistry = new ModuleRegistry();
        
        // 3. Register all modules
        foreach ($this->modules as $module) {
            $moduleRegistry->register($module);
        }
        
        // 4. Register core services
        $screenManager = new ScreenManager();
        $container->instance(ScreenManager::class, $screenManager);
        $container->instance(ContainerInterface::class, $container);
        
        // 5. Collect and register services from providers
        $this->registerModuleServices($moduleRegistry, $container);
        
        // 6. Create context and boot modules
        $context = new ModuleContext($container, $screenManager, $this->config);
        $moduleRegistry->boot($context);
        
        // 7. Apply theme
        if ($this->theme !== null) {
            ColorScheme::applyTheme($this->theme);
        }
        
        // 8. Create key binding registry
        $keyBindings = new KeyBindingRegistry();
        $this->registerModuleKeyBindings($moduleRegistry, $keyBindings);
        
        // 9. Create application
        $app = new Application($keyBindings);
        $app->setTargetFps($this->fps);
        
        // 10. Create and configure screen registry
        $screenRegistry = new ScreenRegistry($screenManager);
        $this->registerModuleScreens($moduleRegistry, $container, $screenRegistry);
        $app->setScreenRegistry($screenRegistry);
        
        // 11. Build and set menu system
        $menus = $this->buildMenuSystem($moduleRegistry, $keyBindings);
        $app->setMenuSystem($menus);
        
        // 12. Resolve initial screen
        $initialScreen = $this->resolveInitialScreen($screenRegistry);
        
        return new BuiltApplication(
            app: $app,
            moduleRegistry: $moduleRegistry,
            container: $container,
            screenRegistry: $screenRegistry,
            initialScreen: $initialScreen,
            onQuit: $this->onQuit,
        );
    }
    
    private function registerModuleServices(
        ModuleRegistry $registry,
        Container $container,
    ): void {
        $providers = $registry->getImplementing(ServiceProviderInterface::class);
        
        foreach ($providers as $provider) {
            foreach ($provider->services() as $definition) {
                if ($definition->singleton) {
                    $container->singleton($definition->id, $definition->factory);
                } else {
                    $container->bind($definition->id, $definition->factory);
                }
            }
        }
    }
    
    private function registerModuleScreens(
        ModuleRegistry $registry,
        ContainerInterface $container,
        ScreenRegistry $screenRegistry,
    ): void {
        $providers = $registry->getImplementing(ScreenProviderInterface::class);
        
        foreach ($providers as $provider) {
            foreach ($provider->screens($container) as $screen) {
                $screenRegistry->register($screen);
            }
        }
    }
    
    private function registerModuleKeyBindings(
        ModuleRegistry $registry,
        KeyBindingRegistry $keyBindings,
    ): void {
        $providers = $registry->getImplementing(KeyBindingProviderInterface::class);
        
        foreach ($providers as $provider) {
            foreach ($provider->keyBindings() as $binding) {
                $keyBindings->register($binding);
            }
        }
        
        // Always add quit binding
        $keyBindings->register(new KeyBinding(
            combination: KeyCombination::fromString('F12'),
            actionId: 'app.quit',
            description: 'Quit application',
            category: 'app',
        ));
    }
    
    /**
     * @return array<string, MenuDefinition>
     */
    private function buildMenuSystem(
        ModuleRegistry $registry,
        KeyBindingRegistry $keyBindings,
    ): array {
        $menus = [];
        
        // Collect from providers
        $providers = $registry->getImplementing(MenuProviderInterface::class);
        
        foreach ($providers as $provider) {
            foreach ($provider->menus() as $menu) {
                // Use label as key, later menus override earlier
                $key = strtolower(str_replace(' ', '_', $menu->label));
                $menus[$key] = $menu;
            }
        }
        
        // Add quit menu
        $quitBinding = $keyBindings->getPrimaryByActionId('app.quit');
        $menus['quit'] = new MenuDefinition(
            label: 'Quit',
            fkey: $quitBinding?->combination,
            items: [
                ActionMenuItem::create('Quit', static fn() => null, 'q'),
            ],
            priority: 999,
        );
        
        // Sort by priority
        uasort($menus, static fn($a, $b) => $a->priority <=> $b->priority);
        
        return $menus;
    }
    
    private function resolveInitialScreen(ScreenRegistry $registry): ?string
    {
        // Use explicit initial screen if set
        if ($this->initialScreen !== null) {
            if (!$registry->has($this->initialScreen)) {
                throw new \RuntimeException(
                    "Initial screen '{$this->initialScreen}' not found"
                );
            }
            return $this->initialScreen;
        }
        
        // Use first registered screen
        $names = $registry->getNames();
        return $names[0] ?? null;
    }

}

```

### BuiltApplication

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Builder;

use Butschster\Commander\Application;
use Butschster\Commander\SDK\Container\ContainerInterface;
use Butschster\Commander\SDK\Module\ModuleRegistry;
use Butschster\Commander\UI\Screen\ScreenRegistry;

/**
 * Configured application ready to run.
 * 
 * Wraps the core Application with module lifecycle management.
 */
final class BuiltApplication
{
    public function __construct(
        private readonly Application $app,
        private readonly ModuleRegistry $moduleRegistry,
        private readonly ContainerInterface $container,
        private readonly ScreenRegistry $screenRegistry,
        private readonly ?string $initialScreen,
        private readonly ?\Closure $onQuit,
    ) {
        // Wire quit callback
        if ($this->onQuit !== null) {
            $this->app->onAction('app.quit', $this->onQuit);
        } else {
            $this->app->onAction('app.quit', fn() => $this->app->stop());
        }
    }
    
    /**
     * Run the application.
     * 
     * Blocks until application exits.
     */
    public function run(): void
    {
        $initialScreen = $this->getInitialScreen();
        
        if ($initialScreen === null) {
            throw new \RuntimeException('No screens registered');
        }
        
        try {
            $this->app->run($initialScreen);
        } finally {
            $this->moduleRegistry->shutdown();
        }
    }
    
    /**
     * Get the DI container.
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
    
    /**
     * Get the module registry.
     */
    public function getModuleRegistry(): ModuleRegistry
    {
        return $this->moduleRegistry;
    }
    
    /**
     * Get the screen registry.
     */
    public function getScreenRegistry(): ScreenRegistry
    {
        return $this->screenRegistry;
    }
    
    /**
     * Get the inner Application (for testing).
     */
    public function getInnerApplication(): Application
    {
        return $this->app;
    }
    
    private function getInitialScreen(): ?\Butschster\Commander\UI\Screen\ScreenInterface
    {
        if ($this->initialScreen === null) {
            return null;
        }
        
        return $this->screenRegistry->getScreen($this->initialScreen);
    }
}
```

### ModuleTestCase

```php
<?php

declare(strict_types=1);

namespace Tests\Testing;

use Butschster\Commander\SDK\Builder\ApplicationBuilder;
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
     */
    protected function createContext(array $config = []): ModuleContext
    {
        return new ModuleContext(
            $this->container,
            $this->screenManager,
            $config,
        );
    }
    
    /**
     * Boot a module and return the container.
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
     */
    protected function createMockModule(
        string $name,
        array $dependencies = [],
        ?\Closure $onBoot = null,
        ?\Closure $onShutdown = null,
    ): ModuleInterface {
        return new class($name, $dependencies, $onBoot, $onShutdown) implements ModuleInterface {
            public function __construct(
                private readonly string $name,
                private readonly array $deps,
                private readonly ?\Closure $onBoot,
                private readonly ?\Closure $onShutdown,
            ) {}
            
            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata(
                    $this->name,
                    ucfirst($this->name),
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
        $initialScreen = $app->getScreenRegistry()->getScreen(
            $app->getScreenRegistry()->getNames()[0] ?? ''
        );
        
        if ($initialScreen !== null) {
            $screenManager->pushScreen($initialScreen);
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
        $this->assertTrue(
            $this->moduleRegistry->has($name),
            "Module '{$name}' should be registered"
        );
    }
    
    /**
     * Assert service exists in container.
     */
    protected function assertServiceExists(string $id): void
    {
        $this->assertTrue(
            $this->container->has($id),
            "Service '{$id}' should exist in container"
        );
    }
}
```

## Test Cases

```php
// tests/Unit/SDK/Builder/ApplicationBuilderTest.php

final class ApplicationBuilderTest extends TestCase
{
    public function test_creates_builder_instance(): void
    {
        $builder = ApplicationBuilder::create();
        
        $this->assertInstanceOf(ApplicationBuilder::class, $builder);
    }
    
    public function test_with_module_is_fluent(): void
    {
        $builder = ApplicationBuilder::create();
        $module = $this->createSimpleModule();
        
        $result = $builder->withModule($module);
        
        $this->assertSame($builder, $result);
    }
    
    public function test_builds_application_with_module(): void
    {
        $module = $this->createSimpleModule();
        
        $app = ApplicationBuilder::create()
            ->withModule($module)
            ->build();
        
        $this->assertInstanceOf(BuiltApplication::class, $app);
        $this->assertTrue($app->getModuleRegistry()->has('simple'));
    }
    
    public function test_registers_module_services(): void
    {
        $module = $this->createModuleWithService();
        
        $app = ApplicationBuilder::create()
            ->withModule($module)
            ->build();
        
        $this->assertTrue($app->getContainer()->has(TestService::class));
    }
    
    public function test_registers_module_screens(): void
    {
        $module = $this->createModuleWithScreen();
        
        $app = ApplicationBuilder::create()
            ->withModule($module)
            ->build();
        
        $this->assertTrue($app->getScreenRegistry()->has('test_screen'));
    }
    
    public function test_throws_on_missing_initial_screen(): void
    {
        $module = $this->createSimpleModule();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Initial screen 'nonexistent' not found");
        
        ApplicationBuilder::create()
            ->withModule($module)
            ->withInitialScreen('nonexistent')
            ->build();
    }
    
    public function test_passes_config_to_modules(): void
    {
        $receivedConfig = null;
        
        $module = new class($receivedConfig) implements ModuleInterface {
            public function __construct(private mixed &$config) {}
            
            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata('test', 'Test');
            }
            
            public function boot(ModuleContext $context): void
            {
                $this->config = $context->config('database.host');
            }
            
            public function shutdown(): void {}
        };
        
        ApplicationBuilder::create()
            ->withModule($module)
            ->withConfig(['database' => ['host' => 'localhost']])
            ->build();
        
        $this->assertSame('localhost', $receivedConfig);
    }
    
    public function test_boots_modules_in_dependency_order(): void
    {
        $bootOrder = [];
        
        $parent = $this->createMockModule('parent', [], function () use (&$bootOrder) {
            $bootOrder[] = 'parent';
        });
        
        $child = $this->createMockModule('child', ['parent'], function () use (&$bootOrder) {
            $bootOrder[] = 'child';
        });
        
        ApplicationBuilder::create()
            ->withModule($child)  // Wrong order
            ->withModule($parent)
            ->build();
        
        $this->assertSame(['parent', 'child'], $bootOrder);
    }
    
    // Helper methods...
}
```

```php
// tests/Integration/SDK/ApplicationBuilderIntegrationTest.php

final class ApplicationBuilderIntegrationTest extends ModuleTestCase
{
    public function test_full_application_with_multiple_modules(): void
    {
        $this->terminal()->setSize(120, 40);
        
        $module1 = $this->createScreenModule('mod1', 'Screen One');
        $module2 = $this->createScreenModule('mod2', 'Screen Two');
        
        $app = ApplicationBuilder::create()
            ->withModule($module1)
            ->withModule($module2)
            ->build();
        
        $this->runBuiltApp($app);
        
        // First module's screen should be shown
        $this->assertScreenContains('Screen One');
    }
    
    public function test_menus_from_modules_appear_in_menu_bar(): void
    {
        $this->terminal()->setSize(120, 40);
        
        $module = $this->createMenuModule('files', 'Files', 'F1');
        
        $app = ApplicationBuilder::create()
            ->withModule($module)
            ->build();
        
        $this->runBuiltApp($app);
        
        $this->assertScreenContains('F1 Files');
    }
    
    public function test_quit_menu_always_present(): void
    {
        $this->terminal()->setSize(120, 40);
        
        $module = $this->createSimpleModule();
        
        $app = ApplicationBuilder::create()
            ->withModule($module)
            ->build();
        
        $this->runBuiltApp($app);
        
        $this->assertScreenContains('F12 Quit');
    }
    
    // Helper methods for creating test modules...
}
```

## Definition of Done

- [ ] `ApplicationBuilder` implements:
    - [ ] `create()` static factory
    - [ ] `withModule()` for adding modules
    - [ ] `withTheme()` for theme configuration
    - [ ] `withFps()` for frame rate
    - [ ] `withConfig()` for configuration
    - [ ] `withInitialScreen()` for startup screen
    - [ ] `onQuit()` for quit callback
    - [ ] `build()` that wires everything
- [ ] `BuiltApplication` implements:
    - [ ] `run()` that starts application and handles shutdown
    - [ ] `getContainer()`, `getModuleRegistry()`, `getScreenRegistry()`
    - [ ] `getInnerApplication()` for testing
- [ ] Services registered before module boot
- [ ] Screens collected after module boot
- [ ] Menus sorted by priority
- [ ] Quit menu (F12) always added
- [ ] Theme applied via `ColorScheme::applyTheme()`
- [ ] Module shutdown called when app exits
- [ ] `ModuleTestCase` provides testing utilities
- [ ] All unit and integration tests pass
- [ ] Static analysis passes

## Dependencies

**Requires**:

- Stage 1 (ModuleInterface, ModuleMetadata, ModuleContext)
- Stage 2 (Container, ServiceDefinition)
- Stage 3 (ModuleRegistry)
- Stage 4 (All provider interfaces)

**Enables**:

- Stage 6 (Can now build apps with FileBrowserModule)
- Stage 7 (Can convert remaining modules)
