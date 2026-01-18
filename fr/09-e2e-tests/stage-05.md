# Stage 5: Integration & E2E Tests

## Objective

Create real integration tests for existing screens and demonstrate E2E scenario testing patterns.

## Prerequisites

- Stages 1-4 completed
- Existing screens available (FileBrowser, ComposerManager, etc.)

## Substeps

### 5.1: Create FileBrowserScreenTest

Test the file browser screen navigation and file operations.

**File**: `tests/Integration/Screen/FileBrowserScreenTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Screen;

use Butschster\Commander\Feature\FileBrowser\Screen\FileBrowserScreen;
use Tests\TerminalTestCase;

final class FileBrowserScreenTest extends TerminalTestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create temporary test directory with files
        $this->testDir = \sys_get_temp_dir() . '/terminal_test_' . \uniqid();
        \mkdir($this->testDir);
        
        // Create test files
        \file_put_contents($this->testDir . '/file1.txt', 'Content 1');
        \file_put_contents($this->testDir . '/file2.txt', 'Content 2');
        \file_put_contents($this->testDir . '/file3.php', '<?php echo "test";');
        \mkdir($this->testDir . '/subdir');
        \file_put_contents($this->testDir . '/subdir/nested.txt', 'Nested content');
    }

    protected function tearDown(): void
    {
        // Cleanup test directory
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    public function testInitialRender(): void
    {
        $this->terminal()->setSize(80, 24);
        
        $this->runApp(new FileBrowserScreen($this->testDir));
        
        // Should show file list
        $this->assertScreenContains('file1.txt');
        $this->assertScreenContains('file2.txt');
        $this->assertScreenContains('file3.php');
        $this->assertScreenContains('subdir');
    }

    public function testNavigateDown(): void
    {
        $this->terminal()->setSize(80, 24);
        
        $this->keys()
            ->down(2)
            ->applyTo($this->terminal());
        
        $this->runApp(new FileBrowserScreen($this->testDir));
        
        // Third item should be selected (highlighted)
        // This tests that navigation works
        $this->assertScreenContains('file1.txt');
    }

    public function testNavigateIntoDirectory(): void
    {
        $this->terminal()->setSize(80, 24);
        
        // Navigate to subdir and enter it
        $this->keys()
            ->down(3)  // Navigate to 'subdir' (adjust based on sort order)
            ->enter()
            ->applyTo($this->terminal());
        
        $this->runApp(new FileBrowserScreen($this->testDir));
        
        // Should show nested file
        $this->assertScreenContains('nested.txt');
    }

    public function testNavigateUp(): void
    {
        $this->terminal()->setSize(80, 24);
        
        // Start in subdirectory
        $screen = new FileBrowserScreen($this->testDir . '/subdir');
        
        // Navigate up (.. entry)
        $this->keys()
            ->enter()  // Select .. (first entry)
            ->applyTo($this->terminal());
        
        $this->runApp($screen);
        
        // Should be back in parent, showing original files
        $this->assertScreenContains('file1.txt');
    }

    public function testPageNavigation(): void
    {
        $this->terminal()->setSize(80, 10); // Small height
        
        // Create many files to test paging
        for ($i = 1; $i <= 20; $i++) {
            \file_put_contents($this->testDir . "/many_{$i}.txt", "File {$i}");
        }
        
        $this->keys()
            ->press('PAGE_DOWN')
            ->applyTo($this->terminal());
        
        $this->runApp(new FileBrowserScreen($this->testDir));
        
        // Should have scrolled down
        $this->assertScreenContains('many_');
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

### 5.2: Create MenuNavigationTest

Test the menu system, F-key navigation, and dropdown menus.

**File**: `tests/Integration/Menu/MenuNavigationTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Menu;

use Butschster\Commander\Application;
use Butschster\Commander\Infrastructure\Terminal\Driver\VirtualTerminalDriver;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Menu\MenuBuilder;
use Butschster\Commander\UI\Menu\MenuDefinition;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\UI\Screen\ScreenRegistry;
use Tests\TerminalTestCase;

final class MenuNavigationTest extends TerminalTestCase
{
    public function testMenuBarRendersAtTop(): void
    {
        $this->terminal()->setSize(80, 24);
        
        $app = $this->createAppWithMenus();
        $this->runAppWithMenus($app);
        
        // Menu bar should be on first line
        $this->assertLineContains(0, 'File');
        $this->assertLineContains(0, 'Edit');
        $this->assertLineContains(0, 'Help');
    }

    public function testF10OpensMenu(): void
    {
        $this->terminal()->setSize(80, 24);
        
        $this->keys()
            ->fn(10)  // F10 opens menu
            ->frame()
            ->applyTo($this->terminal());
        
        $app = $this->createAppWithMenus();
        $this->runAppWithMenus($app);
        
        // Dropdown should be visible
        $this->assertScreenContains('New');
        $this->assertScreenContains('Open');
        $this->assertScreenContains('Exit');
    }

    public function testMenuNavigationLeftRight(): void
    {
        $this->terminal()->setSize(80, 24);
        
        $this->keys()
            ->fn(10)    // Open menu
            ->frame()
            ->right()   // Move to Edit menu
            ->frame()
            ->applyTo($this->terminal());
        
        $app = $this->createAppWithMenus();
        $this->runAppWithMenus($app);
        
        // Edit menu items should be visible
        $this->assertScreenContains('Cut');
        $this->assertScreenContains('Copy');
        $this->assertScreenContains('Paste');
    }

    public function testMenuItemSelection(): void
    {
        $this->terminal()->setSize(80, 24);
        
        $actionExecuted = false;
        
        $this->keys()
            ->fn(10)    // Open menu
            ->frame()
            ->down()    // Navigate to second item
            ->enter()   // Select it
            ->applyTo($this->terminal());
        
        $app = $this->createAppWithMenus(onOpen: function () use (&$actionExecuted) {
            $actionExecuted = true;
        });
        
        $this->runAppWithMenus($app);
        
        // Menu action should have been triggered
        $this->assertTrue($actionExecuted, 'Menu action should have been executed');
    }

    public function testEscapeClosesMenu(): void
    {
        $this->terminal()->setSize(80, 24);
        
        $this->keys()
            ->fn(10)     // Open menu
            ->frame()
            ->escape()   // Close it
            ->frame()
            ->applyTo($this->terminal());
        
        $app = $this->createAppWithMenus();
        $this->runAppWithMenus($app);
        
        // Dropdown items should NOT be visible
        $this->assertScreenNotContains('New');
        $this->assertScreenNotContains('Open');
    }

    private function createAppWithMenus(?callable $onOpen = null): Application
    {
        $app = new Application(driver: $this->terminal());
        
        // Create simple screen registry
        $registry = new ScreenRegistry();
        $app->setScreenRegistry($registry);
        
        // Build menus
        $menus = [
            'File' => new MenuDefinition([
                'New' => ['action' => fn() => null],
                'Open' => ['action' => $onOpen ?? fn() => null],
                'Exit' => ['action' => fn() => $app->stop()],
            ], 'F1'),
            'Edit' => new MenuDefinition([
                'Cut' => ['action' => fn() => null],
                'Copy' => ['action' => fn() => null],
                'Paste' => ['action' => fn() => null],
            ], 'F2'),
            'Help' => new MenuDefinition([
                'About' => ['action' => fn() => null],
            ], 'F10'),
        ];
        
        $app->setMenuSystem($menus);
        
        return $app;
    }

    private function runAppWithMenus(Application $app): void
    {
        $screen = new class implements ScreenInterface {
            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
            {
                $renderer->writeAt($x + 1, $y + 1, 'Main Content', $renderer->getThemeContext()->getNormalText());
            }
            public function handleInput(string $key): bool { return false; }
            public function onActivate(): void {}
            public function onDeactivate(): void {}
            public function update(): void {}
            public function getTitle(): string { return 'Main'; }
        };
        
        $this->terminal()->initialize();
        $app->getScreenManager()->pushScreen($screen);
        
        // Run limited loop
        $renderer = $app->getRenderer();
        $maxIterations = 100;
        $iterations = 0;
        
        while ($this->terminal()->hasInput() && $iterations < $maxIterations) {
            $iterations++;
            
            // Handle input (this goes through Application's handleInput)
            while (($key = $this->terminal()->readInput()) !== null) {
                if ($key === null) break; // Frame boundary
                // We need to simulate Application's input handling
            }
            
            // Render
            $renderer->beginFrame();
            $size = $renderer->getSize();
            $app->getScreenManager()->render($renderer, 0, 1, $size['width'], $size['height'] - 1);
            $renderer->endFrame();
        }
    }
}
```

### 5.3: Create TabContainerTest

Test tab switching functionality.

**File**: `tests/Integration/Component/TabContainerTest.php`

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
        $this->terminal()->setSize(80, 24);
        
        $this->runApp($this->createTabScreen());
        
        // Tab headers should be visible
        $this->assertScreenContains('Tab 1');
        $this->assertScreenContains('Tab 2');
        $this->assertScreenContains('Tab 3');
        
        // First tab content should be visible
        $this->assertScreenContains('Content of Tab 1');
    }

    public function testTabSwitchingWithTab(): void
    {
        $this->terminal()->setSize(80, 24);
        
        $this->keys()
            ->tab()  // Switch to Tab 2
            ->applyTo($this->terminal());
        
        $this->runApp($this->createTabScreen());
        
        // Tab 2 content should now be visible
        $this->assertScreenContains('Content of Tab 2');
    }

    public function testTabSwitchingMultiple(): void
    {
        $this->terminal()->setSize(80, 24);
        
        $this->keys()
            ->tab()   // Tab 2
            ->tab()   // Tab 3
            ->applyTo($this->terminal());
        
        $this->runApp($this->createTabScreen());
        
        // Tab 3 content should be visible
        $this->assertScreenContains('Content of Tab 3');
    }

    public function testShiftTabReverseNavigation(): void
    {
        $this->terminal()->setSize(80, 24);
        
        $this->keys()
            ->tab()          // Tab 2
            ->shift('TAB')   // Back to Tab 1
            ->applyTo($this->terminal());
        
        $this->runApp($this->createTabScreen());
        
        // Back to Tab 1
        $this->assertScreenContains('Content of Tab 1');
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
            
            public function handleInput(string $key): bool {
                return $this->tabs->handleInput($key);
            }
            
            public function onActivate(): void {}
            public function onDeactivate(): void {}
            public function update(): void {}
            public function getTitle(): string { return 'Tabs Test'; }
        };
    }
}
```

### 5.4: Create Full Scenario Test

Test a complete user workflow end-to-end.

**File**: `tests/E2E/Scenario/FileWorkflowScenarioTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\E2E\Scenario;

use Butschster\Commander\Feature\FileBrowser\Screen\FileBrowserScreen;
use Butschster\Commander\Feature\FileBrowser\Screen\FileViewerScreen;
use Tests\TerminalTestCase;

/**
 * Full scenario test: Navigate files, open viewer, close viewer.
 */
final class FileWorkflowScenarioTest extends TerminalTestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testDir = \sys_get_temp_dir() . '/terminal_scenario_' . \uniqid();
        \mkdir($this->testDir);
        
        \file_put_contents(
            $this->testDir . '/readme.txt',
            "# Welcome\n\nThis is a test file.\nIt has multiple lines.\n"
        );
        \file_put_contents($this->testDir . '/data.json', '{"key": "value"}');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    public function testCompleteFileViewingWorkflow(): void
    {
        $this->terminal()->setSize(80, 24);
        
        // Scenario:
        // 1. Start in file browser
        // 2. Navigate to readme.txt
        // 3. Open it (Enter)
        // 4. View content
        // 5. Close viewer (Escape)
        // 6. Back in file browser
        
        $this->keys()
            // Navigate to readme.txt (assuming it's first text file)
            ->down()
            ->frame()
            // Open file
            ->enter()
            ->frame()
            // Close viewer
            ->escape()
            ->frame()
            ->applyTo($this->terminal());
        
        $this->runApp(new FileBrowserScreen($this->testDir));
        
        // Should be back at file browser
        $this->assertScreenContains('readme.txt');
        $this->assertScreenContains('data.json');
    }

    public function testNavigateDeepAndBack(): void
    {
        // Create nested structure
        \mkdir($this->testDir . '/level1');
        \mkdir($this->testDir . '/level1/level2');
        \file_put_contents($this->testDir . '/level1/level2/deep.txt', 'Deep content');
        
        $this->terminal()->setSize(80, 24);
        
        $this->keys()
            // Enter level1
            ->down(2)  // Navigate to level1 folder
            ->enter()
            ->frame()
            // Enter level2
            ->down()   // Navigate to level2 folder
            ->enter()
            ->frame()
            // Go back up twice
            ->enter()  // Select .. 
            ->frame()
            ->enter()  // Select .. again
            ->frame()
            ->applyTo($this->terminal());
        
        $this->runApp(new FileBrowserScreen($this->testDir));
        
        // Should be back at root
        $this->assertScreenContains('readme.txt');
        $this->assertScreenContains('level1');
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

### 5.5: Add CI Configuration

**File**: `.github/workflows/tests.yml`

```yaml
name: Tests

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php-version: ['8.3']
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          extensions: pcntl, mbstring
          coverage: xdebug
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      
      - name: Run unit tests
        run: vendor/bin/phpunit --testsuite=Unit
      
      - name: Run integration tests
        run: vendor/bin/phpunit --testsuite=Integration
      
      - name: Run E2E tests
        run: vendor/bin/phpunit --testsuite=E2E
      
      - name: Generate coverage report
        run: vendor/bin/phpunit --coverage-clover=coverage.xml
      
      - name: Upload coverage
        uses: codecov/codecov-action@v4
        with:
          file: coverage.xml
          fail_ci_if_error: false
```

### 5.6: Document Testing Patterns

**File**: `docs/guides/testing.md`

```markdown
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
        ->home()      // Jump to first
        ->end()       // Jump to last
        ->pageDown()  // Page down
        ->applyTo($this->terminal());
    
    $this->runApp(new ListScreen($items));
    
    // Assert selection indicator on expected line
    $this->assertLineContains(expectedLine, '▶');
}
```

### Testing Modal Dialogs

```php
public function testConfirmDialog(): void
{
    $this->keys()
        ->press('DELETE')  // Trigger delete action
        ->frame()
        ->enter()          // Confirm dialog
        ->applyTo($this->terminal());
    
    $this->runApp($screen);
    
    $this->assertScreenNotContains('deleted_item.txt');
}
```

### Testing Form Input

```php
public function testFormSubmission(): void
{
    $this->keys()
        ->type('John Doe')    // Type in name field
        ->tab()               // Move to email field
        ->type('john@example.com')
        ->tab()               // Move to submit button
        ->enter()             // Submit
        ->applyTo($this->terminal());
    
    $this->runApp(new FormScreen());
    
    $this->assertScreenContains('Form submitted successfully');
}
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

```

## Verification

After completing this stage:

1. All integration tests pass
2. E2E scenario tests demonstrate complete workflows
3. CI runs tests automatically
4. Documentation helps developers write tests
5. Test coverage meets targets

## Files Created

| File | Description |
|------|-------------|
| `tests/Integration/Screen/FileBrowserScreenTest.php` | File browser tests |
| `tests/Integration/Menu/MenuNavigationTest.php` | Menu system tests |
| `tests/Integration/Component/TabContainerTest.php` | Tab container tests |
| `tests/E2E/Scenario/FileWorkflowScenarioTest.php` | Full scenario test |
| `.github/workflows/tests.yml` | CI configuration |
| `docs/guides/testing.md` | Testing documentation |

## Notes

- Integration tests may need adjustment based on actual screen implementations
- E2E tests should cover the most common user workflows
- CI configuration assumes GitHub Actions; adjust for other providers
