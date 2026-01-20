# Feature: Test Suite Infrastructure

## Overview

Create a comprehensive test suite for the CLI/Terminal framework. Currently, no tests exist despite PHPUnit being a dev
dependency. This is critical for ensuring code quality and preventing regressions.

## Stage Dependencies

```
Stage 1 (Setup) → Stage 2 (Unit: Components) → Stage 3 (Unit: Infrastructure)
                                             → Stage 4 (Integration)
                                             → Stage 5 (Feature Tests)
```

## Development Progress

### Stage 1: Test Infrastructure Setup

- [ ] Substep 1.1: Create `tests/` directory structure with Unit/Integration/Feature subdirectories
- [ ] Substep 1.2: Create `phpunit.xml` configuration file
- [ ] Substep 1.3: Create base `TestCase.php` with common utilities
- [ ] Substep 1.4: Create `MockRenderer` for testing component rendering
- [ ] Substep 1.5: Verify PHPUnit runs with empty test suite

**Notes**:
**Status**: Not Started
**Completed**:

---

### Stage 2: Unit Tests - Core Components

- [ ] Substep 2.1: Create `TableComponentTest.php` - test rendering, selection, scrolling
- [ ] Substep 2.2: Create `ListComponentTest.php` - test items, navigation, callbacks
- [ ] Substep 2.3: Create `TextDisplayTest.php` - test text setting, scrolling, wrapping
- [ ] Substep 2.4: Create `PanelTest.php` - test borders, title, content
- [ ] Substep 2.5: Achieve 80%+ coverage for Display components

**Notes**:
**Status**: Not Started
**Completed**:

---

### Stage 3: Unit Tests - Infrastructure

- [ ] Substep 3.1: Create `KeyboardHandlerTest.php` - test key parsing, escape sequences
- [ ] Substep 3.2: Create `RendererTest.php` - test buffer operations, writeAt, drawBox
- [ ] Substep 3.3: Create `ColorSchemeTest.php` - test color combining, theme application
- [ ] Substep 3.4: Create `SizeUnitTest.php` - test percentage, fixed, flex calculations

**Notes**:
**Status**: Not Started
**Completed**:

---

### Stage 4: Unit Tests - Layout System

- [ ] Substep 4.1: Create `GridLayoutTest.php` - test column/row sizing, component placement
- [ ] Substep 4.2: Create `StackLayoutTest.php` - test vertical/horizontal stacking
- [ ] Substep 4.3: Create `SplitLayoutTest.php` - test ratio-based splitting
- [ ] Substep 4.4: Create `TabContainerTest.php` - test tab switching, activation

**Notes**:
**Status**: Not Started
**Completed**:

---

### Stage 5: Integration Tests - Screens

- [ ] Substep 5.1: Create `ScreenManagerTest.php` - test push/pop, navigation
- [ ] Substep 5.2: Create `ScreenRegistryTest.php` - test registration, metadata extraction
- [ ] Substep 5.3: Create `MenuSystemTest.php` - test menu building, F-key handling
- [ ] Substep 5.4: Create sample screen integration test

**Notes**:
**Status**: Not Started
**Completed**:

---

## Codebase References

- `src/UI/Component/AbstractComponent.php` - Base component to test
- `src/UI/Component/Display/TableComponent.php` - Complex component example
- `src/Infrastructure/Terminal/Renderer.php` - Rendering logic to mock
- `src/Infrastructure/Terminal/KeyboardHandler.php` - Input parsing to test
- `composer.json:require-dev` - PHPUnit already configured

## File Structure

```
tests/
├── TestCase.php
├── Mock/
│   ├── MockRenderer.php
│   └── MockTerminalManager.php
├── Unit/
│   ├── Component/
│   │   ├── Display/
│   │   │   ├── TableComponentTest.php
│   │   │   ├── ListComponentTest.php
│   │   │   └── TextDisplayTest.php
│   │   ├── Container/
│   │   │   ├── GridLayoutTest.php
│   │   │   ├── StackLayoutTest.php
│   │   │   └── TabContainerTest.php
│   │   └── Layout/
│   │       └── PanelTest.php
│   ├── Infrastructure/
│   │   ├── KeyboardHandlerTest.php
│   │   └── RendererTest.php
│   └── Theme/
│       └── ColorSchemeTest.php
└── Integration/
    ├── Screen/
    │   ├── ScreenManagerTest.php
    │   └── ScreenRegistryTest.php
    └── Menu/
        └── MenuSystemTest.php
```

## Usage Instructions

⚠️ Keep this checklist updated:

- Mark completed substeps immediately with [x]
- Add notes about deviations or challenges
- Document decisions differing from plan
- Update status when starting/completing stages
- Run `composer test` after each stage to verify
