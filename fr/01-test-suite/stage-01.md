# Stage 1: Test Infrastructure Setup

## Overview

Set up the foundational test infrastructure including directory structure, PHPUnit configuration, base test case, and
mock objects. This stage enables all subsequent testing stages.

## Files

CREATE:

- `tests/TestCase.php` - Base test case with common utilities
- `tests/Mock/MockRenderer.php` - Mock renderer for testing component output
- `tests/Mock/MockTerminalManager.php` - Mock terminal for testing without real terminal
- `phpunit.xml` - PHPUnit configuration

## Code References

- `src/Infrastructure/Terminal/Renderer.php` - Interface to mock for testing
- `src/Infrastructure/Terminal/TerminalManager.php` - Terminal operations to mock
- `composer.json:autoload-dev` - PSR-4 autoloading for tests namespace

## Implementation Details

### Directory Structure

```
tests/
├── TestCase.php
├── Mock/
│   ├── MockRenderer.php
│   └── MockTerminalManager.php
├── Unit/
├── Integration/
└── Feature/
```

### Base TestCase

```php
<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Mock\MockRenderer;

abstract class TestCase extends BaseTestCase
{
    protected function createMockRenderer(int $width = 80, int $height = 24): MockRenderer
    {
        return new MockRenderer($width, $height);
    }
    
    protected function assertRenderedAt(MockRenderer $renderer, int $x, int $y, string $expected): void
    {
        $actual = $renderer->getTextAt($x, $y, \mb_strlen($expected));
        $this->assertEquals($expected, $actual, "Expected '{$expected}' at ({$x}, {$y}), got '{$actual}'");
    }
}
```

### MockRenderer

Should capture all `writeAt()` calls into a buffer array for inspection:

- Store cells as `[y][x] => ['char' => string, 'color' => string]`
- Provide `getTextAt(x, y, length)` for assertions
- Provide `getColorAt(x, y)` for color assertions
- Provide `dump()` for debugging

### PHPUnit Configuration

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

## Definition of Done

- [ ] `tests/` directory structure created
- [ ] `phpunit.xml` configuration file exists and is valid
- [ ] `TestCase.php` base class created with helper methods
- [ ] `MockRenderer.php` captures rendering output for assertions
- [ ] `MockTerminalManager.php` provides terminal operations without real terminal
- [ ] Running `vendor/bin/phpunit` executes successfully (0 tests, 0 assertions)
- [ ] Autoloading works for `Tests\` namespace

## Dependencies

**Requires**: None (first stage)
**Enables**: Stage 2, 3, 4, 5 (all testing stages)
