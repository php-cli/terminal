# Stage 3: Test Infrastructure

## Objective

Create PHPUnit configuration and base test classes that make writing terminal tests easy and consistent.

## Prerequisites

- Stage 1 & 2 completed
- PHPUnit already in composer.json (require-dev)

## Substeps

### 3.1: Create phpunit.xml Configuration

**File**: `phpunit.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true"
         stopOnFailure="false"
         cacheDirectory=".build/phpunit/cache"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
        <testsuite name="E2E">
            <directory>tests/E2E</directory>
        </testsuite>
    </testsuites>

    <coverage>
        <report>
            <clover outputFile=".build/phpunit/logs/clover.xml"/>
            <html outputDirectory=".build/phpunit/coverage"/>
        </report>
    </coverage>

    <source>
        <include>
            <directory suffix=".php">src</directory>
        </include>
        <exclude>
            <directory>src/Feature</directory>
        </exclude>
    </source>

    <php>
        <env name="APP_ENV" value="testing"/>
    </php>
</phpunit>
```

### 3.2: Create Base TestCase

**File**: `tests/TestCase.php`

```php
<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case with common utilities.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Assert that array has exact keys.
     *
     * @param array<string> $expectedKeys
     * @param array<mixed> $actual
     */
    protected function assertArrayHasExactKeys(array $expectedKeys, array $actual, string $message = ''): void
    {
        $actualKeys = \array_keys($actual);
        \sort($expectedKeys);
        \sort($actualKeys);
        
        $this->assertSame($expectedKeys, $actualKeys, $message ?: 'Array keys do not match expected');
    }

    /**
     * Get private/protected property value for testing.
     */
    protected function getPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    /**
     * Set private/protected property value for testing.
     */
    protected function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
}
```

### 3.3: Create ScreenCapture Value Object

**File**: `tests/Testing/ScreenCapture.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Testing;

/**
 * Captured terminal screen state for assertions.
 * 
 * Immutable snapshot of screen content at a point in time.
 */
final readonly class ScreenCapture
{
    /**
     * @param array<int, array<int, array{char: string, color: string}>> $buffer
     */
    public function __construct(
        private array $buffer,
        private int $width,
        private int $height,
    ) {}

    /**
     * Get text at specific position.
     */
    public function getText(int $x, int $y, int $length): string
    {
        if ($y < 0 || $y >= $this->height) {
            return '';
        }

        $text = '';
        for ($i = 0; $i < $length; $i++) {
            $posX = $x + $i;
            if ($posX >= 0 && $posX < $this->width) {
                $text .= $this->buffer[$y][$posX]['char'];
            }
        }

        return $text;
    }

    /**
     * Get entire line.
     */
    public function getLine(int $y): string
    {
        if ($y < 0 || $y >= $this->height) {
            return '';
        }

        $line = '';
        foreach ($this->buffer[$y] as $cell) {
            $line .= $cell['char'];
        }

        return $line;
    }

    /**
     * Get line with trailing spaces trimmed.
     */
    public function getLineTrimmed(int $y): string
    {
        return \rtrim($this->getLine($y));
    }

    /**
     * Check if screen contains text anywhere.
     */
    public function contains(string $text): bool
    {
        return $this->findText($text) !== null;
    }

    /**
     * Find text position on screen.
     * 
     * @return array{x: int, y: int}|null
     */
    public function findText(string $text): ?array
    {
        $textLength = \mb_strlen($text);
        
        for ($y = 0; $y < $this->height; $y++) {
            $line = $this->getLine($y);
            $pos = \mb_strpos($line, $text);
            
            if ($pos !== false) {
                return ['x' => $pos, 'y' => $y];
            }
        }

        return null;
    }

    /**
     * Find all occurrences of text.
     * 
     * @return array<array{x: int, y: int}>
     */
    public function findAllText(string $text): array
    {
        $results = [];
        $textLength = \mb_strlen($text);
        
        for ($y = 0; $y < $this->height; $y++) {
            $line = $this->getLine($y);
            $offset = 0;
            
            while (($pos = \mb_strpos($line, $text, $offset)) !== false) {
                $results[] = ['x' => $pos, 'y' => $y];
                $offset = $pos + $textLength;
            }
        }

        return $results;
    }

    /**
     * Get rectangular region as array of lines.
     * 
     * @return array<string>
     */
    public function getRegion(int $x, int $y, int $width, int $height): array
    {
        $lines = [];
        
        for ($row = 0; $row < $height; $row++) {
            $lines[] = $this->getText($x, $y + $row, $width);
        }

        return $lines;
    }

    /**
     * Get color at specific position.
     */
    public function getColorAt(int $x, int $y): string
    {
        if ($y < 0 || $y >= $this->height || $x < 0 || $x >= $this->width) {
            return '';
        }

        return $this->buffer[$y][$x]['color'];
    }

    /**
     * Check if position has specific color code.
     */
    public function hasColorAt(int $x, int $y, string $expectedColor): bool
    {
        return $this->getColorAt($x, $y) === $expectedColor;
    }

    /**
     * Get screen dimensions.
     * 
     * @return array{width: int, height: int}
     */
    public function getSize(): array
    {
        return ['width' => $this->width, 'height' => $this->height];
    }

    /**
     * Dump screen content to string for debugging.
     */
    public function dump(): string
    {
        $output = "Screen ({$this->width}x{$this->height}):\n";
        $output .= \str_repeat('─', $this->width + 2) . "\n";
        
        for ($y = 0; $y < $this->height; $y++) {
            $output .= '│' . $this->getLine($y) . "│\n";
        }
        
        $output .= \str_repeat('─', $this->width + 2);
        
        return $output;
    }

    /**
     * Dump with line numbers (useful for debugging).
     */
    public function dumpWithLineNumbers(): string
    {
        $output = "Screen ({$this->width}x{$this->height}):\n";
        $lineNumWidth = \strlen((string) $this->height);
        
        for ($y = 0; $y < $this->height; $y++) {
            $lineNum = \str_pad((string) $y, $lineNumWidth, ' ', STR_PAD_LEFT);
            $output .= "{$lineNum}│{$this->getLine($y)}│\n";
        }
        
        return $output;
    }

    /**
     * Get all non-empty lines (trimmed).
     * 
     * @return array<int, string> Line number => content
     */
    public function getNonEmptyLines(): array
    {
        $result = [];
        
        for ($y = 0; $y < $this->height; $y++) {
            $line = $this->getLineTrimmed($y);
            if ($line !== '') {
                $result[$y] = $line;
            }
        }

        return $result;
    }
}
```

### 3.4: Create ScriptedKeySequence Builder

**File**: `tests/Testing/ScriptedKeySequence.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Testing;

use Butschster\Commander\Infrastructure\Terminal\Driver\VirtualTerminalDriver;

/**
 * Fluent builder for creating key input sequences.
 */
final class ScriptedKeySequence
{
    /** @var array<string|null> */
    private array $sequence = [];

    /**
     * Add a single key press.
     */
    public function press(string $key): self
    {
        $this->sequence[] = \strtoupper($key);
        return $this;
    }

    /**
     * Add Ctrl+key combination.
     */
    public function ctrl(string $key): self
    {
        $this->sequence[] = 'CTRL_' . \strtoupper($key);
        return $this;
    }

    /**
     * Add Alt+key combination.
     */
    public function alt(string $key): self
    {
        $this->sequence[] = 'ALT_' . \strtoupper($key);
        return $this;
    }

    /**
     * Add Shift+key combination.
     */
    public function shift(string $key): self
    {
        $this->sequence[] = 'SHIFT_' . \strtoupper($key);
        return $this;
    }

    /**
     * Type a string (each character becomes a key press).
     */
    public function type(string $text): self
    {
        foreach (\mb_str_split($text) as $char) {
            // Handle special characters
            if ($char === ' ') {
                $this->sequence[] = ' '; // Space
            } elseif ($char === "\n") {
                $this->sequence[] = 'ENTER';
            } elseif ($char === "\t") {
                $this->sequence[] = 'TAB';
            } else {
                $this->sequence[] = $char; // Regular character
            }
        }
        return $this;
    }

    /**
     * Repeat a key N times.
     */
    public function repeat(string $key, int $times): self
    {
        $key = \strtoupper($key);
        for ($i = 0; $i < $times; $i++) {
            $this->sequence[] = $key;
        }
        return $this;
    }

    /**
     * Add a frame boundary (signals app to process all previous input).
     * 
     * When the driver returns null from readInput(), it means
     * "no more input for this frame" - the app processes what it has
     * and renders a frame.
     */
    public function frame(): self
    {
        $this->sequence[] = null;
        return $this;
    }

    /**
     * Add navigation keys.
     */
    public function up(int $times = 1): self
    {
        return $this->repeat('UP', $times);
    }

    public function down(int $times = 1): self
    {
        return $this->repeat('DOWN', $times);
    }

    public function left(int $times = 1): self
    {
        return $this->repeat('LEFT', $times);
    }

    public function right(int $times = 1): self
    {
        return $this->repeat('RIGHT', $times);
    }

    /**
     * Press Enter.
     */
    public function enter(): self
    {
        return $this->press('ENTER');
    }

    /**
     * Press Escape.
     */
    public function escape(): self
    {
        return $this->press('ESCAPE');
    }

    /**
     * Press Tab.
     */
    public function tab(int $times = 1): self
    {
        return $this->repeat('TAB', $times);
    }

    /**
     * Press a function key (F1-F12).
     */
    public function fn(int $number): self
    {
        if ($number < 1 || $number > 12) {
            throw new \InvalidArgumentException('Function key must be F1-F12');
        }
        return $this->press("F{$number}");
    }

    /**
     * Build the sequence array.
     * 
     * @return array<string|null>
     */
    public function build(): array
    {
        return $this->sequence;
    }

    /**
     * Apply sequence directly to a virtual driver.
     */
    public function applyTo(VirtualTerminalDriver $driver): void
    {
        foreach ($this->sequence as $key) {
            if ($key === null) {
                $driver->queueFrameBoundary();
            } else {
                $driver->queueInput($key);
            }
        }
    }

    /**
     * Reset the sequence.
     */
    public function reset(): self
    {
        $this->sequence = [];
        return $this;
    }

    /**
     * Get current sequence length.
     */
    public function count(): int
    {
        return \count($this->sequence);
    }
}
```

### 3.5: Create TerminalTestCase

**File**: `tests/TerminalTestCase.php`

```php
<?php

declare(strict_types=1);

namespace Tests;

use Butschster\Commander\Application;
use Butschster\Commander\Infrastructure\Terminal\Driver\VirtualTerminalDriver;
use Butschster\Commander\Testing\ScriptedKeySequence;
use Butschster\Commander\Testing\ScreenCapture;
use Butschster\Commander\UI\Screen\ScreenInterface;

/**
 * Base test case for terminal UI tests.
 * 
 * Provides virtual terminal, key sequence building, and screen assertions.
 */
abstract class TerminalTestCase extends TestCase
{
    protected VirtualTerminalDriver $driver;
    protected ?Application $app = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new VirtualTerminalDriver();
    }

    protected function tearDown(): void
    {
        $this->app = null;
        parent::tearDown();
    }

    /**
     * Get the virtual terminal driver for configuration.
     */
    protected function terminal(): VirtualTerminalDriver
    {
        return $this->driver;
    }

    /**
     * Create a new key sequence builder.
     */
    protected function keys(): ScriptedKeySequence
    {
        return new ScriptedKeySequence();
    }

    /**
     * Create application with virtual driver.
     */
    protected function createApp(): Application
    {
        $this->app = new Application(driver: $this->driver);
        return $this->app;
    }

    /**
     * Run application with initial screen until input is exhausted.
     */
    protected function runApp(ScreenInterface $screen): void
    {
        $app = $this->createApp();
        
        // Run limited loop - process all queued input
        $this->runAppLoop($app, $screen);
    }

    /**
     * Run application until condition is met or max frames reached.
     * 
     * @param callable(ScreenCapture): bool $condition
     */
    protected function runUntil(
        ScreenInterface $screen,
        callable $condition,
        int $maxFrames = 100,
    ): void {
        $app = $this->createApp();
        
        $this->runAppLoopUntil($app, $screen, $condition, $maxFrames);
    }

    /**
     * Get current screen capture.
     */
    protected function capture(): ScreenCapture
    {
        return $this->driver->getScreenCapture();
    }

    // === Assertions ===

    /**
     * Assert that screen contains specific text.
     */
    protected function assertScreenContains(string $text, string $message = ''): void
    {
        $capture = $this->capture();
        
        $this->assertTrue(
            $capture->contains($text),
            $message ?: "Screen should contain '{$text}'.\n" . $capture->dump()
        );
    }

    /**
     * Assert that screen does not contain specific text.
     */
    protected function assertScreenNotContains(string $text, string $message = ''): void
    {
        $capture = $this->capture();
        
        $this->assertFalse(
            $capture->contains($text),
            $message ?: "Screen should not contain '{$text}'.\n" . $capture->dump()
        );
    }

    /**
     * Assert text at specific position.
     */
    protected function assertTextAt(int $x, int $y, string $expected, string $message = ''): void
    {
        $capture = $this->capture();
        $actual = $capture->getText($x, $y, \mb_strlen($expected));
        
        $this->assertSame(
            $expected,
            $actual,
            $message ?: "Text at ({$x}, {$y}) should be '{$expected}', got '{$actual}'.\n" . $capture->dump()
        );
    }

    /**
     * Assert that specific line contains text.
     */
    protected function assertLineContains(int $y, string $text, string $message = ''): void
    {
        $capture = $this->capture();
        $line = $capture->getLine($y);
        
        $this->assertStringContainsString(
            $text,
            $line,
            $message ?: "Line {$y} should contain '{$text}'.\n" . $capture->dump()
        );
    }

    /**
     * Assert current screen class.
     */
    protected function assertCurrentScreen(string $screenClass, string $message = ''): void
    {
        $this->assertNotNull($this->app, 'Application not initialized');
        
        $current = $this->app->getScreenManager()->getCurrentScreen();
        
        $this->assertInstanceOf(
            $screenClass,
            $current,
            $message ?: "Current screen should be {$screenClass}"
        );
    }

    /**
     * Assert screen stack depth.
     */
    protected function assertScreenDepth(int $expected, string $message = ''): void
    {
        $this->assertNotNull($this->app, 'Application not initialized');
        
        $actual = $this->app->getScreenManager()->getDepth();
        
        $this->assertSame(
            $expected,
            $actual,
            $message ?: "Screen depth should be {$expected}, got {$actual}"
        );
    }

    // === Private helpers ===

    /**
     * Run application main loop until input exhausted.
     */
    private function runAppLoop(Application $app, ScreenInterface $screen): void
    {
        // Initialize
        $this->driver->initialize();
        $app->getScreenManager()->pushScreen($screen);

        $renderer = $app->getRenderer();
        $screenManager = $app->getScreenManager();
        $maxIterations = 1000; // Safety limit
        $iterations = 0;

        while ($this->driver->hasInput() && $iterations < $maxIterations) {
            $iterations++;

            // Process input
            while (($key = $this->driver->readInput()) !== null) {
                $screenManager->handleInput($key);
            }

            // Update and render
            $screenManager->update();
            $renderer->beginFrame();
            
            $size = $renderer->getSize();
            $screenManager->render($renderer, 0, 0, $size['width'], $size['height']);
            
            $renderer->endFrame();
        }

        // Final render after all input processed
        $renderer->beginFrame();
        $size = $renderer->getSize();
        $screenManager->render($renderer, 0, 0, $size['width'], $size['height']);
        $renderer->endFrame();
    }

    /**
     * Run application loop until condition met.
     * 
     * @param callable(ScreenCapture): bool $condition
     */
    private function runAppLoopUntil(
        Application $app,
        ScreenInterface $screen,
        callable $condition,
        int $maxFrames,
    ): void {
        $this->driver->initialize();
        $app->getScreenManager()->pushScreen($screen);

        $renderer = $app->getRenderer();
        $screenManager = $app->getScreenManager();

        for ($frame = 0; $frame < $maxFrames; $frame++) {
            // Process available input
            while (($key = $this->driver->readInput()) !== null) {
                $screenManager->handleInput($key);
            }

            // Update and render
            $screenManager->update();
            $renderer->beginFrame();
            
            $size = $renderer->getSize();
            $screenManager->render($renderer, 0, 0, $size['width'], $size['height']);
            
            $renderer->endFrame();

            // Check condition
            $capture = $this->capture();
            if ($condition($capture)) {
                return;
            }
        }

        $this->fail("Condition not met within {$maxFrames} frames.\n" . $this->capture()->dump());
    }
}
```

### 3.6: Verify Framework with Smoke Test

**File**: `tests/Integration/SmokeTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Integration;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\UI\Theme\ThemeContext;
use Butschster\Commander\UI\Theme\ThemeManager;
use Tests\TerminalTestCase;

/**
 * Smoke test to verify testing framework works.
 */
final class SmokeTest extends TerminalTestCase
{
    public function testVirtualDriverWorks(): void
    {
        $this->terminal()->setSize(40, 10);
        $this->terminal()->queueInput('DOWN', 'DOWN', 'ENTER');
        
        $this->assertSame(3, $this->terminal()->getRemainingInputCount());
        $this->assertSame('DOWN', $this->terminal()->readInput());
        $this->assertSame(2, $this->terminal()->getRemainingInputCount());
    }

    public function testKeySequenceBuilder(): void
    {
        $sequence = $this->keys()
            ->down(3)
            ->enter()
            ->escape()
            ->build();
        
        $this->assertSame(['DOWN', 'DOWN', 'DOWN', 'ENTER', 'ESCAPE'], $sequence);
    }

    public function testScreenCaptureContains(): void
    {
        $this->terminal()->setSize(20, 5);
        $this->terminal()->write("\033[1;1HHello World");
        
        $capture = $this->capture();
        
        $this->assertTrue($capture->contains('Hello'));
        $this->assertTrue($capture->contains('World'));
        $this->assertFalse($capture->contains('Goodbye'));
    }

    public function testSimpleScreenRendering(): void
    {
        $screen = new class implements ScreenInterface {
            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
            {
                $theme = $renderer->getThemeContext();
                $renderer->writeAt($x + 1, $y + 1, 'Test Screen', $theme->getNormalText());
            }

            public function handleInput(string $key): bool
            {
                return false;
            }

            public function onActivate(): void {}
            public function onDeactivate(): void {}
            public function update(): void {}
            public function getTitle(): string { return 'Test'; }
        };

        $this->terminal()->setSize(40, 10);
        $this->runApp($screen);
        
        $this->assertScreenContains('Test Screen');
    }
}
```

## Verification

After completing this stage:

1. `composer test` runs without errors
2. Smoke test passes
3. VirtualTerminalDriver integrates with test framework
4. Assertions provide helpful failure messages
5. Key sequence builder creates correct sequences

## Files Created

| File                                    | Description            |
|-----------------------------------------|------------------------|
| `phpunit.xml`                           | PHPUnit configuration  |
| `tests/TestCase.php`                    | Base test case         |
| `tests/TerminalTestCase.php`            | TUI-specific test case |
| `tests/Testing/ScreenCapture.php`       | Screen state capture   |
| `tests/Testing/ScriptedKeySequence.php` | Key sequence builder   |
| `tests/Integration/SmokeTest.php`       | Smoke test             |

## Notes

- TerminalTestCase provides both direct driver access and high-level abstractions
- Assertions include screen dump in failure messages for debugging
- Smoke test validates the entire stack works together
