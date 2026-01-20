# Stage 3: Module Registry & Lifecycle

## Overview

Implement the module registry that manages module registration, dependency validation, and lifecycle (boot/shutdown).
The registry handles topological sorting to ensure modules boot in correct dependency order and shutdown in reverse
order.

**Why this stage**: Before we can build an application with modules, we need a way to manage them. The registry is the
central coordinator.

## Files

**CREATE:**

```
src/SDK/Module/
└── ModuleRegistry.php         - Central module management
```

**CREATE (Tests):**

```
tests/Unit/SDK/Module/
└── ModuleRegistryTest.php
```

## Code References

### Existing Patterns

- `src/UI/Screen/ScreenRegistry.php:15-95` - Registry pattern with register/get/has
- `src/UI/Screen/ScreenRegistry.php:30-48` - Registration with metadata extraction
- `src/Application.php:170-180` - Lifecycle pattern (setup, run, cleanup)

### Dependency Sorting Algorithm

Use Kahn's algorithm for topological sort:

1. Build adjacency list from dependencies
2. Track in-degree for each node
3. Start with nodes that have no dependencies
4. Process and reduce in-degrees
5. Detect cycles if nodes remain with non-zero in-degree

## Implementation Details

### ModuleRegistry

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Module;

use Butschster\Commander\SDK\Exception\ModuleConflictException;
use Butschster\Commander\SDK\Exception\ModuleDependencyException;
use Butschster\Commander\SDK\Exception\ModuleNotFoundException;

/**
 * Central registry for module management.
 * 
 * Handles registration, dependency validation, and lifecycle.
 */
final class ModuleRegistry
{
    /** @var array<string, ModuleInterface> */
    private array $modules = [];
    
    /** @var array<string, ModuleMetadata> */
    private array $metadata = [];
    
    /** @var array<string> Boot order (topologically sorted) */
    private array $bootOrder = [];
    
    /** @var bool Whether boot() has been called */
    private bool $booted = false;
    
    /**
     * Register a module.
     * 
     * @throws ModuleConflictException If module name already registered
     */
    public function register(ModuleInterface $module): void
    {
        if ($this->booted) {
            throw new \RuntimeException('Cannot register modules after boot');
        }
        
        $metadata = $module->metadata();
        $name = $metadata->name;
        
        if (isset($this->modules[$name])) {
            throw new ModuleConflictException($name);
        }
        
        $this->modules[$name] = $module;
        $this->metadata[$name] = $metadata;
    }
    
    /**
     * Boot all registered modules in dependency order.
     * 
     * @throws ModuleDependencyException If dependencies not met
     */
    public function boot(ModuleContext $context): void
    {
        if ($this->booted) {
            throw new \RuntimeException('Modules already booted');
        }
        
        // Validate and sort
        $this->validateDependencies();
        $this->bootOrder = $this->topologicalSort();
        
        // Boot in order
        foreach ($this->bootOrder as $name) {
            $this->modules[$name]->boot($context);
        }
        
        $this->booted = true;
    }
    
    /**
     * Shutdown all modules in reverse boot order.
     */
    public function shutdown(): void
    {
        if (!$this->booted) {
            return;
        }
        
        // Shutdown in reverse order
        $shutdownOrder = array_reverse($this->bootOrder);
        
        foreach ($shutdownOrder as $name) {
            try {
                $this->modules[$name]->shutdown();
            } catch (\Throwable $e) {
                // Log but continue shutting down other modules
                // In real implementation, use a logger
            }
        }
        
        $this->booted = false;
    }
    
    /**
     * Check if module is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->modules[$name]);
    }
    
    /**
     * Get module by name.
     * 
     * @throws ModuleNotFoundException
     */
    public function get(string $name): ModuleInterface
    {
        if (!isset($this->modules[$name])) {
            throw new ModuleNotFoundException($name);
        }
        
        return $this->modules[$name];
    }
    
    /**
     * Get all registered modules.
     * 
     * @return array<string, ModuleInterface>
     */
    public function all(): array
    {
        return $this->modules;
    }
    
    /**
     * Get all module metadata.
     * 
     * @return array<string, ModuleMetadata>
     */
    public function allMetadata(): array
    {
        return $this->metadata;
    }
    
    /**
     * Get modules in boot order.
     * 
     * @return array<ModuleInterface>
     */
    public function getBootOrder(): array
    {
        return array_map(
            fn(string $name) => $this->modules[$name],
            $this->bootOrder
        );
    }
    
    /**
     * Get modules implementing a specific interface.
     * 
     * @template T
     * @param class-string<T> $interface
     * @return array<T>
     */
    public function getImplementing(string $interface): array
    {
        $result = [];
        
        foreach ($this->bootOrder ?: array_keys($this->modules) as $name) {
            $module = $this->modules[$name];
            if ($module instanceof $interface) {
                $result[] = $module;
            }
        }
        
        return $result;
    }
    
    /**
     * Validate all module dependencies are met.
     * 
     * @throws ModuleDependencyException
     */
    private function validateDependencies(): void
    {
        foreach ($this->metadata as $name => $metadata) {
            foreach ($metadata->dependencies as $dependency) {
                if (!isset($this->modules[$dependency])) {
                    throw new ModuleDependencyException($name, $dependency);
                }
            }
        }
    }
    
    /**
     * Topological sort using Kahn's algorithm.
     * 
     * @return array<string> Sorted module names
     * @throws ModuleDependencyException On circular dependency
     */
    private function topologicalSort(): array
    {
        // Build in-degree map
        $inDegree = [];
        $dependents = []; // name => [modules that depend on it]
        
        foreach ($this->metadata as $name => $metadata) {
            $inDegree[$name] = count($metadata->dependencies);
            
            foreach ($metadata->dependencies as $dep) {
                $dependents[$dep][] = $name;
            }
        }
        
        // Start with modules that have no dependencies
        $queue = [];
        foreach ($inDegree as $name => $degree) {
            if ($degree === 0) {
                $queue[] = $name;
            }
        }
        
        $sorted = [];
        
        while (!empty($queue)) {
            $name = array_shift($queue);
            $sorted[] = $name;
            
            // Reduce in-degree of dependents
            foreach ($dependents[$name] ?? [] as $dependent) {
                $inDegree[$dependent]--;
                if ($inDegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }
        
        // Check for cycles
        if (count($sorted) !== count($this->modules)) {
            $remaining = array_diff(array_keys($this->modules), $sorted);
            throw new ModuleDependencyException(
                reset($remaining),
                'circular dependency detected'
            );
        }
        
        return $sorted;
    }
}
```

### Update ModuleNotFoundException

```php
// src/SDK/Exception/ModuleNotFoundException.php

final class ModuleNotFoundException extends ModuleException
{
    public function __construct(
        public readonly string $moduleName,
    ) {
        parent::__construct("Module not found: {$moduleName}");
    }
}
```

### Update ModuleConflictException

```php
// src/SDK/Exception/ModuleConflictException.php

final class ModuleConflictException extends ModuleException
{
    public function __construct(
        public readonly string $moduleName,
    ) {
        parent::__construct("Module '{$moduleName}' is already registered");
    }
}
```

## Test Cases

```php
// tests/Unit/SDK/Module/ModuleRegistryTest.php

final class ModuleRegistryTest extends TestCase
{
    public function test_registers_module(): void
    {
        $registry = new ModuleRegistry();
        $module = $this->createModule('test');
        
        $registry->register($module);
        
        $this->assertTrue($registry->has('test'));
        $this->assertSame($module, $registry->get('test'));
    }
    
    public function test_throws_on_duplicate_registration(): void
    {
        $registry = new ModuleRegistry();
        $registry->register($this->createModule('test'));
        
        $this->expectException(ModuleConflictException::class);
        $registry->register($this->createModule('test'));
    }
    
    public function test_throws_on_missing_dependency(): void
    {
        $registry = new ModuleRegistry();
        $registry->register($this->createModule('child', ['parent']));
        
        $this->expectException(ModuleDependencyException::class);
        $registry->boot($this->createContext());
    }
    
    public function test_boots_in_dependency_order(): void
    {
        $bootOrder = [];
        
        $parent = $this->createModule('parent', [], function () use (&$bootOrder) {
            $bootOrder[] = 'parent';
        });
        
        $child = $this->createModule('child', ['parent'], function () use (&$bootOrder) {
            $bootOrder[] = 'child';
        });
        
        $registry = new ModuleRegistry();
        $registry->register($child);  // Register in wrong order
        $registry->register($parent);
        
        $registry->boot($this->createContext());
        
        $this->assertSame(['parent', 'child'], $bootOrder);
    }
    
    public function test_shuts_down_in_reverse_order(): void
    {
        $shutdownOrder = [];
        
        $parent = $this->createModule('parent', [], null, function () use (&$shutdownOrder) {
            $shutdownOrder[] = 'parent';
        });
        
        $child = $this->createModule('child', ['parent'], null, function () use (&$shutdownOrder) {
            $shutdownOrder[] = 'child';
        });
        
        $registry = new ModuleRegistry();
        $registry->register($parent);
        $registry->register($child);
        $registry->boot($this->createContext());
        
        $registry->shutdown();
        
        $this->assertSame(['child', 'parent'], $shutdownOrder);
    }
    
    public function test_detects_circular_dependency(): void
    {
        $registry = new ModuleRegistry();
        $registry->register($this->createModule('a', ['b']));
        $registry->register($this->createModule('b', ['a']));
        
        $this->expectException(ModuleDependencyException::class);
        $this->expectExceptionMessageMatches('/circular/i');
        $registry->boot($this->createContext());
    }
    
    public function test_get_implementing_returns_filtered_modules(): void
    {
        $registry = new ModuleRegistry();
        $registry->register($this->createModule('plain'));
        $registry->register($this->createScreenModule('with_screens'));
        
        $screenProviders = $registry->getImplementing(ScreenProviderInterface::class);
        
        $this->assertCount(1, $screenProviders);
    }
    
    public function test_cannot_register_after_boot(): void
    {
        $registry = new ModuleRegistry();
        $registry->register($this->createModule('first'));
        $registry->boot($this->createContext());
        
        $this->expectException(\RuntimeException::class);
        $registry->register($this->createModule('second'));
    }
    
    public function test_cannot_boot_twice(): void
    {
        $registry = new ModuleRegistry();
        $registry->register($this->createModule('test'));
        $registry->boot($this->createContext());
        
        $this->expectException(\RuntimeException::class);
        $registry->boot($this->createContext());
    }
    
    // Helper methods
    private function createModule(
        string $name,
        array $deps = [],
        ?\Closure $onBoot = null,
        ?\Closure $onShutdown = null,
    ): ModuleInterface {
        return new class($name, $deps, $onBoot, $onShutdown) implements ModuleInterface {
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
    
    private function createContext(): ModuleContext
    {
        return new ModuleContext(new Container());
    }
}
```

## Definition of Done

- [ ] `ModuleRegistry` implements:
    - [ ] `register()` with duplicate detection
    - [ ] `boot()` with dependency validation and sorting
    - [ ] `shutdown()` in reverse order
    - [ ] `has()`, `get()`, `all()`, `allMetadata()`
    - [ ] `getImplementing()` for filtering by interface
    - [ ] `getBootOrder()` for ordered iteration
- [ ] Topological sort handles:
    - [ ] Simple linear dependencies (A → B → C)
    - [ ] Diamond dependencies (A → B, A → C, B → D, C → D)
    - [ ] Circular dependency detection
- [ ] Prevention of registration after boot
- [ ] Prevention of double boot
- [ ] Shutdown continues even if one module throws
- [ ] All unit tests pass
- [ ] Static analysis passes

## Dependencies

**Requires**:

- Stage 1 (ModuleInterface, ModuleMetadata, ModuleContext, exceptions)
- Stage 2 (Container for ModuleContext)

**Enables**:

- Stage 4 (Providers use registry's `getImplementing()`)
- Stage 5 (Builder uses registry for module management)
