<?php

declare(strict_types=1);

namespace Tests;

use Butschster\Commander\Application;
use Tests\Testing\VirtualTerminalDriver;
use Tests\Testing\ScriptedKeySequence;
use Tests\Testing\ScreenCapture;
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

    /**
     * Assert that screen contains specific text.
     */
    protected function assertScreenContains(string $text, string $message = ''): void
    {
        $capture = $this->capture();

        $this->assertTrue(
            $capture->contains($text),
            $message ?: "Screen should contain '{$text}'.\n" . $capture->dump(),
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
            $message ?: "Screen should not contain '{$text}'.\n" . $capture->dump(),
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
            $message ?: "Text at ({$x}, {$y}) should be '{$expected}', got '{$actual}'.\n" . $capture->dump(),
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
            $message ?: "Line {$y} should contain '{$text}'.\n" . $capture->dump(),
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
            $message ?: "Current screen should be {$screenClass}",
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
            $message ?: "Screen depth should be {$expected}, got {$actual}",
        );
    }

    // === Extended Content Assertions ===

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
            $message ?: "Screen should contain '{$text}' exactly {$count} times, found " . \count($found) . ".\n" . $capture->dump(),
        );
    }

    /**
     * Assert screen contains all specified texts.
     *
     * @param array<string> $texts
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
            $message ?: "Screen should contain all texts. Missing: " . \implode(', ', $missing) . "\n" . $capture->dump(),
        );
    }

    /**
     * Assert screen contains at least one of the specified texts.
     *
     * @param array<string> $texts
     */
    protected function assertScreenContainsAny(array $texts, string $message = ''): void
    {
        $capture = $this->capture();

        foreach ($texts as $text) {
            if ($capture->contains($text)) {
                return;
            }
        }

        $this->fail(
            $message ?: "Screen should contain at least one of: " . \implode(', ', $texts) . "\n" . $capture->dump(),
        );
    }

    // === Extended Line Assertions ===

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
            $message ?: "Line {$y} should equal '{$expected}', got '{$actual}'.\n" . $capture->dump(),
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
            $message ?: "Line {$y} should start with '{$prefix}'.\n" . $capture->dump(),
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
            $message ?: "Line {$y} should be empty.\n" . $capture->dump(),
        );
    }

    /**
     * Assert text appears on specific line (anywhere on that line).
     */
    protected function assertTextOnLine(int $y, string $text, string $message = ''): void
    {
        $capture = $this->capture();
        $line = $capture->getLine($y);

        $this->assertStringContainsString(
            $text,
            $line,
            $message ?: "Text '{$text}' should appear on line {$y}.\n" . $capture->dump(),
        );
    }

    /**
     * Assert region content (rectangular area).
     *
     * @param array<string> $expected
     */
    protected function assertRegion(int $x, int $y, int $width, int $height, array $expected, string $message = ''): void
    {
        $capture = $this->capture();
        $actual = $capture->getRegion($x, $y, $width, $height);

        $this->assertSame(
            $expected,
            $actual,
            $message ?: "Region at ({$x},{$y}) {$width}x{$height} doesn't match.\n" . $capture->dump(),
        );
    }

    // === Extended Screen State Assertions ===

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
            $message ?: "Screen title should be '{$expected}'",
        );
    }

    // === Color Assertions ===

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
            $message ?: "Color at ({$x},{$y}) should be '{$expectedColor}', got '{$actual}'",
        );
    }

    /**
     * Assert that specific text has specific color.
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
            $message ?: "Text '{$text}' should have color '{$expectedColor}'",
        );
    }

    // === Debugging Helpers ===

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
     */
    protected function pauseAndShow(string $label = ''): void
    {
        if ($label !== '') {
            echo "\n=== {$label} ===\n";
        }
        $this->dumpScreenWithLines();
    }

    /**
     * Run application main loop until input exhausted.
     */
    private function runAppLoop(Application $app, ScreenInterface $screen): void
    {
        $this->driver->initialize();
        $app->getScreenManager()->pushScreen($screen);

        $renderer = $app->getRenderer();
        $screenManager = $app->getScreenManager();
        $maxIterations = 1000;
        $iterations = 0;

        while ($this->driver->hasInput() && $iterations < $maxIterations) {
            $iterations++;

            while (($key = $this->driver->readInput()) !== null) {
                $screenManager->handleInput($key);
            }

            $screenManager->update();
            $renderer->beginFrame();

            $size = $renderer->getSize();
            $screenManager->render($renderer, 0, 0, $size['width'], $size['height']);

            $renderer->endFrame();
        }

        // Final render
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
            while (($key = $this->driver->readInput()) !== null) {
                $screenManager->handleInput($key);
            }

            $screenManager->update();
            $renderer->beginFrame();

            $size = $renderer->getSize();
            $screenManager->render($renderer, 0, 0, $size['width'], $size['height']);

            $renderer->endFrame();

            $capture = $this->capture();
            if ($condition($capture)) {
                return;
            }
        }

        $this->fail("Condition not met within {$maxFrames} frames.\n" . $this->capture()->dump());
    }
}
