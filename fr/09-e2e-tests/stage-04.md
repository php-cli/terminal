# Stage 4: Screen Assertions & Helpers

## Objective

Extend TerminalTestCase with comprehensive assertions for screen content, layout, and visual state verification.

## Prerequisites

- Stage 3 completed (TerminalTestCase exists)
- Basic assertions working

## Substeps

### 4.1: Implement assertScreenContains / assertScreenNotContains

Already implemented in Stage 3. Enhancements:

**File**: `tests/TerminalTestCase.php` (additions)

```php
/**
 * Assert screen contains text at least N times.
 */
protected function assertScreenContainsCount(string $text, int $count, string $message = ''): void
{
    $capture = $this->capture();
    $found = $capture->findAllText($text);
    
    $this->assertCount(
        $count,
        $found,
        $message ?: "Screen should contain '{$text}' exactly {$count} times, found " . \count($found) . ".\n" . $capture->dump()
    );
}

/**
 * Assert screen contains all specified texts.
 */
protected function assertScreenContainsAll(array $texts, string $message = ''): void
{
    $capture = $this->capture();
    $missing = [];
    
    foreach ($texts as $text) {
        if (!$capture->contains($text)) {
            $missing[] = $text;
        }
    }
    
    $this->assertEmpty(
        $missing,
        $message ?: "Screen should contain all texts. Missing: " . \implode(', ', $missing) . "\n" . $capture->dump()
    );
}

/**
 * Assert screen contains at least one of the specified texts.
 */
protected function assertScreenContainsAny(array $texts, string $message = ''): void
{
    $capture = $this->capture();
    
    foreach ($texts as $text) {
        if ($capture->contains($text)) {
            return; // Found at least one
        }
    }
    
    $this->fail(
        $message ?: "Screen should contain at least one of: " . \implode(', ', $texts) . "\n" . $capture->dump()
    );
}
```

### 4.2: Implement assertTextAt / assertLineContains

Already implemented in Stage 3. Enhancements:

```php
/**
 * Assert line equals exactly (trimmed).
 */
protected function assertLineEquals(int $y, string $expected, string $message = ''): void
{
    $capture = $this->capture();
    $actual = $capture->getLineTrimmed($y);
    
    $this->assertSame(
        $expected,
        $actual,
        $message ?: "Line {$y} should equal '{$expected}', got '{$actual}'.\n" . $capture->dump()
    );
}

/**
 * Assert line starts with text.
 */
protected function assertLineStartsWith(int $y, string $prefix, string $message = ''): void
{
    $capture = $this->capture();
    $line = $capture->getLineTrimmed($y);
    
    $this->assertStringStartsWith(
        $prefix,
        $line,
        $message ?: "Line {$y} should start with '{$prefix}'.\n" . $capture->dump()
    );
}

/**
 * Assert line is empty (only spaces).
 */
protected function assertLineEmpty(int $y, string $message = ''): void
{
    $capture = $this->capture();
    $line = $capture->getLineTrimmed($y);
    
    $this->assertSame(
        '',
        $line,
        $message ?: "Line {$y} should be empty.\n" . $capture->dump()
    );
}

/**
 * Assert text appears at specific line (anywhere on that line).
 */
protected function assertTextOnLine(int $y, string $text, string $message = ''): void
{
    $capture = $this->capture();
    $line = $capture->getLine($y);
    
    $this->assertStringContainsString(
        $text,
        $line,
        $message ?: "Text '{$text}' should appear on line {$y}.\n" . $capture->dump()
    );
}

/**
 * Assert region content (rectangular area).
 */
protected function assertRegion(int $x, int $y, int $width, int $height, array $expected, string $message = ''): void
{
    $capture = $this->capture();
    $actual = $capture->getRegion($x, $y, $width, $height);
    
    $this->assertSame(
        $expected,
        $actual,
        $message ?: "Region at ({$x},{$y}) {$width}x{$height} doesn't match.\n" . $capture->dump()
    );
}
```

### 4.3: Implement assertCurrentScreen / assertScreenDepth

Already implemented in Stage 3. Enhancements:

```php
/**
 * Assert previous screen in stack.
 */
protected function assertPreviousScreen(string $screenClass, string $message = ''): void
{
    $this->assertNotNull($this->app, 'Application not initialized');
    
    $stack = $this->app->getScreenManager()->getStack();
    
    $this->assertGreaterThanOrEqual(2, \count($stack), 'Need at least 2 screens in stack');
    
    $previous = $stack[\count($stack) - 2];
    
    $this->assertInstanceOf(
        $screenClass,
        $previous,
        $message ?: "Previous screen should be {$screenClass}"
    );
}

/**
 * Assert screen stack contains specific screens (from bottom to top).
 */
protected function assertScreenStack(array $screenClasses, string $message = ''): void
{
    $this->assertNotNull($this->app, 'Application not initialized');
    
    $stack = $this->app->getScreenManager()->getStack();
    
    $this->assertSameSize($screenClasses, $stack, 'Screen stack size mismatch');
    
    foreach ($screenClasses as $i => $class) {
        $this->assertInstanceOf(
            $class,
            $stack[$i],
            $message ?: "Screen at position {$i} should be {$class}"
        );
    }
}

/**
 * Assert screen title.
 */
protected function assertScreenTitle(string $expected, string $message = ''): void
{
    $this->assertNotNull($this->app, 'Application not initialized');
    
    $current = $this->app->getScreenManager()->getCurrentScreen();
    $this->assertNotNull($current, 'No current screen');
    
    $this->assertSame(
        $expected,
        $current->getTitle(),
        $message ?: "Screen title should be '{$expected}'"
    );
}
```

### 4.4: Implement ScreenCapture::dump() for Debugging

Already implemented in Stage 3 (ScreenCapture.php).

Additional debugging helpers in TerminalTestCase:

```php
/**
 * Dump current screen to output (for debugging tests).
 */
protected function dumpScreen(): void
{
    echo "\n" . $this->capture()->dump() . "\n";
}

/**
 * Dump screen with line numbers.
 */
protected function dumpScreenWithLines(): void
{
    echo "\n" . $this->capture()->dumpWithLineNumbers() . "\n";
}

/**
 * Dump only non-empty lines (more compact output).
 */
protected function dumpNonEmptyLines(): void
{
    $capture = $this->capture();
    $lines = $capture->getNonEmptyLines();
    
    echo "\nNon-empty lines:\n";
    foreach ($lines as $y => $content) {
        echo \sprintf("%3d: %s\n", $y, $content);
    }
    echo "\n";
}

/**
 * Debug helper: pause and show screen state.
 * Use during test development.
 */
protected function pauseAndShow(string $label = ''): void
{
    if ($label !== '') {
        echo "\n=== {$label} ===\n";
    }
    $this->dumpScreenWithLines();
}
```

### 4.5: Color Assertion Helpers

```php
/**
 * Assert position has specific color code.
 */
protected function assertColorAt(int $x, int $y, string $expectedColor, string $message = ''): void
{
    $capture = $this->capture();
    $actual = $capture->getColorAt($x, $y);
    
    $this->assertSame(
        $expectedColor,
        $actual,
        $message ?: "Color at ({$x},{$y}) should be '{$expectedColor}', got '{$actual}'"
    );
}

/**
 * Assert that a specific text has specific color.
 */
protected function assertTextHasColor(string $text, string $expectedColor, string $message = ''): void
{
    $capture = $this->capture();
    $pos = $capture->findText($text);
    
    $this->assertNotNull($pos, "Text '{$text}' not found on screen");
    
    $actual = $capture->getColorAt($pos['x'], $pos['y']);
    
    $this->assertSame(
        $expectedColor,
        $actual,
        $message ?: "Text '{$text}' should have color '{$expectedColor}'"
    );
}

/**
 * Assert line has consistent color (all same color).
 * Useful for checking highlighted/selected lines.
 */
protected function assertLineHasUniformColor(int $y, string $expectedColor, string $message = ''): void
{
    $capture = $this->capture();
    $size = $capture->getSize();
    $line = $capture->getLineTrimmed($y);
    
    // Find first and last non-space character
    $start = \strlen($line) - \strlen(\ltrim($line));
    $end = \strlen(\rtrim($line)) - 1;
    
    for ($x = $start; $x <= $end; $x++) {
        $actual = $capture->getColorAt($x, $y);
        if ($actual !== $expectedColor) {
            $this->fail(
                $message ?: "Line {$y} should have uniform color '{$expectedColor}', but position {$x} has '{$actual}'"
            );
        }
    }
    
    $this->assertTrue(true); // All positions matched
}
```

### 4.6: Document Assertion API with Examples

**File**: `tests/Testing/README.md`

```markdown
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
$this->assertPreviousScreen(MainMenuScreen::class);
$this->assertScreenStack([MainMenuScreen::class, FileBrowserScreen::class]);
$this->assertScreenTitle('File Browser');
$this->assertScreenDepth(2);
```

### Color Assertions

```php
$this->assertColorAt(0, 0, "\033[1;37m");
$this->assertTextHasColor('Error', "\033[31m");
$this->assertLineHasUniformColor(5, "\033[7m");  // Inverted (selected)
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
$capture->getLine(5);              // Full line 5
$capture->getLineTrimmed(5);       // Line 5, trimmed
$capture->getRegion(0, 0, 20, 5);  // 20x5 region as array of lines
$capture->getNonEmptyLines();      // Line number => content

// Colors
$capture->getColorAt(5, 3);
$capture->hasColorAt(5, 3, "\033[1m");

// Dimensions
$capture->getSize();  // ['width' => 80, 'height' => 24]
```

```

## Verification

After completing this stage:

1. All assertion methods work correctly
2. Failure messages include screen dump for debugging
3. Color assertions work with ANSI codes
4. Documentation is complete and accurate
5. Example tests demonstrate usage

## Files Modified

| File | Changes |
|------|---------|
| `tests/TerminalTestCase.php` | Extended assertions |
| `tests/Testing/ScreenCapture.php` | Additional query methods |
| `tests/Testing/README.md` | New - documentation |

## Notes

- All assertions include screen dump on failure
- Color assertions work with raw ANSI escape codes
- Documentation serves as living spec for the testing API
