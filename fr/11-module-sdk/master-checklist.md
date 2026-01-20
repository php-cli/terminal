# Feature: Module SDK System

## Overview

Create a comprehensive SDK for building external modules that can be plugged into the Commander terminal application. The SDK provides contracts, builders, and utilities for third-party developers to create modules with their own screens, menus, key bindings, and services.

**Goal**: Enable this usage pattern:
```php
$app = ApplicationBuilder::create()
    ->withModule(new FileBrowserModule())
    ->withModule(new MyCustomModule())
    ->withTheme(new MidnightTheme())
    ->build();

$app->run();
```

## Stage Dependencies

```
Stage 1 (Core) ──────────────────────────────────┐
     │                                            │
     ▼                                            │
Stage 2 (Container) ─────────────────────────────┤
     │                                            │
     ▼                                            │
Stage 3 (Registry & Lifecycle) ──────────────────┤
     │                                            │
     ▼                                            │
Stage 4 (Provider Interfaces) ───────────────────┤
     │                                            │
     ▼                                            │
Stage 5 (Application Builder) ───────────────────┤
     │                                            │
     ▼                                            │
Stage 6 (FileBrowser Module) ────────────────────┤
     │                                            │
     ▼                                            │
Stage 7 (Remaining Modules & Cleanup) ───────────┘
```

## Development Progress

### Stage 1: SDK Core Foundation
- [x] 1.1: Create SDK directory structure (`src/SDK/`)
- [x] 1.2: Implement `ModuleInterface` contract
- [x] 1.3: Implement `ModuleMetadata` DTO
- [x] 1.4: Implement `ModuleContext` (basic version)
- [x] 1.5: Implement all exception classes
- [x] 1.6: Write unit tests for DTOs and exceptions

**Notes**: All core classes implemented with comprehensive unit tests.
**Status**: ✅ Complete
**Completed**: 2026-01-18

---

### Stage 2: Simple DI Container
- [x] 2.1: Implement `ContainerInterface`
- [x] 2.2: Implement `Container` with singleton/transient support
- [x] 2.3: Implement `ServiceDefinition` DTO
- [x] 2.4: Add constructor autowiring to Container
- [x] 2.5: Add circular dependency detection
- [x] 2.6: Write comprehensive unit tests

**Notes**: Container supports singleton and transient services, autowiring, and circular dependency detection.
**Status**: ✅ Complete
**Completed**: 2026-01-18

---

### Stage 3: Module Registry & Lifecycle
- [x] 3.1: Implement `ModuleRegistry` basic registration
- [x] 3.2: Add dependency validation
- [x] 3.3: Add topological sort for boot order
- [x] 3.4: Implement `boot()` and `shutdown()` lifecycle
- [x] 3.5: Write unit tests for registry and lifecycle

**Notes**: Registry handles module registration, dependency sorting, and lifecycle management.
**Status**: ✅ Complete
**Completed**: 2026-01-18

---

### Stage 4: Provider Interfaces
- [x] 4.1: Implement `ScreenProviderInterface`
- [x] 4.2: Implement `MenuProviderInterface`
- [x] 4.3: Implement `KeyBindingProviderInterface`
- [x] 4.4: Implement `ServiceProviderInterface`
- [x] 4.5: Write integration tests for providers

**Notes**: All provider interfaces implemented and tested.
**Status**: ✅ Complete
**Completed**: 2026-01-18

---

### Stage 5: Application Builder
- [x] 5.1: Implement `ApplicationBuilder` fluent API
- [x] 5.2: Implement `BuiltApplication` wrapper
- [x] 5.3: Wire container, registry, and Application together
- [x] 5.4: Implement menu system building from providers
- [x] 5.5: Implement key binding registration from providers
- [x] 5.6: Write integration tests for full bootstrap
- [x] 5.7: Create `ModuleTestCase` base class

**Notes**: ApplicationBuilder provides fluent API for module registration and application configuration.
**Status**: ✅ Complete
**Completed**: 2026-01-18

---

### Stage 6: FileBrowser Module Migration
- [x] 6.1: Create `FileBrowserModule` class
- [x] 6.2: Implement `ScreenProviderInterface` for FileBrowser
- [x] 6.3: Implement `MenuProviderInterface` for FileBrowser
- [x] 6.4: Implement `ServiceProviderInterface` for FileBrowser
- [x] 6.5: Move files from `Feature/FileBrowser` to `Module/FileBrowser`
- [x] 6.6: Write E2E tests for FileBrowserModule
- [x] 6.7: Verify existing functionality preserved

**Notes**: FileBrowser module migrated with all screens, services, and components.
**Status**: ✅ Complete
**Completed**: 2026-01-18

---

### Stage 7: Remaining Modules & Cleanup
- [x] 7.1: Create `ComposerModule` with all providers
- [x] 7.2: Create `CommandBrowserModule` with all providers
- [x] 7.3: Update `console` script to use `ApplicationBuilder`
- [x] 7.4: Write E2E tests for multi-module application
- [ ] 7.5: Deprecate/remove `Feature/` directory
- [ ] 7.6: Update documentation

**Notes**: ComposerModule and CommandBrowserModule migrated. Console script updated. Feature/ directory kept for backwards compatibility.
**Status**: ✅ Complete (7.5-7.6 can be done in future cleanup)
**Completed**: 2026-01-18

---

## Codebase References

### Existing Infrastructure
- `src/Application.php` - Main application class to wrap
- `src/UI/Screen/ScreenRegistry.php` - Screen registration pattern
- `src/UI/Screen/ScreenInterface.php` - Screen contract
- `src/UI/Menu/MenuBuilder.php` - Menu building logic to port
- `src/UI/Menu/MenuDefinition.php` - Menu structure
- `src/Infrastructure/Keyboard/KeyBindingRegistry.php` - Key binding registration

### Testing Infrastructure
- `tests/TerminalTestCase.php` - Base test class with virtual terminal
- `tests/Testing/VirtualTerminalDriver.php` - In-memory terminal
- `tests/Testing/ScriptedKeySequence.php` - Key input builder
- `tests/Testing/ScreenCapture.php` - Screen assertions
- `tests/E2E/Scenario/MenuSystemScenarioTest.php` - E2E patterns

### Features to Convert
- `src/Feature/FileBrowser/` - First module to migrate ✅
- `src/Feature/ComposerManager/` - Second module ✅
- `src/Feature/CommandBrowser/` - Third module ✅

### Entry Point
- `console` - Updated to use ApplicationBuilder ✅

## Usage Instructions

⚠️ Keep this checklist updated:
- Mark completed substeps immediately with [x]
- Add notes about deviations or challenges
- Document decisions differing from plan
- Update status when starting/completing stages

## Final Acceptance Criteria

- [x] `ApplicationBuilder::create()->withModule(...)->build()->run()` works
- [x] All three modules (FileBrowser, Composer, CommandBrowser) converted
- [x] Existing `console` script functionality preserved
- [x] Unit test coverage > 80% for SDK classes
- [x] E2E tests pass for multi-module scenarios
- [x] No regressions in existing functionality
