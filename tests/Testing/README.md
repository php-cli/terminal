# Terminal Testing Framework

## Quick Start

```php
use Tests\TerminalTestCase;

class MyScreenTest extends TerminalTestCase
{
    public function testBasicNavigation(): void
    {
        // 1. Configure terminal
        $this->terminal()->setSize(80, 24);
        
        // 2. Queue input
        $this->keys()
            ->down(3)
            ->enter()
            ->applyTo($this->terminal());
        
        // 3. Run application
        $this->runApp(new MyScreen());
        
        // 4. Assert results
        $this->assertScreenContains('Expected content');
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

## Screen Assertions

### Content Assertions

```php
// Text presence
$this->assertScreenContains('Hello');
$this->assertScreenNotContains('Error');
$this->assertScreenContainsAll(['File', 'Edit', 'Help']);
$this->assertScreenContainsAny(['OK', 'Cancel']);
$this->assertScreenContainsCount('Item', 3);  // Exactly 3 occurrences

// Position-specific
$this->assertTextAt(0, 0, 'Title');           // Exact position
$this->assertTextOnLine(5, 'Selected');       // Anywhere on line 5
$this->assertLineContains(0, 'Menu');         // Line contains text
$this->assertLineEquals(10, 'Status: OK');    // Line equals exactly
$this->assertLineStartsWith(0, 'File');       // Line prefix
$this->assertLineEmpty(23);                   // Line is empty
```

### Screen State Assertions

```php
// Current screen
$this->assertCurrentScreen(FileBrowserScreen::class);
$this->assertScreenTitle('File Browser');
$this->assertScreenDepth(2);
```

### Color Assertions

```php
$this->assertColorAt(0, 0, "\033[1;37m");
$this->assertTextHasColor('Error', "\033[31m");
```

## Debugging

```php
// During test development
$this->dumpScreen();           // Show screen content
$this->dumpScreenWithLines();  // With line numbers
$this->dumpNonEmptyLines();    // Compact output

// In assertions
$this->pauseAndShow('After F10');  // Label + dump
```

## Advanced: Conditional Execution

```php
// Run until condition met (useful for async operations)
$this->runUntil(
    new LoadingScreen(),
    fn(ScreenCapture $s) => $s->contains('Loading complete'),
    maxFrames: 100
);
```

## Screen Capture API

```php
$capture = $this->capture();

// Text queries
$capture->contains('text');
$capture->findText('text');        // Returns ['x' => 5, 'y' => 3] or null
$capture->findAllText('item');     // All occurrences
$capture->getText(0, 0, 10);       // 10 chars starting at (0,0)
$capture->getLine(5);              // Line 5 (trimmed)
$capture->getLineTrimmed(5);       // Same as getLine
$capture->getLineRaw(5);           // Line 5 (with spaces)
$capture->getRegion(0, 0, 20, 5);  // 20x5 region as array of lines
$capture->getNonEmptyLines();      // Line number => content

// Colors
$capture->getColorAt(5, 3);
$capture->hasColorAt(5, 3, "\033[1m");

// Dimensions
$capture->getSize();  // ['width' => 80, 'height' => 24]

// Debugging
$capture->dump();                // Visual representation
$capture->dumpWithLineNumbers(); // With line numbers
```

## Virtual Terminal Driver

```php
// Configuration
$this->terminal()->setSize(120, 40);

// Input queue
$this->terminal()->queueInput('UP', 'DOWN', 'ENTER');
$this->terminal()->queueFrameBoundary();  // Marks frame boundary
$this->terminal()->clearInput();

// Output
$this->terminal()->getOutput();     // Raw ANSI output
$this->terminal()->clearOutput();

// State
$this->terminal()->hasInput();
$this->terminal()->getRemainingInputCount();
$this->terminal()->isInitialized();
```
