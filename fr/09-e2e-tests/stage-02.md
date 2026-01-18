# Stage 2: Virtual Terminal Driver

## Objective

Create a virtual terminal driver that captures output and provides scripted input for testing purposes.

## Prerequisites

- Stage 1 completed (TerminalDriverInterface exists)
- Understanding of ANSI escape sequences

## Substeps

### 2.1: Create VirtualTerminalDriver Base

Create the virtual driver with configurable size.

**File**: `src/Infrastructure/Terminal/Driver/VirtualTerminalDriver.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Terminal\Driver;

use Butschster\Commander\Testing\ScreenCapture;
use Butschster\Commander\Testing\AnsiParser;

/**
 * Virtual terminal driver for testing.
 * 
 * Provides scripted input and captures output for verification.
 */
final class VirtualTerminalDriver implements TerminalDriverInterface
{
    private int $width;
    private int $height;
    
    /** @var list<string|null> Queued input keys (null = frame boundary) */
    private array $inputQueue = [];
    
    /** Raw output written to terminal */
    private string $outputBuffer = '';
    
    /** Whether initialize() was called */
    private bool $initialized = false;
    
    public function __construct(
        int $width = 80,
        int $height = 24,
    ) {
        $this->width = $width;
        $this->height = $height;
    }

    public function setSize(int $width, int $height): void
    {
        $this->width = $width;
        $this->height = $height;
    }

    public function getSize(): array
    {
        return ['width' => $this->width, 'height' => $this->height];
    }

    public function readInput(): ?string
    {
        if (empty($this->inputQueue)) {
            return null;
        }

        return \array_shift($this->inputQueue);
    }

    public function hasInput(): bool
    {
        return !empty($this->inputQueue);
    }

    public function write(string $output): void
    {
        $this->outputBuffer .= $output;
    }

    public function initialize(): void
    {
        $this->initialized = true;
        $this->outputBuffer = '';
    }

    public function cleanup(): void
    {
        $this->initialized = false;
    }

    public function isInteractive(): bool
    {
        return false; // Virtual terminal is never "interactive"
    }

    // === Test-specific methods ===

    /**
     * Queue input keys to be returned by readInput().
     * 
     * @param string ...$keys Key names: 'UP', 'ENTER', 'F10', 'CTRL_C', etc.
     */
    public function queueInput(string ...$keys): void
    {
        foreach ($keys as $key) {
            $this->inputQueue[] = $key;
        }
    }

    /**
     * Queue a frame boundary marker.
     * When readInput() returns null, it signals end of current frame's input.
     */
    public function queueFrameBoundary(): void
    {
        $this->inputQueue[] = null;
    }

    /**
     * Get raw output buffer.
     */
    public function getOutput(): string
    {
        return $this->outputBuffer;
    }

    /**
     * Clear output buffer.
     */
    public function clearOutput(): void
    {
        $this->outputBuffer = '';
    }

    /**
     * Check if driver was initialized.
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Get remaining input count.
     */
    public function getRemainingInputCount(): int
    {
        return \count($this->inputQueue);
    }

    /**
     * Parse ANSI output and return screen capture.
     */
    public function getScreenCapture(): ScreenCapture
    {
        $parser = new AnsiParser($this->width, $this->height);
        return $parser->parse($this->outputBuffer);
    }
}
```

### 2.2: Implement Input Queue Methods

Already included in 2.1. Additional convenience methods:

```php
/**
 * Queue multiple key sequences at once.
 * 
 * @param array<string|array<string>> $sequence
 */
public function queueSequence(array $sequence): void
{
    foreach ($sequence as $item) {
        if (\is_array($item)) {
            // Array of keys for same frame
            foreach ($item as $key) {
                $this->inputQueue[] = $key;
            }
        } else {
            $this->inputQueue[] = $item;
        }
    }
}

/**
 * Clear all queued input.
 */
public function clearInput(): void
{
    $this->inputQueue = [];
}
```

### 2.3: Implement Output Capture

Already included in 2.1. The output buffer captures all ANSI sequences.

### 2.4: Create AnsiParser

Parse ANSI escape sequences into a screen buffer.

**File**: `tests/Testing/AnsiParser.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Testing;

/**
 * Parses ANSI terminal output into a screen buffer.
 * 
 * Handles:
 * - Cursor movement (CSI H, CSI A/B/C/D)
 * - Colors (CSI ...m)
 * - Clear operations (CSI 2J, CSI K)
 * - Text output
 */
final class AnsiParser
{
    /** @var array<int, array<int, array{char: string, color: string}>> */
    private array $buffer = [];
    
    private int $cursorX = 0;
    private int $cursorY = 0;
    private string $currentColor = '';

    public function __construct(
        private readonly int $width,
        private readonly int $height,
    ) {
        $this->initBuffer();
    }

    public function parse(string $output): ScreenCapture
    {
        $this->initBuffer();
        $this->cursorX = 0;
        $this->cursorY = 0;
        $this->currentColor = '';

        $length = \strlen($output);
        $i = 0;

        while ($i < $length) {
            $char = $output[$i];

            // Check for escape sequence
            if ($char === "\033" && isset($output[$i + 1]) && $output[$i + 1] === '[') {
                $i = $this->parseEscapeSequence($output, $i);
                continue;
            }

            // Handle special characters
            if ($char === "\n") {
                $this->cursorY++;
                $this->cursorX = 0;
                $i++;
                continue;
            }

            if ($char === "\r") {
                $this->cursorX = 0;
                $i++;
                continue;
            }

            // Regular character - write to buffer
            $this->writeChar($char);
            $i++;
        }

        return new ScreenCapture($this->buffer, $this->width, $this->height);
    }

    private function parseEscapeSequence(string $output, int $start): int
    {
        // Skip ESC[
        $i = $start + 2;
        $params = '';
        $length = \strlen($output);

        // Collect parameters
        while ($i < $length) {
            $char = $output[$i];
            
            if (\ctype_digit($char) || $char === ';' || $char === '?') {
                $params .= $char;
                $i++;
                continue;
            }

            // Found command character
            $this->executeCommand($char, $params);
            return $i + 1;
        }

        return $i;
    }

    private function executeCommand(string $cmd, string $params): void
    {
        $parts = $params !== '' ? \explode(';', $params) : [];
        
        switch ($cmd) {
            case 'H': // Cursor position
            case 'f':
                $row = isset($parts[0]) && $parts[0] !== '' ? (int) $parts[0] : 1;
                $col = isset($parts[1]) && $parts[1] !== '' ? (int) $parts[1] : 1;
                $this->cursorY = \max(0, \min($this->height - 1, $row - 1));
                $this->cursorX = \max(0, \min($this->width - 1, $col - 1));
                break;

            case 'A': // Cursor up
                $n = isset($parts[0]) && $parts[0] !== '' ? (int) $parts[0] : 1;
                $this->cursorY = \max(0, $this->cursorY - $n);
                break;

            case 'B': // Cursor down
                $n = isset($parts[0]) && $parts[0] !== '' ? (int) $parts[0] : 1;
                $this->cursorY = \min($this->height - 1, $this->cursorY + $n);
                break;

            case 'C': // Cursor forward
                $n = isset($parts[0]) && $parts[0] !== '' ? (int) $parts[0] : 1;
                $this->cursorX = \min($this->width - 1, $this->cursorX + $n);
                break;

            case 'D': // Cursor back
                $n = isset($parts[0]) && $parts[0] !== '' ? (int) $parts[0] : 1;
                $this->cursorX = \max(0, $this->cursorX - $n);
                break;

            case 'J': // Clear screen
                $mode = isset($parts[0]) ? (int) $parts[0] : 0;
                if ($mode === 2) {
                    $this->initBuffer();
                    $this->cursorX = 0;
                    $this->cursorY = 0;
                }
                break;

            case 'K': // Clear line
                $mode = isset($parts[0]) ? (int) $parts[0] : 0;
                $this->clearLine($mode);
                break;

            case 'm': // SGR (colors/attributes)
                $this->currentColor = "\033[{$params}m";
                break;

            // Ignore other sequences (cursor show/hide, alternate screen, etc.)
            case 'h':
            case 'l':
                break;
        }
    }

    private function writeChar(string $char): void
    {
        if ($this->cursorY >= 0 && $this->cursorY < $this->height &&
            $this->cursorX >= 0 && $this->cursorX < $this->width) {
            
            $this->buffer[$this->cursorY][$this->cursorX] = [
                'char' => $char,
                'color' => $this->currentColor,
            ];
        }

        $this->cursorX++;
        
        // Wrap at end of line
        if ($this->cursorX >= $this->width) {
            $this->cursorX = 0;
            $this->cursorY++;
        }
    }

    private function clearLine(int $mode): void
    {
        $emptyCell = ['char' => ' ', 'color' => ''];
        
        switch ($mode) {
            case 0: // Clear from cursor to end
                for ($x = $this->cursorX; $x < $this->width; $x++) {
                    $this->buffer[$this->cursorY][$x] = $emptyCell;
                }
                break;
            case 1: // Clear from start to cursor
                for ($x = 0; $x <= $this->cursorX; $x++) {
                    $this->buffer[$this->cursorY][$x] = $emptyCell;
                }
                break;
            case 2: // Clear entire line
                $this->buffer[$this->cursorY] = \array_fill(0, $this->width, $emptyCell);
                break;
        }
    }

    private function initBuffer(): void
    {
        $emptyCell = ['char' => ' ', 'color' => ''];
        
        for ($y = 0; $y < $this->height; $y++) {
            $this->buffer[$y] = \array_fill(0, $this->width, $emptyCell);
        }
    }
}
```

### 2.5: Implement getScreenCapture()

Already included in VirtualTerminalDriver. Returns parsed ScreenCapture.

### 2.6: Unit Tests for VirtualTerminalDriver

**File**: `tests/Unit/Infrastructure/Terminal/Driver/VirtualTerminalDriverTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Terminal\Driver;

use Butschster\Commander\Infrastructure\Terminal\Driver\VirtualTerminalDriver;
use PHPUnit\Framework\TestCase;

final class VirtualTerminalDriverTest extends TestCase
{
    public function testDefaultSize(): void
    {
        $driver = new VirtualTerminalDriver();
        
        $this->assertSame(['width' => 80, 'height' => 24], $driver->getSize());
    }

    public function testCustomSize(): void
    {
        $driver = new VirtualTerminalDriver(120, 40);
        
        $this->assertSame(['width' => 120, 'height' => 40], $driver->getSize());
    }

    public function testSetSize(): void
    {
        $driver = new VirtualTerminalDriver();
        $driver->setSize(100, 30);
        
        $this->assertSame(['width' => 100, 'height' => 30], $driver->getSize());
    }

    public function testQueueAndReadInput(): void
    {
        $driver = new VirtualTerminalDriver();
        
        $driver->queueInput('UP', 'DOWN', 'ENTER');
        
        $this->assertTrue($driver->hasInput());
        $this->assertSame('UP', $driver->readInput());
        $this->assertSame('DOWN', $driver->readInput());
        $this->assertSame('ENTER', $driver->readInput());
        $this->assertFalse($driver->hasInput());
        $this->assertNull($driver->readInput());
    }

    public function testFrameBoundary(): void
    {
        $driver = new VirtualTerminalDriver();
        
        $driver->queueInput('UP');
        $driver->queueFrameBoundary();
        $driver->queueInput('DOWN');
        
        $this->assertSame('UP', $driver->readInput());
        $this->assertNull($driver->readInput()); // Frame boundary
        $this->assertSame('DOWN', $driver->readInput());
    }

    public function testOutputCapture(): void
    {
        $driver = new VirtualTerminalDriver();
        
        $driver->write('Hello ');
        $driver->write('World');
        
        $this->assertSame('Hello World', $driver->getOutput());
    }

    public function testClearOutput(): void
    {
        $driver = new VirtualTerminalDriver();
        
        $driver->write('Test');
        $driver->clearOutput();
        
        $this->assertSame('', $driver->getOutput());
    }

    public function testInitializeAndCleanup(): void
    {
        $driver = new VirtualTerminalDriver();
        
        $this->assertFalse($driver->isInitialized());
        
        $driver->initialize();
        $this->assertTrue($driver->isInitialized());
        $this->assertSame('', $driver->getOutput()); // Cleared on init
        
        $driver->cleanup();
        $this->assertFalse($driver->isInitialized());
    }

    public function testIsNotInteractive(): void
    {
        $driver = new VirtualTerminalDriver();
        
        $this->assertFalse($driver->isInteractive());
    }

    public function testScreenCapture(): void
    {
        $driver = new VirtualTerminalDriver(10, 3);
        
        // Simulate simple output: position cursor and write
        $driver->write("\033[1;1H"); // Move to (0,0)
        $driver->write("Hello");
        $driver->write("\033[2;1H"); // Move to (0,1)
        $driver->write("World");
        
        $capture = $driver->getScreenCapture();
        
        $this->assertTrue($capture->contains('Hello'));
        $this->assertTrue($capture->contains('World'));
        $this->assertSame('Hello', \trim($capture->getLine(0)));
        $this->assertSame('World', \trim($capture->getLine(1)));
    }
}
```

## Verification

After completing this stage:

1. VirtualTerminalDriver can queue and read input
2. Output is captured correctly
3. Frame boundaries work
4. ANSI parser handles basic sequences
5. ScreenCapture returns correct content
6. All unit tests pass

## Files Created

| File                                                                      | Description        |
|---------------------------------------------------------------------------|--------------------|
| `src/Infrastructure/Terminal/Driver/VirtualTerminalDriver.php`            | Virtual driver     |
| `tests/Testing/AnsiParser.php`                                            | ANSI output parser |
| `tests/Unit/Infrastructure/Terminal/Driver/VirtualTerminalDriverTest.php` | Unit tests         |

## Notes

- AnsiParser handles common sequences; edge cases can be added as discovered
- Frame boundaries allow tests to process input in batches
- Screen capture parsing is lazy (only when `getScreenCapture()` called)
