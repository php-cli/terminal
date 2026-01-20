# Stage 1: Terminal Driver Abstraction

## Objective

Extract terminal I/O operations into a driver interface, enabling dependency injection of different drivers (real vs
virtual).

## Prerequisites

- Understanding of current `TerminalManager`, `KeyboardHandler`, and `Renderer` classes
- No blocking dependencies from other stages

## Substeps

### 1.1: Create TerminalDriverInterface

Create the driver contract that abstracts all terminal I/O operations.

**File**: `src/Infrastructure/Terminal/Driver/TerminalDriverInterface.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Terminal\Driver;

/**
 * Abstraction for terminal I/O operations.
 * 
 * Allows swapping real terminal with virtual terminal for testing.
 */
interface TerminalDriverInterface
{
    /**
     * Get terminal dimensions.
     * 
     * @return array{width: int, height: int}
     */
    public function getSize(): array;

    /**
     * Read next input character/escape sequence (non-blocking).
     * 
     * @return string|null Raw input or null if no input available
     */
    public function readInput(): ?string;

    /**
     * Check if input is available without blocking.
     */
    public function hasInput(): bool;

    /**
     * Write raw output to terminal.
     */
    public function write(string $output): void;

    /**
     * Initialize terminal for application use.
     * (raw mode, alternate screen, hide cursor, etc.)
     */
    public function initialize(): void;

    /**
     * Cleanup and restore terminal to original state.
     */
    public function cleanup(): void;

    /**
     * Check if this is an interactive terminal.
     * Returns false for pipes, files, or virtual drivers.
     */
    public function isInteractive(): bool;
}
```

### 1.2: Create RealTerminalDriver

Extract logic from `TerminalManager` and `KeyboardHandler` into the real driver.

**File**: `src/Infrastructure/Terminal/Driver/RealTerminalDriver.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Terminal\Driver;

/**
 * Real terminal driver using STDIN/STDOUT and shell commands.
 */
final class RealTerminalDriver implements TerminalDriverInterface
{
    /** @var resource */
    private $stdin;
    
    private bool $rawModeEnabled = false;
    private bool $nonBlockingEnabled = false;
    private ?string $originalTerminalSettings = null;

    public function __construct()
    {
        $this->stdin = STDIN;
    }

    public function getSize(): array
    {
        // Try stty first
        $output = [];
        \exec('stty size 2>/dev/null', $output, $returnCode);

        if ($returnCode === 0 && !empty($output[0])) {
            [$height, $width] = \sscanf($output[0], '%d %d');
            if ($height && $width) {
                return ['width' => (int) $width, 'height' => (int) $height];
            }
        }

        // Fallback to tput
        $width = (int) \exec('tput cols 2>/dev/null') ?: 80;
        $height = (int) \exec('tput lines 2>/dev/null') ?: 24;

        return ['width' => $width, 'height' => $height];
    }

    public function readInput(): ?string
    {
        if (!$this->nonBlockingEnabled) {
            \stream_set_blocking($this->stdin, false);
            $this->nonBlockingEnabled = true;
        }

        $char = \fread($this->stdin, 1);

        if ($char === false || $char === '') {
            return null;
        }

        // Return raw character - escape sequence parsing happens in KeyboardHandler
        return $char;
    }

    public function hasInput(): bool
    {
        $read = [$this->stdin];
        $write = null;
        $except = null;

        return \stream_select($read, $write, $except, 0, 0) > 0;
    }

    public function write(string $output): void
    {
        echo $output;
        \flush();
    }

    public function initialize(): void
    {
        $this->enableRawMode();
        $this->write("\033[?1049h"); // Enter alternate screen
        $this->write("\033[?25l");   // Hide cursor
        $this->write("\033[2J\033[H"); // Clear screen
    }

    public function cleanup(): void
    {
        $this->write("\033[0m");     // Reset attributes
        $this->write("\033[?25h");   // Show cursor
        $this->write("\033[?1049l"); // Exit alternate screen
        $this->disableRawMode();
    }

    public function isInteractive(): bool
    {
        return \posix_isatty($this->stdin);
    }

    private function enableRawMode(): void
    {
        if ($this->rawModeEnabled) {
            return;
        }

        $this->originalTerminalSettings = \shell_exec('stty -g 2>/dev/null');
        \shell_exec('stty -icanon -echo -isig 2>/dev/null');
        $this->rawModeEnabled = true;
    }

    private function disableRawMode(): void
    {
        if (!$this->rawModeEnabled) {
            return;
        }

        if ($this->originalTerminalSettings !== null) {
            \shell_exec("stty {$this->originalTerminalSettings} 2>/dev/null");
        }

        if ($this->nonBlockingEnabled) {
            \stream_set_blocking($this->stdin, true);
            $this->nonBlockingEnabled = false;
        }

        $this->rawModeEnabled = false;
    }
}
```

### 1.3: Refactor TerminalManager to Use Driver

Update `TerminalManager` to delegate to driver while maintaining backward compatibility.

**Changes to**: `src/Infrastructure/Terminal/TerminalManager.php`

```php
// Add driver property and constructor injection
public function __construct(
    private readonly ?TerminalDriverInterface $driver = null,
) {
    // Driver is optional - if not provided, use internal implementation (BC)
}

// Modify getSize() to use driver if available
public function getSize(): array
{
    if ($this->driver !== null) {
        return $this->driver->getSize();
    }
    // ... existing implementation
}

// Modify initialize() to use driver if available
public function initialize(): void
{
    if ($this->driver !== null) {
        $this->driver->initialize();
        return;
    }
    // ... existing implementation
}

// Modify cleanup() to use driver if available  
public function cleanup(): void
{
    if ($this->driver !== null) {
        $this->driver->cleanup();
        return;
    }
    // ... existing implementation
}
```

### 1.4: Refactor KeyboardHandler to Use Driver

Update `KeyboardHandler` to accept driver for input reading.

**Changes to**: `src/Infrastructure/Terminal/KeyboardHandler.php`

```php
// Add driver property
public function __construct(
    private readonly KeyMappingRegistry $mappings = new KeyMappingRegistry(),
    private readonly ?TerminalDriverInterface $driver = null,
) {
    $this->stdin = STDIN; // Keep for BC when no driver
}

// Modify internal read method to use driver
private function readChar(): ?string
{
    if ($this->driver !== null) {
        return $this->driver->readInput();
    }
    
    // ... existing STDIN reading logic
}

// Update hasInput() to use driver
public function hasInput(): bool
{
    if ($this->driver !== null) {
        return $this->driver->hasInput();
    }
    
    // ... existing implementation
}
```

### 1.5: Refactor Renderer to Use Driver

Update `Renderer` to use driver for output.

**Changes to**: `src/Infrastructure/Terminal/Renderer.php`

```php
public function __construct(
    private readonly TerminalManager $terminal,
    private readonly ThemeContext $themeContext,
    private readonly ?TerminalDriverInterface $driver = null,
) {
    // ... existing initialization
}

// Modify endFrame() to use driver for output
public function endFrame(): void
{
    // ... build output string ...
    
    if ($output !== '') {
        if ($this->driver !== null) {
            $this->driver->write($output);
        } else {
            echo $output;
            \flush();
        }
    }
}
```

### 1.6: Update Application to Wire Driver

Update `Application` to accept and propagate driver to all components.

**Changes to**: `src/Application.php`

```php
public function __construct(
    ?KeyBindingRegistryInterface $keyBindings = null,
    private readonly ?TerminalDriverInterface $driver = null,
) {
    // ... existing code ...
    
    // Pass driver to components
    $this->terminal = new TerminalManager($this->driver);
    $this->renderer = new Renderer($this->terminal, $this->themeContext, $this->driver);
    $this->keyboard = new KeyboardHandler(driver: $this->driver);
    
    // ... rest of initialization
}
```

## Verification

After completing this stage:

1. Run existing application - should work unchanged (BC preserved)
2. Verify no regressions in terminal behavior
3. Application initializes with default (null) driver
4. Terminal size detection works
5. Keyboard input works
6. Screen rendering works

## Files Changed

| File                                                             | Type     | Description               |
|------------------------------------------------------------------|----------|---------------------------|
| `src/Infrastructure/Terminal/Driver/TerminalDriverInterface.php` | New      | Driver contract           |
| `src/Infrastructure/Terminal/Driver/RealTerminalDriver.php`      | New      | Production driver         |
| `src/Infrastructure/Terminal/TerminalManager.php`                | Modified | Add driver injection      |
| `src/Infrastructure/Terminal/KeyboardHandler.php`                | Modified | Add driver injection      |
| `src/Infrastructure/Terminal/Renderer.php`                       | Modified | Add driver injection      |
| `src/Application.php`                                            | Modified | Wire driver to components |

## Notes

- Keep all existing public APIs unchanged
- Driver is optional with null default for backward compatibility
- RealTerminalDriver consolidates I/O logic currently spread across multiple classes
