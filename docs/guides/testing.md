# Terminal Testing Guide

## Overview

The terminal testing framework provides comprehensive tools for testing TUI applications without a real terminal. It
uses a driver abstraction layer that allows swapping real terminal I/O with virtual (in-memory) implementation.

### Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                      Application                             │
├──────────────────────────────────────────────────────────────┤
│  ScreenManager  │  KeyboardHandler  │  Renderer              │
├─────────────────┴───────────────────┴────────────────────────┤
│                  TerminalDriverInterface                     │
├──────────────────────────────────────────────────────────────┤
│  RealTerminalDriver  │  VirtualTerminalDriver                │
│  (STDIN/STDOUT)      │  (In-memory buffers)                  │
└──────────────────────────────────────────────────────────────┘
```

### Key Components

| Component               | Location         | Purpose                                                |
|-------------------------|------------------|--------------------------------------------------------|
| `VirtualTerminalDriver` | `tests/Testing/` | Simulates terminal with input queue and output capture |
| `AnsiParser`            | `tests/Testing/` | Parses ANSI escape sequences into screen buffer        |
| `ScreenCapture`         | `tests/Testing/` | Immutable snapshot of screen state for assertions      |
| `ScriptedKeySequence`   | `tests/Testing/` | Fluent builder for key input sequences                 |
| `TerminalTestCase`      | `tests/`         | Base test class with assertions and helpers            |

## Getting Started

### Basic Test Structure

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Screen;

use Tests\TerminalTestCase;

final class MyScreenTest extends TerminalTestCase
{
    public function testBasicRendering(): void
    {
        // 1. Configure terminal size
        $this->terminal()->setSize(180. 64);
        
        // 2. Queue input sequence (optional)
        $this->keys()
            ->down(3)
            ->enter()
            ->applyTo($this->terminal());
        
        // 3. Run the application with your screen
        $this->runApp(new MyScreen());
        
        // 4. Assert expected state
        $this->assertScreenContains('Expected text');
    }
}
```

### Test Suites

Tests are organized into three suites in `phpunit.xml`:

| Suite       | Directory            | Purpose                                           |
|-------------|----------------------|---------------------------------------------------|
| Unit        | `tests/Unit/`        | Test isolated classes without dependencies        |
| Integration | `tests/Integration/` | Test screens and components with virtual terminal |
| E2E         | `tests/E2E/`         | Test complete user workflows                      |

## ScriptedKeySequence API

The fluent key sequence builder creates input for the virtual terminal.

### Basic Keys

```php
$this->keys()
    ->press('F10')        // Single key press
    ->down()              // Arrow down (shorthand)
    ->up()                // Arrow up (shorthand)
    ->left()              // Arrow left (shorthand)
    ->right()             // Arrow right (shorthand)
    ->enter()             // Enter key
    ->escape()            // Escape key
    ->tab()               // Tab key
    ->fn(1)               // Function key F1 (1-12)
```

### Repetition

```php
$this->keys()
    ->down(5)             // Press DOWN 5 times
    ->repeat('TAB', 3)    // Press TAB 3 times
    ->tab(2)              // Press TAB 2 times (shorthand)
```

### Modifiers

```php
$this->keys()
    ->ctrl('C')           // Ctrl+C
    ->ctrl('S')           // Ctrl+S
    ->alt('X')            // Alt+X
    ->shift('TAB')        // Shift+Tab
```

### Text Input

```php
$this->keys()
    ->type('Hello World') // Types each character
    ->type("line1\nline2") // Newlines become ENTER
```

### Frame Boundaries

Frame boundaries signal the application to process all previous input and render:

```php
$this->keys()
    ->fn(10)              // Open menu
    ->frame()             // Process and render
    ->down()              // Navigate in menu
    ->enter()             // Select item
    ->applyTo($this->terminal());
```

### Applying to Terminal

```php
// Option 1: Apply directly
$this->keys()
    ->down(3)
    ->enter()
    ->applyTo($this->terminal());

// Option 2: Build array for manual use
$sequence = $this->keys()
    ->down(3)
    ->enter()
    ->build();
// Returns: ['DOWN', 'DOWN', 'DOWN', 'ENTER']
```

## VirtualTerminalDriver API

Direct access via `$this->terminal()`:

### Size Configuration

```php
$this->terminal()->setSize(120, 40);  // Set dimensions
$this->terminal()->getSize();          // Returns ['width' => 120, 'height' => 40]
```

> IMPORTANT: Low width and height values may cause hiding some of the terminal output and make assertions fail.

### Input Queue

```php
$this->terminal()->queueInput('UP', 'DOWN', 'ENTER');  // Queue multiple keys
$this->terminal()->queueFrameBoundary();               // Add frame boundary
$this->terminal()->clearInput();                       // Clear queue
$this->terminal()->hasInput();                         // Check if queue has items
$this->terminal()->getRemainingInputCount();           // Get queue size
$this->terminal()->readInput();                        // Read next (usually not needed)
```

### Output

```php
$this->terminal()->getOutput();        // Get raw ANSI output
$this->terminal()->clearOutput();      // Clear output buffer
$this->terminal()->getScreenCapture(); // Get parsed ScreenCapture
```

### State

```php
$this->terminal()->isInitialized();    // Check if initialize() was called
$this->terminal()->isInteractive();    // Always false for virtual driver
```

## ScreenCapture API

Get via `$this->capture()` or `$this->terminal()->getScreenCapture()`:

### Text Queries

```php
$capture = $this->capture();

// Check text presence
$capture->contains('Hello');                    // Returns bool

// Find text position
$capture->findText('Hello');                    // Returns ['x' => 5, 'y' => 3] or null
$capture->findAllText('Item');                  // Returns array of positions

// Get text at position
$capture->getText(0, 0, 10);                    // 10 chars starting at (0,0)

// Get lines
$capture->getLine(5);                           // Line 5, trailing spaces trimmed
$capture->getLineTrimmed(5);                    // Same as getLine()
$capture->getLineRaw(5);                        // Line 5, with trailing spaces

// Get region
$capture->getRegion(0, 0, 20, 5);               // 20x5 region as array of lines

// Get non-empty lines
$capture->getNonEmptyLines();                   // [lineNum => content, ...]
```

### Color Queries

```php
$capture->getColorAt(5, 3);                     // Get ANSI color code at position
$capture->hasColorAt(5, 3, "\033[1;31m");       // Check specific color
```

### Dimensions

```php
$capture->getSize();                            // Returns ['width' => 80, 'height' => 24]
```

### Debugging

```php
$capture->dump();                               // Visual box representation
$capture->dumpWithLineNumbers();                // With line numbers
```

## Assertions Reference

### Content Assertions

```php
// Basic presence
$this->assertScreenContains('Hello');
$this->assertScreenNotContains('Error');

// Multiple texts
$this->assertScreenContainsAll(['File', 'Edit', 'Help']);
$this->assertScreenContainsAny(['OK', 'Cancel']);
$this->assertScreenContainsCount('Item', 3);   // Exactly 3 occurrences
```

### Position Assertions

```php
// Exact position
$this->assertTextAt(0, 0, 'Title');

// Line assertions
$this->assertLineContains(5, 'Selected');      // Line contains text
$this->assertTextOnLine(5, 'Selected');        // Alias for lineContains
$this->assertLineEquals(10, 'Status: OK');     // Exact match (trimmed)
$this->assertLineStartsWith(0, 'File');        // Line prefix
$this->assertLineEmpty(23);                    // Line has no visible content

// Region assertion
$this->assertRegion(0, 0, 10, 3, [
    'Line one  ',
    'Line two  ',
    'Line three',
]);
```

### Screen State Assertions

```php
$this->assertCurrentScreen(FileBrowserScreen::class);
$this->assertScreenTitle('File Browser');
$this->assertScreenDepth(2);                   // Screen stack depth
```

### Color Assertions

```php
$this->assertColorAt(0, 0, "\033[1;37m");
$this->assertTextHasColor('Error', "\033[31m");
```

## Debug Helpers

```php
// Output to console during test development
$this->dumpScreen();                           // Show screen content
$this->dumpScreenWithLines();                  // With line numbers
$this->dumpNonEmptyLines();                    // Compact - only non-empty

// Labeled debug point
$this->pauseAndShow('After pressing F10');
```

## Complete Examples

### Testing a Screen with Dependencies

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Screen;

use Butschster\Commander\Feature\FileBrowser\Screen\FileBrowserScreen;
use Butschster\Commander\Feature\FileBrowser\Service\FileSystemService;
use Butschster\Commander\UI\Screen\ScreenManager;
use Tests\TerminalTestCase;

final class FileBrowserScreenTest extends TerminalTestCase
{
    private string $testDir;
    private FileSystemService $fileSystem;
    private ScreenManager $screenManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Create isolated test directory
        $this->testDir = \sys_get_temp_dir() . '/terminal_test_' . \uniqid();
        \mkdir($this->testDir);

        // Create test files
        \file_put_contents($this->testDir . '/file1.txt', 'Content 1');
        \file_put_contents($this->testDir . '/file2.txt', 'Content 2');
        \mkdir($this->testDir . '/subdir');

        // Initialize dependencies
        $this->fileSystem = new FileSystemService();
        $this->screenManager = new ScreenManager();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    public function testInitialRender(): void
    {
        $this->terminal()->setSize(180. 64);

        $this->runApp(new FileBrowserScreen(
            $this->fileSystem,
            $this->screenManager,
            $this->testDir
        ));

        $this->assertScreenContainsAll(['file1.txt', 'file2.txt', 'subdir']);
    }

    public function testNavigateDown(): void
    {
        $this->terminal()->setSize(180. 64);

        $this->keys()
            ->down(2)
            ->applyTo($this->terminal());

        $this->runApp(new FileBrowserScreen(
            $this->fileSystem,
            $this->screenManager,
            $this->testDir
        ));

        // Verify navigation worked - files still visible
        $this->assertScreenContains('file1.txt');
    }

    private function removeDirectory(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $files = \array_diff(\scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            \is_dir($path) ? $this->removeDirectory($path) : \unlink($path);
        }
        \rmdir($dir);
    }
}
```

### Testing Components with Anonymous Screens

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Component;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Container\AbstractTab;
use Butschster\Commander\UI\Component\Container\TabContainer;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Tests\TerminalTestCase;

final class TabContainerTest extends TerminalTestCase
{
    public function testTabsRenderCorrectly(): void
    {
        $this->terminal()->setSize(180. 64);

        $this->runApp($this->createTabScreen());

        $this->assertScreenContainsAll(['Tab 1', 'Tab 2', 'Tab 3']);
        $this->assertScreenContains('Content of Tab 1');
    }

    public function testSwitchToSecondTab(): void
    {
        $this->terminal()->setSize(180. 64);

        $screen = $this->createTabScreenWithActiveTab(1);
        $this->runApp($screen);

        $this->assertScreenContains('Content of Tab 2');
    }

    private function createTabScreen(): ScreenInterface
    {
        return new class implements ScreenInterface {
            private TabContainer $tabs;

            public function __construct()
            {
                $this->tabs = new TabContainer();

                $this->tabs->addTab(new class extends AbstractTab {
                    public function getTitle(): string { return 'Tab 1'; }
                    public function render(Renderer $renderer, int $x, int $y, ?int $width, ?int $height): void {
                        $renderer->writeAt($x + 2, $y + 2, 'Content of Tab 1', $renderer->getThemeContext()->getNormalText());
                    }
                });

                $this->tabs->addTab(new class extends AbstractTab {
                    public function getTitle(): string { return 'Tab 2'; }
                    public function render(Renderer $renderer, int $x, int $y, ?int $width, ?int $height): void {
                        $renderer->writeAt($x + 2, $y + 2, 'Content of Tab 2', $renderer->getThemeContext()->getNormalText());
                    }
                });

                $this->tabs->addTab(new class extends AbstractTab {
                    public function getTitle(): string { return 'Tab 3'; }
                    public function render(Renderer $renderer, int $x, int $y, ?int $width, ?int $height): void {
                        $renderer->writeAt($x + 2, $y + 2, 'Content of Tab 3', $renderer->getThemeContext()->getNormalText());
                    }
                });
            }

            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void {
                $this->tabs->render($renderer, $x, $y, $width, $height);
            }

            public function handleInput(string $key): bool { return false; }
            public function onActivate(): void {}
            public function onDeactivate(): void {}
            public function update(): void {}
            public function getTitle(): string { return 'Tabs Test'; }
        };
    }

    private function createTabScreenWithActiveTab(int $tabIndex): ScreenInterface
    {
        $screen = $this->createTabScreen();
        // Access tabs via reflection or create screen with pre-selected tab
        return $screen;
    }
}
```

### Testing with runUntil for Async Operations

```php
public function testWaitForLoadingComplete(): void
{
    $this->terminal()->setSize(180. 64);

    $this->runUntil(
        new LoadingScreen(),
        fn(ScreenCapture $capture) => $capture->contains('Loading complete'),
        maxFrames: 100
    );

    $this->assertScreenContains('Data loaded successfully');
}
```

## Best Practices

### 1. Isolate Test Data

Always create fresh test data and clean up after tests:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->testDir = \sys_get_temp_dir() . '/test_' . \uniqid();
    \mkdir($this->testDir);
}

protected function tearDown(): void
{
    $this->removeDirectory($this->testDir);
    parent::tearDown();
}
```

### 2. Test One Behavior Per Test

```php
// ✅ Good - focused tests
public function testNavigateDownSelectsNextItem(): void { ... }
public function testEnterOpensSelectedFile(): void { ... }
public function testEscapeClosesDialog(): void { ... }

// ❌ Bad - too many behaviors
public function testFileOperations(): void { /* does too much */ }
```

### 3. Use Frame Boundaries for Multi-Step Interactions

```php
$this->keys()
    ->fn(10)      // Open menu
    ->frame()     // Wait for menu to render
    ->down()      // Navigate
    ->frame()     // Wait for selection to update
    ->enter()     // Select
    ->applyTo($this->terminal());
```

### 4. Use Descriptive Assertion Messages

```php
$this->assertScreenContains(
    'file.txt',
    'File list should show the newly created file'
);
```

### 5. Debug Before Asserting (During Development)

```php
public function testSomething(): void
{
    $this->terminal()->setSize(180. 64);
    $this->runApp(new MyScreen());
    
    // Uncomment during development to see screen state
    // $this->dumpScreenWithLines();
    
    $this->assertScreenContains('Expected');
}
```

### 6. Set Appropriate Terminal Size

Different sizes may affect layout. Test with realistic dimensions:

```php
// Standard terminal
$this->terminal()->setSize(180. 64);

// Wide terminal
$this->terminal()->setSize(120, 40);

// Small terminal (edge case)
$this->terminal()->setSize(40, 10);
```

### 7. Clean Up Screen Dependencies

Screens often require services. Initialize them in setUp:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->fileSystem = new FileSystemService();
    $this->screenManager = new ScreenManager();
}
```

## Running Tests

### Command Line

```bash
# All tests
composer test

# By suite
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Integration
vendor/bin/phpunit --testsuite=E2E

# By filter
vendor/bin/phpunit --filter=FileBrowserScreenTest
vendor/bin/phpunit --filter=testNavigateDown

# With coverage
vendor/bin/phpunit --coverage-html=coverage
```

### MCP Tools (via context.yaml)

```
test              - Run all tests
test-unit         - Run unit tests only
test-integration  - Run integration tests only
test-e2e          - Run E2E tests only
test-filter       - Run tests matching pattern (requires 'filter' parameter)
```

## Troubleshooting

### Test Hangs

Input queue may be empty before all actions complete. Solutions:

- Add `frame()` boundaries between actions
- Check loop termination conditions
- Verify screen handles all input keys

### Unexpected Screen Content

1. Add `$this->dumpScreen()` or `$this->dumpScreenWithLines()` before assertions
2. Verify terminal size matches expected layout
3. Check input sequence is correct
4. Ensure dependencies are properly initialized

### Flaky Tests

- Ensure test data is isolated (unique directories)
- Use `frame()` for timing-sensitive sequences
- Avoid relying on specific file system ordering
- Check for state leaking between tests

### Class Not Found Errors

Ensure autoload is up to date:

```bash
composer dump-autoload
```

### ANSI Parsing Issues

If screen content doesn't match expected:

1. Check `$this->terminal()->getOutput()` for raw ANSI
2. Verify AnsiParser handles the specific escape sequences
3. Some complex sequences may need parser updates
