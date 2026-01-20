# Stage 1: SDK Core Foundation

## Overview

Establish the foundational contracts and data structures for the module system. This stage creates the core interfaces
that all modules will implement, the metadata DTOs, and exception classes. After this stage, we have a clear contract
for what a "module" is, but no runtime behavior yet.

**Why first**: Everything else depends on `ModuleInterface` and `ModuleMetadata`. These are the building blocks.

## Files

**CREATE:**

```
src/SDK/
├── Module/
│   ├── ModuleInterface.php          - Core module contract
│   ├── ModuleMetadata.php           - Immutable metadata DTO
│   └── ModuleContext.php            - Runtime context for modules
└── Exception/
    ├── ModuleException.php          - Base exception
    ├── ModuleNotFoundException.php  - Module not found
    ├── ModuleDependencyException.php - Missing dependency
    ├── ModuleConflictException.php  - Duplicate module name
    └── CircularDependencyException.php - Circular deps
```

**CREATE (Tests):**

```
tests/Unit/SDK/
├── Module/
│   ├── ModuleMetadataTest.php
│   └── ModuleContextTest.php
└── Exception/
    └── ModuleExceptionTest.php
```

## Code References

### Existing Patterns to Follow

- `src/UI/Screen/ScreenInterface.php:12-50` - Interface design pattern (simple, focused methods)
- `src/UI/Screen/ScreenMetadata.php:1-40` - Metadata DTO pattern (readonly class with public properties)
- `src/UI/Screen/Attribute/Metadata.php:17-27` - Attribute usage pattern

### Integration Points

- `src/UI/Screen/ScreenManager.php:17-24` - How context is passed (similar pattern for ModuleContext)

## Implementation Details

### ModuleInterface

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Module;

/**
 * Core contract for all modules.
 * 
 * Modules are self-contained units that provide screens, menus, 
 * services, and key bindings to the application.
 */
interface ModuleInterface
{
    /**
     * Get module metadata (name, version, dependencies).
     * 
     * Called during registration to identify the module.
     */
    public function metadata(): ModuleMetadata;
    
    /**
     * Called once when application boots.
     * 
     * Use this to:
     * - Initialize module-specific resources
     * - Register event listeners
     * - Perform one-time setup
     * 
     * @param ModuleContext $context Access to container, config, services
     */
    public function boot(ModuleContext $context): void;
    
    /**
     * Called when application shuts down.
     * 
     * Use this to:
     * - Close connections
     * - Cleanup temporary resources
     * - Save state if needed
     */
    public function shutdown(): void;
}
```

### ModuleMetadata

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Module;

/**
 * Immutable module metadata.
 * 
 * Contains identification and dependency information.
 */
final readonly class ModuleMetadata
{
    /**
     * @param string $name Unique identifier (e.g., 'file_browser')
     * @param string $title Human-readable title
     * @param string $version Semantic version (e.g., '1.0.0')
     * @param array<string> $dependencies Module names this depends on
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $version = '1.0.0',
        public array $dependencies = [],
    ) {}
}
```

### ModuleContext (Basic)

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Module;

/**
 * Runtime context passed to modules during boot.
 * 
 * Provides access to application services and configuration.
 * Will be extended in later stages to include Container.
 */
final readonly class ModuleContext
{
    /**
     * @param array<string, mixed> $config Application configuration
     */
    public function __construct(
        private array $config = [],
    ) {}
    
    /**
     * Get configuration value using dot notation.
     * 
     * @param string $key Dot-notation key (e.g., 'database.host')
     * @param mixed $default Default if not found
     */
    public function config(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        
        return $value;
    }
}
```

### Exception Classes

Follow existing exception patterns in PHP ecosystem:

- Extend from appropriate base (`\RuntimeException`, `\InvalidArgumentException`)
- Include relevant context in constructor
- Keep message generation in constructor

```php
// Base exception
class ModuleException extends \RuntimeException {}

// Specific exceptions with context
final class ModuleDependencyException extends ModuleException
{
    public function __construct(
        public readonly string $moduleName,
        public readonly string $missingDependency,
    ) {
        parent::__construct(
            "Module '{$moduleName}' requires '{$missingDependency}' which is not registered"
        );
    }
}
```

## Definition of Done

- [ ] `ModuleInterface` exists with `metadata()`, `boot()`, `shutdown()` methods
- [ ] `ModuleMetadata` is a readonly class with name, title, version, dependencies
- [ ] `ModuleContext` provides `config()` method with dot-notation support
- [ ] All 5 exception classes created with appropriate context
- [ ] Unit tests cover:
    - ModuleMetadata instantiation and property access
    - ModuleContext config retrieval with dot notation
    - ModuleContext config default values
    - Exception message generation
- [ ] All tests pass: `vendor/bin/phpunit --filter=SDK`
- [ ] Static analysis passes: `vendor/bin/phpstan analyse src/SDK`

## Dependencies

**Requires**: None (foundation stage)

**Enables**:

- Stage 2 (Container needs ModuleContext)
- Stage 3 (Registry needs ModuleInterface, ModuleMetadata)
- Stage 4 (Providers extend ModuleInterface)
