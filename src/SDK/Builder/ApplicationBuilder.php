<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Builder;

use Butschster\Commander\Application;
use Butschster\Commander\Infrastructure\Keyboard\KeyBinding;
use Butschster\Commander\Infrastructure\Keyboard\KeyBindingRegistry;
use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;
use Butschster\Commander\Infrastructure\Terminal\Driver\TerminalDriverInterface;
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
    private ?\Closure $onQuit = null;
    private ?TerminalDriverInterface $driver = null;

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
        $this->fps = \max(1, \min(60, $fps));
        return $this;
    }

    /**
     * Provide configuration values.
     *
     * @param array<string, mixed> $config
     */
    public function withConfig(array $config): self
    {
        $this->config = \array_merge($this->config, $config);
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
     * Set terminal driver (useful for testing).
     */
    public function withDriver(TerminalDriverInterface $driver): self
    {
        $this->driver = $driver;
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
        $context = new ModuleContext($container, $this->config);
        $moduleRegistry->boot($context);

        // 7. Apply theme
        if ($this->theme !== null) {
            ColorScheme::applyTheme($this->theme);
        }

        // 8. Create key binding registry
        $keyBindings = new KeyBindingRegistry();
        $this->registerModuleKeyBindings($moduleRegistry, $keyBindings);

        // 9. Create application
        $app = new Application($keyBindings, $this->driver);
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
                $key = \strtolower(\str_replace(' ', '_', $menu->label));
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
        \uasort($menus, static fn($a, $b) => $a->priority <=> $b->priority);

        return $menus;
    }

    private function resolveInitialScreen(ScreenRegistry $registry): ?string
    {
        // Use explicit initial screen if set
        if ($this->initialScreen !== null) {
            if (!$registry->has($this->initialScreen)) {
                throw new \RuntimeException(
                    "Initial screen '{$this->initialScreen}' not found",
                );
            }
            return $this->initialScreen;
        }

        // Use first registered screen
        $names = $registry->getNames();
        return $names[0] ?? null;
    }
}
