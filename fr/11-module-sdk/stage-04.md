# Stage 4: Provider Interfaces

## Overview

Define the provider interfaces that modules implement to contribute screens, menus, key bindings, and services. These
interfaces follow the composition pattern — modules implement only the providers they need.

**Why this stage**: Before the builder can wire everything together, we need to know what modules can provide. These
interfaces define the "plugin API".

## Files

**CREATE:**

```
src/SDK/Provider/
├── ScreenProviderInterface.php      - Provides screens
├── MenuProviderInterface.php        - Provides menu definitions
├── KeyBindingProviderInterface.php  - Provides key bindings
└── ServiceProviderInterface.php     - Provides service definitions
```

**CREATE (Tests):**

```
tests/Unit/SDK/Provider/
└── ProviderInterfaceTest.php        - Contract tests
```

## Code References

### Existing Patterns

- `src/UI/Screen/ScreenInterface.php` - Screen contract that providers yield
- `src/UI/Menu/MenuDefinition.php` - Menu structure that providers yield
- `src/Infrastructure/Keyboard/KeyBinding.php` - Key binding that providers yield
- `src/UI/Screen/ScreenRegistry.php:30-48` - How screens are registered (our providers will feed this)

### How Providers Work

```php
// Module implements multiple providers
final class FileBrowserModule implements 
    ModuleInterface,
    ScreenProviderInterface,
    MenuProviderInterface,
    ServiceProviderInterface
{
    // ModuleInterface
    public function metadata(): ModuleMetadata { ... }
    public function boot(ModuleContext $context): void { ... }
    public function shutdown(): void { ... }
    
    // ScreenProviderInterface
    public function screens(ContainerInterface $container): iterable
    {
        yield $container->get(FileBrowserScreen::class);
        yield $container->get(FileViewerScreen::class);
    }
    
    // MenuProviderInterface
    public function menus(): iterable
    {
        yield new MenuDefinition(
            label: 'Files',
            fkey: KeyCombination::fromString('F1'),
            items: [...],
            priority: 10,
        );
    }
    
    // ServiceProviderInterface
    public function services(): iterable
    {
        yield ServiceDefinition::singleton(
            FileSystemService::class,
            fn() => new FileSystemService()
        );
    }
}
```

## Implementation Details

### ScreenProviderInterface

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Provider;

use Butschster\Commander\SDK\Container\ContainerInterface;
use Butschster\Commander\UI\Screen\ScreenInterface;

/**
 * Modules implement this to provide screens.
 * 
 * Screens are UI views that can be navigated to.
 * They appear in menus and can be opened programmatically.
 */
interface ScreenProviderInterface
{
    /**
     * Provide screens to register.
     * 
     * Called after services are registered, so container
     * can be used to resolve screen dependencies.
     * 
     * @param ContainerInterface $container For resolving dependencies
     * @return iterable<ScreenInterface>
     */
    public function screens(ContainerInterface $container): iterable;
}
```

### MenuProviderInterface

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Provider;

use Butschster\Commander\UI\Menu\MenuDefinition;

/**
 * Modules implement this to provide menu entries.
 * 
 * Menus appear in the top menu bar with F-key shortcuts.
 */
interface MenuProviderInterface
{
    /**
     * Provide menu definitions.
     * 
     * Each MenuDefinition becomes a top-level menu item
     * with its own dropdown containing menu items.
     * 
     * @return iterable<MenuDefinition>
     */
    public function menus(): iterable;
}
```

### KeyBindingProviderInterface

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Provider;

use Butschster\Commander\Infrastructure\Keyboard\KeyBinding;

/**
 * Modules implement this to provide global key bindings.
 * 
 * Key bindings are shortcuts that work application-wide,
 * not just within a specific screen.
 */
interface KeyBindingProviderInterface
{
    /**
     * Provide key bindings.
     * 
     * Bindings map key combinations to action IDs.
     * The application handles action execution.
     * 
     * @return iterable<KeyBinding>
     */
    public function keyBindings(): iterable;
}
```

### ServiceProviderInterface

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Provider;

use Butschster\Commander\SDK\Container\ServiceDefinition;

/**
 * Modules implement this to provide services.
 * 
 * Services are registered in the container and can be
 * injected into screens and other services.
 */
interface ServiceProviderInterface
{
    /**
     * Provide service definitions.
     * 
     * Called before boot() so services are available
     * during module initialization.
     * 
     * @return iterable<ServiceDefinition>
     */
    public function services(): iterable;
}
```

## Usage Examples

### Full Module Example

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\FileBrowser;

use Butschster\Commander\Infrastructure\Keyboard\KeyBinding;
use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;
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
use Butschster\Commander\UI\Menu\ScreenMenuItem;

final class FileBrowserModule implements 
    ModuleInterface,
    ServiceProviderInterface,
    ScreenProviderInterface,
    MenuProviderInterface,
    KeyBindingProviderInterface
{
    public function __construct(
        private readonly ?string $initialPath = null,
    ) {}
    
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata(
            name: 'file_browser',
            title: 'File Browser',
            version: '1.0.0',
        );
    }
    
    public function services(): iterable
    {
        yield ServiceDefinition::singleton(
            FileSystemService::class,
            fn() => new FileSystemService()
        );
    }
    
    public function screens(ContainerInterface $container): iterable
    {
        $fileSystem = $container->get(FileSystemService::class);
        $screenManager = $container->get(ScreenManager::class);
        
        yield new FileBrowserScreen(
            $fileSystem,
            $screenManager,
            $this->initialPath ?? getcwd(),
        );
        
        yield new FileViewerScreen(
            $fileSystem,
            $screenManager,
        );
    }
    
    public function menus(): iterable
    {
        yield new MenuDefinition(
            label: 'Files',
            fkey: KeyCombination::fromString('F1'),
            items: [
                ScreenMenuItem::create('File Browser', 'file_browser'),
                ScreenMenuItem::create('File Viewer', 'file_viewer'),
            ],
            priority: 10,
        );
    }
    
    public function keyBindings(): iterable
    {
        yield new KeyBinding(
            combination: KeyCombination::fromString('Ctrl+O'),
            actionId: 'file_browser.open',
            description: 'Open file browser',
            category: 'files',
        );
    }
    
    public function boot(ModuleContext $context): void
    {
        // Module-specific initialization
    }
    
    public function shutdown(): void
    {
        // Cleanup
    }
}
```

### Minimal Module (Screens Only)

```php
final class SimpleScreenModule implements ModuleInterface, ScreenProviderInterface
{
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata('simple', 'Simple Module');
    }
    
    public function screens(ContainerInterface $container): iterable
    {
        yield new SimpleScreen();
    }
    
    public function boot(ModuleContext $context): void {}
    public function shutdown(): void {}
}
```

### Service-Only Module

```php
final class DatabaseModule implements ModuleInterface, ServiceProviderInterface
{
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata('database', 'Database Services');
    }
    
    public function services(): iterable
    {
        yield ServiceDefinition::singleton(
            DatabaseConnection::class,
            fn(ContainerInterface $c) => new DatabaseConnection(
                $c->get(ConfigRepository::class)->get('database.dsn')
            )
        );
        
        yield ServiceDefinition::singleton(
            UserRepository::class,
            fn(ContainerInterface $c) => new UserRepository(
                $c->get(DatabaseConnection::class)
            )
        );
    }
    
    public function boot(ModuleContext $context): void
    {
        // Initialize connection pool
    }
    
    public function shutdown(): void
    {
        // Close connections
    }
}
```

## Test Cases

```php
// tests/Unit/SDK/Provider/ProviderInterfaceTest.php

final class ProviderInterfaceTest extends TestCase
{
    public function test_screen_provider_yields_screens(): void
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
                return new class($name) implements ScreenInterface {
                    public function __construct(private string $name) {}
                    public function render(...$args): void {}
                    public function handleInput(string $key): bool { return false; }
                    public function onActivate(): void {}
                    public function onDeactivate(): void {}
                    public function update(): void {}
                    public function getTitle(): string { return $this->name; }
                };
            }
        };
        
        $container = new Container();
        $screens = iterator_to_array($module->screens($container));
        
        $this->assertCount(2, $screens);
        $this->assertContainsOnlyInstancesOf(ScreenInterface::class, $screens);
    }
    
    public function test_menu_provider_yields_menu_definitions(): void
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
        
        $menus = iterator_to_array($module->menus());
        
        $this->assertCount(2, $menus);
        $this->assertSame('Menu 1', $menus[0]->label);
        $this->assertSame('Menu 2', $menus[1]->label);
    }
    
    public function test_service_provider_yields_definitions(): void
    {
        $module = new class implements ModuleInterface, ServiceProviderInterface {
            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata('test', 'Test');
            }
            
            public function services(): iterable
            {
                yield ServiceDefinition::singleton('service1', fn() => new \stdClass());
                yield ServiceDefinition::transient('service2', fn() => new \stdClass());
            }
            
            public function boot(ModuleContext $context): void {}
            public function shutdown(): void {}
        };
        
        $services = iterator_to_array($module->services());
        
        $this->assertCount(2, $services);
        $this->assertTrue($services[0]->singleton);
        $this->assertFalse($services[1]->singleton);
    }
    
    public function test_key_binding_provider_yields_bindings(): void
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
                    'test'
                );
            }
            
            public function boot(ModuleContext $context): void {}
            public function shutdown(): void {}
        };
        
        $bindings = iterator_to_array($module->keyBindings());
        
        $this->assertCount(1, $bindings);
        $this->assertSame('test.save', $bindings[0]->actionId);
    }
    
    public function test_module_can_implement_multiple_providers(): void
    {
        $module = new class implements 
            ModuleInterface, 
            ScreenProviderInterface, 
            MenuProviderInterface 
        {
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
            
            public function boot(ModuleContext $context): void {}
            public function shutdown(): void {}
        };
        
        $this->assertInstanceOf(ScreenProviderInterface::class, $module);
        $this->assertInstanceOf(MenuProviderInterface::class, $module);
        $this->assertNotInstanceOf(ServiceProviderInterface::class, $module);
    }
}
```

## Integration with Registry

The `ModuleRegistry::getImplementing()` method from Stage 3 is used to collect providers:

```php
// In ApplicationBuilder (Stage 5)
$screenProviders = $registry->getImplementing(ScreenProviderInterface::class);
foreach ($screenProviders as $provider) {
    foreach ($provider->screens($container) as $screen) {
        $screenRegistry->register($screen);
    }
}

$menuProviders = $registry->getImplementing(MenuProviderInterface::class);
$menus = [];
foreach ($menuProviders as $provider) {
    foreach ($provider->menus() as $menu) {
        $menus[$menu->label] = $menu;
    }
}
```

## Definition of Done

- [ ] `ScreenProviderInterface` with `screens(ContainerInterface): iterable`
- [ ] `MenuProviderInterface` with `menus(): iterable`
- [ ] `KeyBindingProviderInterface` with `keyBindings(): iterable`
- [ ] `ServiceProviderInterface` with `services(): iterable`
- [ ] All interfaces have proper PHPDoc with:
    - [ ] Description of purpose
    - [ ] When method is called in lifecycle
    - [ ] What return types are expected
- [ ] Unit tests verify:
    - [ ] Each provider can yield multiple items
    - [ ] Module can implement multiple providers
    - [ ] Module can implement none (just ModuleInterface)
- [ ] Static analysis passes

## Dependencies

**Requires**:

- Stage 1 (ModuleInterface, ModuleMetadata)
- Stage 2 (ContainerInterface, ServiceDefinition)
- Stage 3 (ModuleRegistry::getImplementing)

**Enables**:

- Stage 5 (Builder collects from providers)
- Stage 6 (FileBrowserModule implements providers)
