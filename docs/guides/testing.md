# Terminal Testing Guide

## Overview

The terminal testing framework allows you to test TUI applications without a real terminal.
It provides scripted keyboard input, screen capture, and comprehensive assertions.

## Getting Started

### Basic Test Structure

```php
use Tests\TerminalTestCase;

class MyFeatureTest extends TerminalTestCase
{
    public function testSomething(): void
    {
        // 1. Configure terminal size
        $this->terminal()->setSize(80, 24);
        
        // 2. Queue input sequence
        $this->keys()
            ->down(3)
            ->enter()
            ->applyTo($this->terminal());
        
        // 3. Run the application
        $this->runApp(new MyScreen());
        
        // 4. Assert expected state
        $this->assertScreenContains('Expected text');
    }
}
```

## Key Sequence Builder

```php
// Simple keys
$this->keys()
    ->press('F10')
    ->down()
    ->enter()

// Modifiers
$this->keys()
    ->ctrl('C')      // Ctrl+C
    ->alt('X')       // Alt+X
    ->shift('TAB')   // Shift+Tab

// Typing
$this->keys()
    ->type('Hello')  // Types H-e-l-l-o
    ->enter()

// Repetition
$this->keys()
    ->repeat('DOWN', 5)  // Press DOWN 5 times
    ->down(5)            // Same thing

// Frame boundaries
$this->keys()
    ->press('F10')
    ->frame()        // Process F10, render frame
    ->press('DOWN')  // Then continue
```

## Best Practices

### 1. Use Frame Boundaries for Complex Sequences

When testing multi-step interactions, use `frame()` to ensure each step is processed:

```php
$this->keys()
    ->fn(10)     // Open menu
    ->frame()    // Wait for menu to render
    ->down()     // Navigate
    ->enter()    // Select
    ->applyTo($this->terminal());
```

### 2. Create Isolated Test Data

Always create fresh test data and clean up:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->testDir = sys_get_temp_dir() . '/test_' . uniqid();
    mkdir($this->testDir);
}

protected function tearDown(): void
{
    $this->removeDirectory($this->testDir);
    parent::tearDown();
}
```

### 3. Test One Behavior Per Test

```php
// Good
public function testNavigateDownSelectsNextItem(): void { ... }
public function testEnterOpensSelectedFile(): void { ... }

// Bad
public function testFileOperations(): void { /* too many things */ }
```

### 4. Use Descriptive Assertion Messages

```php
$this->assertScreenContains(
    'file.txt',
    'File list should show the newly created file'
);
```

### 5. Debug Failing Tests

When a test fails, use dump methods:

```php
public function testSomething(): void
{
    // ... setup ...
    
    $this->runApp($screen);
    
    // Debug: see what's on screen
    $this->dumpScreenWithLines();
    
    // ... assertions ...
}
```

## Common Patterns

### Testing Navigation

```php
public function testListNavigation(): void
{
    $this->terminal()->setSize(80, 24);
    
    $this->keys()
        ->down(5)     // Move down 5 items
        ->press('HOME')  // Jump to first
        ->press('END')   // Jump to last
        ->press('PAGE_DOWN')
        ->applyTo($this->terminal());
    
    $this->runApp(new ListScreen($items));
    
    $this->assertScreenContains('expected item');
}
```

### Testing Form Input

```php
public function testFormSubmission(): void
{
    $this->keys()
        ->type('John Doe')
        ->tab()
        ->type('john@example.com')
        ->tab()
        ->enter()
        ->applyTo($this->terminal());
    
    $this->runApp(new FormScreen());
    
    $this->assertScreenContains('Form submitted');
}
```

## Running Tests

```bash
# All tests
composer test

# Unit tests only
vendor/bin/phpunit --testsuite=Unit

# Integration tests only
vendor/bin/phpunit --testsuite=Integration

# E2E tests only
vendor/bin/phpunit --testsuite=E2E

# With coverage
vendor/bin/phpunit --coverage-html=coverage
```

## Troubleshooting

### Test Hangs

If a test hangs, the input queue might be empty before all actions complete.
Add `frame()` boundaries or check the loop termination condition.

### Unexpected Screen Content

1. Add `$this->dumpScreen()` before assertions
2. Check terminal size matches expected layout
3. Verify input sequence is correct

### Flaky Tests

- Ensure test data is isolated
- Use `frame()` for timing-sensitive sequences
- Check for race conditions in async operations
