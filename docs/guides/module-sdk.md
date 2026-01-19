# Module SDK Developer Guide

This guide explains how to create modules (components) for the Commander terminal application using the Module SDK.
Modules are self-contained units that can provide screens, menus, services, and key bindings to extend the application.

## Table of Contents

1. [Overview](#overview)
2. [Quick Start](#quick-start)
3. [Module Architecture](#module-architecture)
4. [Core Interfaces](#core-interfaces)
5. [Provider Interfaces](#provider-interfaces)
6. [Dependency Injection](#dependency-injection)
7. [Creating Screens](#creating-screens)
8. [Creating Menus](#creating-menus)
9. [Key Bindings](#key-bindings)
10. [Module Dependencies](#module-dependencies)
11. [Configuration](#configuration)
12. [Testing Modules](#testing-modules)
13. [Best Practices](#best-practices)
14. [Complete Example](#complete-example)

---

## Overview

The Module SDK enables developers to create pluggable extensions for Commander terminal applications. Each module is a
self-contained unit that can:

- **Provide Screens**: UI views users can navigate to
- **Provide Menus**: Top menu bar entries with F-key shortcuts
- **Provide Services**: Shared services registered in the DI container
- **Provide Key Bindings**: Global keyboard shortcuts

### Key Benefits

- **Separation of Concerns**: Clear boundaries between features
- **Reusability**: Modules can be distributed via Composer
- **Testability**: Modules can be tested in isolation
- **Composition**: Only implement the interfaces you need

---

## Quick Start

Here's a minimal module that provides a single screen:

```php
<?php

declare(strict_types=1);

namespace MyApp\Module\HelloWorld;

use Butschster\Commander\SDK\Container\ContainerInterface;
use Butschster\Commander\SDK\Module\ModuleContext;
use Butschster\Commander\SDK\Module\ModuleInterface;
use Butschster\Commander\SDK\Module\ModuleMetadata;
use Butschster\Commander\SDK\Provider\ScreenProviderInterface;

final readonly class HelloWorldModule implements ModuleInterface, ScreenProviderInterface
{
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata(
            name: 'hello_world',
            title: 'Hello World',
            version: '1.0.0',
        );
    }

    public function screens(ContainerInterface $container): iterable
    {
        yield new HelloWorldScreen();
    }

    public function boot(ModuleContext $context): void
    {
        // Optional initialization
    }

    public function shutdown(): void
    {
        // Optional cleanup
    }
}
```

### Using the Module

```php
use Butschster\Commander\SDK\Builder\ApplicationBuilder;
use MyApp\Module\HelloWorld\HelloWorldModule;

$app = ApplicationBuilder::create()
    ->withModule(new HelloWorldModule())
    ->withInitialScreen('hello_world')
    ->build();

$app->run();
```

---

## Module Architecture

### Directory Structure

Organize your module with this recommended structure:

```
src/Module/YourModule/
├── YourModule.php              # Module entry point
├── Screen/
│   ├── MainScreen.php          # Screen implementations
│   └── DetailScreen.php
├── Component/
│   ├── ListComponent.php       # Reusable UI components
│   └── InfoPanel.php
└── Service/
    └── YourService.php         # Business logic services
```

### Module Lifecycle

```
1. Registration
   └── ApplicationBuilder::withModule() → ModuleRegistry::register()
                                          └── Validates metadata
                                          └── Checks for conflicts

2. Service Registration (before boot)
   └── ServiceProviderInterface::services() → Container bindings

3. Boot Phase
   └── ModuleRegistry::boot() → Topological sort by dependencies
                             → Module::boot() for each module (in order)

4. Screen/Menu Collection (after boot)
   └── ScreenProviderInterface::screens() → ScreenRegistry
   └── MenuProviderInterface::menus() → MenuSystem

5. Application Running
   └── BuiltApplication::run() → Main loop

6. Shutdown (reverse order)
   └── ModuleRegistry::shutdown() → Module::shutdown() for each
```

---

## Core Interfaces

### ModuleInterface

Every module must implement `ModuleInterface`:

```php
<?php

namespace Butschster\Commander\SDK\Module;

interface ModuleInterface
{
    /**
     * Get module metadata (name, version, dependencies).
     */
    public function metadata(): ModuleMetadata;

    /**
     * Called once when application boots.
     * Use for initialization, event listeners, setup.
     */
    public function boot(ModuleContext $context): void;

    /**
     * Called when application shuts down.
     * Use for cleanup, closing connections, saving state.
     */
    public function shutdown(): void;
}
```

### ModuleMetadata

Describes your module's identity and dependencies:

```php
<?php

namespace Butschster\Commander\SDK\Module;

final readonly class ModuleMetadata
{
    public function __construct(
        public string $name,           // Unique identifier: 'my_module'
        public string $title,          // Human-readable: 'My Module'
        public string $version = '1.0.0',
        public array $dependencies = [], // Other module names
    ) {}
}
```

### ModuleContext

Provides runtime access to application services during boot:

```php
<?php

namespace Butschster\Commander\SDK\Module;

final readonly class ModuleContext
{
    public function __construct(
        public ContainerInterface $container,  // DI container
        private array $config = [],            // App configuration
    ) {}

    /**
     * Get config value using dot notation.
     * Example: $context->config('database.host', 'localhost')
     */
    public function config(string $key, mixed $default = null): mixed;
}
```

---

## Provider Interfaces

Modules implement provider interfaces to contribute functionality. **Only implement what you need** — this is
composition over inheritance.

### ScreenProviderInterface

Provides screens (UI views) to the application:

```php
<?php

namespace Butschster\Commander\SDK\Provider;

interface ScreenProviderInterface
{
    /**
     * @return iterable<ScreenInterface>
     */
    public function screens(ContainerInterface $container): iterable;
}
```

**Example:**

```php
public function screens(ContainerInterface $container): iterable
{
    // Resolve dependencies from container
    $myService = $container->get(MyService::class);
    $screenManager = $container->get(ScreenManager::class);

    yield new MainScreen($myService, $screenManager);
    yield new DetailScreen($myService);
}
```

### MenuProviderInterface

Provides menu entries for the top menu bar:

```php
<?php

namespace Butschster\Commander\SDK\Provider;

interface MenuProviderInterface
{
    /**
     * @return iterable<MenuDefinition>
     */
    public function menus(): iterable;
}
```

**Example:**

```php
use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;
use Butschster\Commander\UI\Menu\MenuDefinition;
use Butschster\Commander\UI\Menu\ScreenMenuItem;
use Butschster\Commander\UI\Menu\ActionMenuItem;

public function menus(): iterable
{
    yield new MenuDefinition(
        label: 'Tools',
        fkey: KeyCombination::fromString('F4'),
        items: [
            ScreenMenuItem::create('My Tool', 'my_tool_screen', 't'),
            ActionMenuItem::create('Do Action', fn() => $this->doSomething(), 'a'),
        ],
        priority: 40,  // Lower = appears earlier in menu bar
    );
}
```

### ServiceProviderInterface

Registers services in the DI container:

```php
<?php

namespace Butschster\Commander\SDK\Provider;

interface ServiceProviderInterface
{
    /**
     * @return iterable<ServiceDefinition>
     */
    public function services(): iterable;
}
```

**Example:**

```php
use Butschster\Commander\SDK\Container\ServiceDefinition;

public function services(): iterable
{
    // Singleton - same instance reused
    yield ServiceDefinition::singleton(
        MyService::class,
        fn(ContainerInterface $c) => new MyService(
            $c->get(DependencyA::class),
        ),
    );

    // Transient - new instance each time
    yield ServiceDefinition::transient(
        RequestHandler::class,
        fn() => new RequestHandler(),
    );
}
```

### KeyBindingProviderInterface

Provides global keyboard shortcuts:

```php
<?php

namespace Butschster\Commander\SDK\Provider;

interface KeyBindingProviderInterface
{
    /**
     * @return iterable<KeyBinding>
     */
    public function keyBindings(): iterable;
}
```

**Example:**

```php
use Butschster\Commander\Infrastructure\Keyboard\KeyBinding;
use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;

public function keyBindings(): iterable
{
    yield new KeyBinding(
        combination: KeyCombination::fromString('Ctrl+T'),
        actionId: 'my_module.open_tool',
        description: 'Open my tool',
        category: 'tools',
    );
}
```

---

## Dependency Injection

### The Container

The SDK includes a lightweight DI container that supports:

- **Singleton bindings**: Same instance reused
- **Transient bindings**: New instance each call
- **Constructor autowiring**: Automatic dependency resolution
- **Circular dependency detection**: Throws on cycles

### Registering Services

In your `services()` method:

```php
public function services(): iterable
{
    // Simple singleton
    yield ServiceDefinition::singleton(
        MyService::class,
        fn() => new MyService(),
    );

    // Singleton with dependencies
    yield ServiceDefinition::singleton(
        MyRepository::class,
        fn(ContainerInterface $c) => new MyRepository(
            $c->get(DatabaseConnection::class),
        ),
    );

    // Factory using module constructor params
    yield ServiceDefinition::singleton(
        ConfiguredService::class,
        fn() => new ConfiguredService($this->apiKey),
    );
}
```

### Resolving Services

In your `screens()` method or during `boot()`:

```php
public function screens(ContainerInterface $container): iterable
{
    // Get registered service
    $service = $container->get(MyService::class);

    // Check if service exists
    if ($container->has(OptionalService::class)) {
        $optional = $container->get(OptionalService::class);
    }

    // Create instance with autowiring (not registered)
    $handler = $container->make(RequestHandler::class, [
        'timeout' => 30,  // Override specific params
    ]);

    yield new MyScreen($service);
}
```

### Core Services Available

These services are automatically available in the container:

| Service                     | Description                     |
|-----------------------------|---------------------------------|
| `ScreenManager::class`      | Manages screen stack (push/pop) |
| `ContainerInterface::class` | The container itself            |

---

## Creating Screens

Screens are the main UI views in your module. They must implement `ScreenInterface` and use the `#[Metadata]` attribute.

### Screen Interface

```php
<?php

namespace Butschster\Commander\UI\Screen;

interface ScreenInterface
{
    public function render(Renderer $renderer, int $x, int $y, ?int $width, ?int $height): void;
    public function handleInput(string $key): bool;
    public function onActivate(): void;
    public function onDeactivate(): void;
    public function update(): void;
    public function getTitle(): string;
}
```

### Screen Metadata

Use the `#[Metadata]` attribute to define screen properties:

```php
use Butschster\Commander\UI\Screen\Attribute\Metadata;

#[Metadata(
    name: 'my_screen',           // Unique identifier
    title: 'My Screen',          // Display title
    description: 'Description',  // Optional
    category: 'tools',           // For menu grouping
    priority: 10,                // Lower = higher in menu
)]
final class MyScreen implements ScreenInterface
{
    // ...
}
```

### Complete Screen Example

```php
<?php

declare(strict_types=1);

namespace MyApp\Module\Todo\Screen;

use Butschster\Commander\UI\Render\Renderer;
use Butschster\Commander\UI\Screen\Attribute\Metadata;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\UI\Screen\ScreenManager;
use MyApp\Module\Todo\Service\TodoService;

#[Metadata(
    name: 'todo_list',
    title: 'Todo List',
    description: 'Manage your todos',
    category: 'productivity',
    priority: 10,
)]
final class TodoListScreen implements ScreenInterface
{
    private int $selectedIndex = 0;
    private array $todos = [];

    public function __construct(
        private readonly TodoService $todoService,
        private readonly ScreenManager $screenManager,
    ) {}

    public function render(Renderer $r, int $x, int $y, ?int $w, ?int $h): void
    {
        $r->writeAt($x + 2, $y + 1, 'My Todos', 'white', 'blue');

        foreach ($this->todos as $i => $todo) {
            $style = $i === $this->selectedIndex ? 'black|yellow' : 'white';
            $checkbox = $todo['done'] ? '[x]' : '[ ]';
            $r->writeAt($x + 2, $y + 3 + $i, "{$checkbox} {$todo['title']}", $style);
        }

        $r->writeAt($x + 2, $y + ($h ?? 20) - 2, 'Enter: Toggle | n: New | d: Delete | Esc: Back');
    }

    public function handleInput(string $key): bool
    {
        return match ($key) {
            'up', 'k' => $this->moveSelection(-1),
            'down', 'j' => $this->moveSelection(1),
            'enter', ' ' => $this->toggleSelected(),
            'n' => $this->createNew(),
            'd' => $this->deleteSelected(),
            'escape' => $this->goBack(),
            default => false,
        };
    }

    public function onActivate(): void
    {
        $this->todos = $this->todoService->getAll();
    }

    public function onDeactivate(): void
    {
        // Save state if needed
    }

    public function update(): void
    {
        // Called each frame - refresh data if needed
    }

    public function getTitle(): string
    {
        return 'Todo List';
    }

    private function moveSelection(int $delta): bool
    {
        $this->selectedIndex = max(0, min(
            count($this->todos) - 1,
            $this->selectedIndex + $delta
        ));
        return true;
    }

    private function toggleSelected(): bool
    {
        if (isset($this->todos[$this->selectedIndex])) {
            $this->todoService->toggle($this->todos[$this->selectedIndex]['id']);
            $this->todos = $this->todoService->getAll();
        }
        return true;
    }

    private function goBack(): bool
    {
        $this->screenManager->popScreen();
        return true;
    }

    // ... other methods
}
```

### Navigating Between Screens

Use `ScreenManager` to navigate:

```php
// Push a new screen onto the stack
$this->screenManager->pushScreen($newScreen);

// Pop current screen (go back)
$this->screenManager->popScreen();

// Replace current screen
$this->screenManager->replaceScreen($newScreen);
```

---

## Creating Menus

Menus appear in the top menu bar and are activated with F-keys.

### MenuDefinition

```php
use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;
use Butschster\Commander\UI\Menu\MenuDefinition;
use Butschster\Commander\UI\Menu\ScreenMenuItem;
use Butschster\Commander\UI\Menu\ActionMenuItem;
use Butschster\Commander\UI\Menu\SeparatorMenuItem;

public function menus(): iterable
{
    yield new MenuDefinition(
        label: 'Tools',                              // Menu bar label
        fkey: KeyCombination::fromString('F4'),      // Activation key
        items: [
            // Navigate to a screen
            ScreenMenuItem::create(
                label: 'Todo List',
                screenName: 'todo_list',  // Must match #[Metadata(name: '...')]
                hotkey: 't',              // Underlined letter
            ),

            // Separator line
            new SeparatorMenuItem(),

            // Execute an action
            ActionMenuItem::create(
                label: 'Refresh All',
                action: fn() => $this->refreshAll(),
                hotkey: 'r',
            ),
        ],
        priority: 40,  // Position in menu bar (lower = left)
    );
}
```

### Menu Priority Guidelines

| Priority | Typical Use                               |
|----------|-------------------------------------------|
| 10       | Primary features (Files)                  |
| 20       | Secondary features (Commands)             |
| 30       | Tertiary features (Tools)                 |
| 40+      | Additional modules                        |
| 999      | System menus (Quit - added automatically) |

---

## Key Bindings

Global keyboard shortcuts that work anywhere in the application.

### Creating Key Bindings

```php
use Butschster\Commander\Infrastructure\Keyboard\KeyBinding;
use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;

public function keyBindings(): iterable
{
    yield new KeyBinding(
        combination: KeyCombination::fromString('Ctrl+T'),
        actionId: 'todo.open',
        description: 'Open todo list',
        category: 'productivity',
    );

    yield new KeyBinding(
        combination: KeyCombination::fromString('Alt+N'),
        actionId: 'todo.new',
        description: 'Create new todo',
        category: 'productivity',
    );
}
```

### Supported Key Combinations

```php
// Function keys
KeyCombination::fromString('F1');
KeyCombination::fromString('F12');

// Modifier + key
KeyCombination::fromString('Ctrl+S');
KeyCombination::fromString('Alt+X');
KeyCombination::fromString('Shift+Tab');
KeyCombination::fromString('Ctrl+Shift+P');
```

### Handling Actions

Register action handlers in `ApplicationBuilder`:

```php
$app = ApplicationBuilder::create()
    ->withModule(new TodoModule())
    ->build();

// Actions are typically handled by screens or the application
```

---

## Module Dependencies

Modules can depend on other modules using the `dependencies` array in metadata.

### Declaring Dependencies

```php
public function metadata(): ModuleMetadata
{
    return new ModuleMetadata(
        name: 'todo_sync',
        title: 'Todo Cloud Sync',
        version: '1.0.0',
        dependencies: ['todo'],  // Requires 'todo' module
    );
}
```

### Dependency Resolution

- Dependencies are validated before boot
- Modules boot in topological order (dependencies first)
- Modules shutdown in reverse order
- Circular dependencies throw `ModuleDependencyException`

### Accessing Dependent Module's Services

```php
public function screens(ContainerInterface $container): iterable
{
    // Service from dependent module is available
    $todoService = $container->get(TodoService::class);

    yield new SyncScreen($todoService, $this->cloudClient);
}
```

---

## Configuration

Pass configuration to modules via `ApplicationBuilder::withConfig()`.

### Passing Configuration

```php
$app = ApplicationBuilder::create()
    ->withModule(new DatabaseModule())
    ->withConfig([
        'database' => [
            'host' => 'localhost',
            'port' => 5432,
            'name' => 'myapp',
        ],
        'api' => [
            'key' => getenv('API_KEY'),
        ],
    ])
    ->build();
```

### Reading Configuration in Modules

```php
public function boot(ModuleContext $context): void
{
    // Dot notation access
    $host = $context->config('database.host', 'localhost');
    $port = $context->config('database.port', 5432);
    $apiKey = $context->config('api.key');

    // Use configuration
    $this->connection = new DatabaseConnection($host, $port);
}
```

### Configuration in Services

```php
public function services(): iterable
{
    yield ServiceDefinition::singleton(
        DatabaseConnection::class,
        function (ContainerInterface $c) {
            // Access config via module property set in boot()
            return new DatabaseConnection(
                $this->dbHost,
                $this->dbPort,
            );
        },
    );
}

public function boot(ModuleContext $context): void
{
    $this->dbHost = $context->config('database.host', 'localhost');
    $this->dbPort = $context->config('database.port', 5432);
}
```

---

## Testing Modules

### Unit Testing

Test module components in isolation:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Todo;

use MyApp\Module\Todo\TodoModule;
use MyApp\Module\Todo\Service\TodoService;
use PHPUnit\Framework\TestCase;

final class TodoModuleTest extends TestCase
{
    public function test_metadata_is_correct(): void
    {
        $module = new TodoModule();
        $metadata = $module->metadata();

        $this->assertSame('todo', $metadata->name);
        $this->assertSame('Todo Manager', $metadata->title);
        $this->assertSame('1.0.0', $metadata->version);
        $this->assertEmpty($metadata->dependencies);
    }

    public function test_provides_todo_service(): void
    {
        $module = new TodoModule();
        $services = iterator_to_array($module->services());

        $this->assertCount(1, $services);
        $this->assertSame(TodoService::class, $services[0]->id);
        $this->assertTrue($services[0]->singleton);
    }

    public function test_provides_screens(): void
    {
        $module = new TodoModule();
        $container = $this->createContainer($module);

        $screens = iterator_to_array($module->screens($container));

        $this->assertCount(1, $screens);
        $this->assertInstanceOf(TodoListScreen::class, $screens[0]);
    }
}
```

### Integration Testing with ModuleTestCase

Use the `ModuleTestCase` base class for integration tests:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Module\Todo;

use MyApp\Module\Todo\TodoModule;
use MyApp\Module\Todo\Service\TodoService;
use Tests\Testing\ModuleTestCase;

final class TodoModuleIntegrationTest extends ModuleTestCase
{
    public function test_service_is_singleton(): void
    {
        $module = new TodoModule();
        $container = $this->bootModule($module);

        $first = $container->get(TodoService::class);
        $second = $container->get(TodoService::class);

        $this->assertSame($first, $second);
    }

    public function test_config_is_passed_to_module(): void
    {
        $module = new TodoModule();
        $this->bootModule($module, [
            'todo' => ['storage' => '/tmp/todos'],
        ]);

        // Assert module received config
        $this->assertSame('/tmp/todos', $module->getStoragePath());
    }
}
```

### E2E Testing

Test the complete application with virtual terminal:

```php
<?php

declare(strict_types=1);

namespace Tests\E2E\Module\Todo;

use Butschster\Commander\SDK\Builder\ApplicationBuilder;
use MyApp\Module\Todo\TodoModule;
use Tests\Testing\ModuleTestCase;

final class TodoModuleE2ETest extends ModuleTestCase
{
    public function test_todo_screen_renders(): void
    {
        $this->terminal()->setSize(120, 40);

        $app = ApplicationBuilder::create()
            ->withModule(new TodoModule())
            ->withInitialScreen('todo_list')
            ->build();

        $this->runBuiltApp($app);

        $this->assertScreenContains('My Todos');
    }

    public function test_navigation_works(): void
    {
        $this->terminal()->setSize(120, 40);

        $this->keys()
            ->down()
            ->down()
            ->enter()
            ->frame()
            ->applyTo($this->terminal());

        $app = ApplicationBuilder::create()
            ->withModule(new TodoModule())
            ->withInitialScreen('todo_list')
            ->build();

        $this->runBuiltApp($app);

        // Assert navigation occurred
        $this->assertScreenContains('expected content');
    }
}
```

---

## Best Practices

### 1. Keep Modules Focused

Each module should have a single responsibility:

```php
// ✅ Good - focused module
final class TodoModule { /* todo management */ }
final class CalendarModule { /* calendar features */ }

// ❌ Bad - too broad
final class ProductivityModule { /* todos, calendar, notes, ... */ }
```

### 2. Use Constructor Parameters for Configuration

```php
// ✅ Good - configurable via constructor
final readonly class TodoModule implements ModuleInterface
{
    public function __construct(
        private string $storagePath = '/tmp/todos',
        private int $maxItems = 100,
    ) {}
}

// Usage
new TodoModule(storagePath: '/var/data/todos', maxItems: 500)
```

### 3. Prefer Composition Over Inheritance

Only implement the provider interfaces you need:

```php
// ✅ Good - only what's needed
final class UtilityModule implements ModuleInterface, ServiceProviderInterface
{
    // No screens, no menus - just services
}

// ✅ Good - full-featured module
final class FileBrowserModule implements
    ModuleInterface,
    ServiceProviderInterface,
    ScreenProviderInterface,
    MenuProviderInterface,
    KeyBindingProviderInterface
{
    // Implements all providers
}
```

### 4. Use Meaningful Names

```php
// ✅ Good names
ModuleMetadata(name: 'file_browser', title: 'File Browser')
#[Metadata(name: 'file_browser', title: 'File Browser')]

// ❌ Bad names
ModuleMetadata(name: 'fb', title: 'FB')
#[Metadata(name: 'screen1', title: 'Screen')]
```

### 5. Handle Shutdown Gracefully

```php
public function shutdown(): void
{
    // Close connections
    $this->database?->close();

    // Save state
    $this->saveState();

    // Release resources
    $this->cache?->flush();
}
```

### 6. Document Your Module

```php
/**
 * File Browser Module
 *
 * Provides file system browsing and viewing capabilities.
 *
 * Screens:
 * - file_browser: Main dual-panel file browser
 * - file_viewer: Full-screen file content viewer
 *
 * Services:
 * - FileSystemService: File system operations
 *
 * Key Bindings:
 * - Ctrl+O: Open file browser
 */
final readonly class FileBrowserModule implements /* ... */
```

---

## Complete Example

Here's a complete module implementation showing all features:

```php
<?php

declare(strict_types=1);

namespace MyApp\Module\Notes;

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
use Butschster\Commander\UI\Menu\ActionMenuItem;
use Butschster\Commander\UI\Menu\SeparatorMenuItem;
use Butschster\Commander\UI\Screen\ScreenManager;
use MyApp\Module\Notes\Screen\NotesListScreen;
use MyApp\Module\Notes\Screen\NoteEditorScreen;
use MyApp\Module\Notes\Service\NotesService;
use MyApp\Module\Notes\Service\NotesStorage;

/**
 * Notes Module
 *
 * A simple note-taking module for Commander.
 *
 * Screens:
 * - notes_list: Browse and manage notes
 * - note_editor: Edit a single note
 *
 * Services:
 * - NotesService: Note CRUD operations
 * - NotesStorage: Persistence layer
 *
 * Key Bindings:
 * - Ctrl+N: Create new note
 */
final class NotesModule implements
    ModuleInterface,
    ServiceProviderInterface,
    ScreenProviderInterface,
    MenuProviderInterface,
    KeyBindingProviderInterface
{
    private string $storagePath;

    /**
     * @param string|null $storagePath Path to store notes (default: ~/.notes)
     */
    public function __construct(
        ?string $storagePath = null,
    ) {
        $this->storagePath = $storagePath ?? $_SERVER['HOME'] . '/.notes';
    }

    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata(
            name: 'notes',
            title: 'Notes',
            version: '1.0.0',
            dependencies: [],  // No dependencies
        );
    }

    public function services(): iterable
    {
        // Storage layer
        yield ServiceDefinition::singleton(
            NotesStorage::class,
            fn() => new NotesStorage($this->storagePath),
        );

        // Business logic
        yield ServiceDefinition::singleton(
            NotesService::class,
            fn(ContainerInterface $c) => new NotesService(
                $c->get(NotesStorage::class),
            ),
        );
    }

    public function screens(ContainerInterface $container): iterable
    {
        $notesService = $container->get(NotesService::class);
        $screenManager = $container->get(ScreenManager::class);

        // Main notes list
        yield new NotesListScreen(
            notesService: $notesService,
            screenManager: $screenManager,
        );

        // Note editor (opened from list)
        yield new NoteEditorScreen(
            notesService: $notesService,
            screenManager: $screenManager,
        );
    }

    public function menus(): iterable
    {
        yield new MenuDefinition(
            label: 'Notes',
            fkey: KeyCombination::fromString('F5'),
            items: [
                ScreenMenuItem::create('All Notes', 'notes_list', 'n'),
                new SeparatorMenuItem(),
                ActionMenuItem::create('New Note', fn() => null, 'c'),
            ],
            priority: 50,
        );
    }

    public function keyBindings(): iterable
    {
        yield new KeyBinding(
            combination: KeyCombination::fromString('Ctrl+N'),
            actionId: 'notes.new',
            description: 'Create new note',
            category: 'notes',
        );

        yield new KeyBinding(
            combination: KeyCombination::fromString('Ctrl+Shift+N'),
            actionId: 'notes.open',
            description: 'Open notes list',
            category: 'notes',
        );
    }

    public function boot(ModuleContext $context): void
    {
        // Ensure storage directory exists
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }

        // Read additional config if provided
        $customPath = $context->config('notes.storage_path');
        if ($customPath !== null) {
            $this->storagePath = $customPath;
        }
    }

    public function shutdown(): void
    {
        // Module cleanup - nothing needed for this simple module
    }
}
```

### Using the Module

```php
<?php

use Butschster\Commander\SDK\Builder\ApplicationBuilder;
use Butschster\Commander\Module\FileBrowser\FileBrowserModule;
use MyApp\Module\Notes\NotesModule;

$app = ApplicationBuilder::create()
    // Built-in modules
    ->withModule(new FileBrowserModule())

    // Custom module
    ->withModule(new NotesModule('/var/data/notes'))

    // Configuration
    ->withConfig([
        'notes' => [
            'max_notes' => 1000,
        ],
    ])

    // Start with file browser
    ->withInitialScreen('file_browser')

    ->build();

$app->run();
```

---

## Summary

The Module SDK provides a clean, extensible architecture for building Commander terminal applications. Key concepts:

1. **Modules** are self-contained units implementing `ModuleInterface`
2. **Providers** let modules contribute screens, menus, services, and key bindings
3. **Container** manages dependency injection with singleton/transient support
4. **ApplicationBuilder** wires everything together with a fluent API
5. **Lifecycle** ensures proper boot order (dependencies first) and cleanup

For more examples, see the built-in modules in `src/Module/`:

- `FileBrowserModule` - File system browsing
- `ComposerModule` - Composer package management
- `CommandBrowserModule` - Symfony command execution
