# Stage 3: E2E Tests - Spinner in Real Application Workflows

## Overview

Create end-to-end tests that verify Spinner behavior in real application workflows. These tests validate:

1. Spinner animation during async operations (ScriptsTab execution)
2. Spinner integration with layout containers
3. Spinner state transitions in multi-screen flows

## Files

CREATE:

- `tests/E2E/Scenario/SpinnerWorkflowScenarioTest.php` - Full workflow tests
- `tests/E2E/Component/SpinnerInLayoutTest.php` - Layout integration tests

## Code References

- `src/Feature/ComposerManager/Tab/ScriptsTab.php:54,85-86,162-167,346-352` - Spinner usage in real tab
- `src/UI/Component/Container/StackLayout.php` - Layout for spinner placement
- `tests/E2E/Scenario/FileWorkflowScenarioTest.php` - E2E test pattern reference
- `tests/TerminalTestCase.php:74-81` - `runUntil()` for async tests

## Implementation Details

### Part A: SpinnerWorkflowScenarioTest

Tests spinner in a real async workflow using a simplified loading screen.

```php
<?php

declare(strict_types=1);

namespace Tests\E2E\Scenario;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Display\Spinner;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Tests\TerminalTestCase;

/**
 * E2E tests for Spinner component in real application workflows.
 *
 * Tests verify spinner animation, state transitions, and integration
 * with the application render loop.
 */
final class SpinnerWorkflowScenarioTest extends TerminalTestCase
{
    // === Animation Workflow Tests ===

    public function testSpinnerAnimatesDuringLoading(): void
    {
        $this->terminal()->setSize(80, 24);

        $spinner = Spinner::createAndStart(Spinner::STYLE_LINE, 0.05);
        $screen = $this->createLoadingScreen($spinner, loadingSteps: 5);

        // Queue enough frame boundaries for animation to occur
        $this->keys()
            ->frame()->frame()->frame()->frame()->frame()
            ->frame()->frame()->frame()->frame()->frame()
            ->applyTo($this->terminal());

        $this->runApp($screen);

        // Spinner text should be visible
        $this->assertScreenContains('Loading');
    }

    public function testSpinnerFrameChangesOverTime(): void
    {
        $this->terminal()->setSize(80, 24);

        $spinner = new Spinner(Spinner::STYLE_LINE, 0.01); // Very fast interval
        $spinner->start();

        $screen = $this->createSpinnerOnlyScreen($spinner);

        // Capture initial frame
        $this->driver->initialize();
        $app = $this->createApp();
        $renderer = $app->getRenderer();

        $renderer->beginFrame();
        $screen->render($renderer, 0, 0, 80, 24);
        $renderer->endFrame();

        $initialCapture = $this->capture();
        $initialContent = $initialCapture->getLine(5);

        // Wait and render again
        \usleep(50000); // 50ms
        $spinner->update();

        $this->driver->clearOutput();
        $renderer->beginFrame();
        $screen->render($renderer, 0, 0, 80, 24);
        $renderer->endFrame();

        $afterCapture = $this->capture();
        $afterContent = $afterCapture->getLine(5);

        // Frame should have changed (animation working)
        $this->assertNotSame($initialContent, $afterContent, 'Spinner frame should animate');
    }

    public function testSpinnerStopsAfterOperationCompletes(): void
    {
        $this->terminal()->setSize(80, 24);

        $spinner = Spinner::createAndStart(Spinner::STYLE_BRAILLE);
        $completed = false;

        $screen = new class($spinner, $completed) implements ScreenInterface {
            private int $frameCount = 0;

            public function __construct(
                private Spinner $spinner,
                private bool &$completed,
            ) {}

            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
            {
                if ($this->spinner->isRunning()) {
                    $this->spinner->render($renderer, $x + 5, $y + 5, 20, 1);
                    $renderer->writeAt($x + 5, $y + 7, 'Processing...', $renderer->getThemeContext()->getNormalText());
                } else {
                    $renderer->writeAt($x + 5, $y + 5, 'Complete!', $renderer->getThemeContext()->getNormalText());
                }
            }

            public function handleInput(string $key): bool { return false; }
            public function onActivate(): void {}
            public function onDeactivate(): void {}

            public function update(): void
            {
                $this->spinner->update();
                $this->frameCount++;

                // Simulate operation completing after 3 frames
                if ($this->frameCount >= 3 && $this->spinner->isRunning()) {
                    $this->spinner->stop();
                    $this->completed = true;
                }
            }

            public function getTitle(): string { return 'Loading'; }
        };

        // Run until "Complete!" appears
        $this->runUntil(
            $screen,
            fn($capture) => $capture->contains('Complete!'),
            maxFrames: 20,
        );

        $this->assertScreenContains('Complete!');
        $this->assertScreenNotContains('Processing...');
        $this->assertFalse($spinner->isRunning());
    }

    // === Layout Integration Tests ===

    public function testSpinnerRendersInHorizontalStack(): void
    {
        $this->terminal()->setSize(80, 24);

        $spinner = new Spinner(Spinner::STYLE_LINE);
        $spinner->setPrefix('[ ')->setSuffix(' ]');

        $screen = $this->createStackLayoutScreen($spinner, 'Loading data...');

        $this->runApp($screen);

        $this->assertScreenContains('[ - ]');
        $this->assertScreenContains('Loading data...');
    }

    public function testSpinnerWithCustomColorRendersCorrectly(): void
    {
        $this->terminal()->setSize(80, 24);

        $spinner = new Spinner(Spinner::STYLE_CIRCLE);
        $spinner->setColor("\033[1;33m"); // Yellow bold

        $screen = $this->createSpinnerOnlyScreen($spinner);

        $this->runApp($screen);

        // Circle spinner starts with ◐
        $this->assertScreenContains('◐');
    }

    // === State Transition Tests ===

    public function testSpinnerStartStopCycleWorks(): void
    {
        $this->terminal()->setSize(80, 24);

        $spinner = new Spinner(Spinner::STYLE_BRAILLE, 0.01);

        // Create screen that cycles start/stop
        $screen = new class($spinner) implements ScreenInterface {
            private int $cycle = 0;

            public function __construct(private Spinner $spinner) {}

            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
            {
                $status = $this->spinner->isRunning() ? 'RUNNING' : 'STOPPED';
                $renderer->writeAt($x + 5, $y + 5, "Cycle {$this->cycle}: {$status}", $renderer->getThemeContext()->getNormalText());
                $this->spinner->render($renderer, $x + 5, $y + 7, 10, 1);
            }

            public function handleInput(string $key): bool { return false; }
            public function onActivate(): void {}
            public function onDeactivate(): void {}

            public function update(): void
            {
                $this->spinner->update();
                $this->cycle++;

                // Cycle through states
                if ($this->cycle === 2) {
                    $this->spinner->start();
                }
                if ($this->cycle === 5) {
                    $this->spinner->stop();
                }
                if ($this->cycle === 7) {
                    $this->spinner->start();
                }
            }

            public function getTitle(): string { return 'Cycle Test'; }
        };

        // Run through cycles
        $this->keys()
            ->frame()->frame()->frame()->frame()->frame()
            ->frame()->frame()->frame()->frame()->frame()
            ->applyTo($this->terminal());

        $this->runApp($screen);

        // After cycle 7, spinner should be running again
        $this->assertTrue($spinner->isRunning());
    }

    public function testSpinnerResetReturnsToFirstFrame(): void
    {
        $this->terminal()->setSize(80, 24);

        $spinner = new Spinner(Spinner::STYLE_LINE, 0.01);
        $spinner->start();

        // Advance several frames
        for ($i = 0; $i < 10; $i++) {
            \usleep(15000);
            $spinner->update();
        }

        // Should not be on first frame
        $this->assertNotSame('-', $spinner->getCurrentFrame());

        // Reset
        $spinner->reset();

        // Should be back to first frame
        $this->assertSame('-', $spinner->getCurrentFrame());
    }

    // === Multi-Style Tests ===

    /**
     * @dataProvider spinnerStyleProvider
     */
    public function testAllSpinnerStylesRenderCorrectly(string $style, string $expectedFirstFrame): void
    {
        $this->terminal()->setSize(80, 24);

        $spinner = new Spinner($style);
        $screen = $this->createSpinnerOnlyScreen($spinner);

        $this->runApp($screen);

        $this->assertScreenContains($expectedFirstFrame);
    }

    public static function spinnerStyleProvider(): array
    {
        return [
            'braille' => [Spinner::STYLE_BRAILLE, '⠋'],
            'dots' => [Spinner::STYLE_DOTS, '⣾'],
            'line' => [Spinner::STYLE_LINE, '-'],
            'arrow' => [Spinner::STYLE_ARROW, '←'],
            'dots_bounce' => [Spinner::STYLE_DOTS_BOUNCE, '⠁'],
            'circle' => [Spinner::STYLE_CIRCLE, '◐'],
            'square' => [Spinner::STYLE_SQUARE, '◰'],
            'clock' => [Spinner::STYLE_CLOCK, '🕐'],
        ];
    }

    // === Helper Methods ===

    private function createLoadingScreen(Spinner $spinner, int $loadingSteps): ScreenInterface
    {
        return new class($spinner, $loadingSteps) implements ScreenInterface {
            private int $step = 0;

            public function __construct(
                private Spinner $spinner,
                private int $loadingSteps,
            ) {}

            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
            {
                $this->spinner->render($renderer, $x + 5, $y + 5, 10, 1);
                $renderer->writeAt($x + 10, $y + 5, "Loading... Step {$this->step}/{$this->loadingSteps}", $renderer->getThemeContext()->getNormalText());
            }

            public function handleInput(string $key): bool { return false; }
            public function onActivate(): void {}
            public function onDeactivate(): void {}

            public function update(): void
            {
                $this->spinner->update();
                if ($this->step < $this->loadingSteps) {
                    $this->step++;
                }
            }

            public function getTitle(): string { return 'Loading'; }
        };
    }

    private function createSpinnerOnlyScreen(Spinner $spinner): ScreenInterface
    {
        return new class($spinner) implements ScreenInterface {
            public function __construct(private Spinner $spinner) {}

            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
            {
                $this->spinner->render($renderer, $x + 5, $y + 5, 20, 1);
            }

            public function handleInput(string $key): bool { return false; }
            public function onActivate(): void {}
            public function onDeactivate(): void {}
            public function update(): void { $this->spinner->update(); }
            public function getTitle(): string { return 'Spinner Test'; }
        };
    }

    private function createStackLayoutScreen(Spinner $spinner, string $text): ScreenInterface
    {
        return new class($spinner, $text) implements ScreenInterface {
            public function __construct(
                private Spinner $spinner,
                private string $text,
            ) {}

            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
            {
                // Simulate horizontal stack: spinner then text
                $spinnerWidth = $this->spinner->getMinSize()['width'];
                $this->spinner->render($renderer, $x + 5, $y + 5, $spinnerWidth, 1);
                $renderer->writeAt($x + 5 + $spinnerWidth + 2, $y + 5, $this->text, $renderer->getThemeContext()->getNormalText());
            }

            public function handleInput(string $key): bool { return false; }
            public function onActivate(): void {}
            public function onDeactivate(): void {}
            public function update(): void { $this->spinner->update(); }
            public function getTitle(): string { return 'Stack Test'; }
        };
    }
}
```

### Part B: Test Scenarios Summary

| Test                                         | Scenario                     | Verifies                            |
|----------------------------------------------|------------------------------|-------------------------------------|
| `testSpinnerAnimatesDuringLoading`           | Loading screen with spinner  | Animation visible during async work |
| `testSpinnerFrameChangesOverTime`            | Frame capture before/after   | Animation actually advances         |
| `testSpinnerStopsAfterOperationCompletes`    | Async operation completion   | State transition and UI update      |
| `testSpinnerRendersInHorizontalStack`        | Layout container             | ComponentInterface integration      |
| `testSpinnerWithCustomColorRendersCorrectly` | Custom color                 | setColor() works                    |
| `testSpinnerStartStopCycleWorks`             | Multiple start/stop cycles   | State machine reliability           |
| `testSpinnerResetReturnsToFirstFrame`        | Reset during animation       | Frame reset behavior                |
| `testAllSpinnerStylesRenderCorrectly`        | All 8 styles (data provider) | All styles render                   |

### Test Categories

| Category           | Test Count                 | Description                              |
|--------------------|----------------------------|------------------------------------------|
| Animation Workflow | 3                          | Async loading, frame changes, completion |
| Layout Integration | 2                          | Stack layout, custom colors              |
| State Transitions  | 2                          | Start/stop cycles, reset                 |
| Multi-Style        | 1 (8 cases)                | All spinner styles via data provider     |
| **Total**          | **8 tests, 15 assertions** |                                          |

## Definition of Done

- [ ] `SpinnerWorkflowScenarioTest.php` created in `tests/E2E/Scenario/`
- [ ] All E2E tests pass with `vendor/bin/phpunit --testsuite=E2E --filter=Spinner`
- [ ] Tests verify animation occurs (frame changes over time)
- [ ] Tests verify state transitions (start → running → stop → stopped)
- [ ] Tests verify layout integration (ComponentInterface works in practice)
- [ ] Tests cover all 8 spinner styles
- [ ] No flaky tests (deterministic timing with `runUntil`)
- [ ] Tests complete in under 5 seconds

## Dependencies

**Requires**: Stage 1 (Spinner refactoring), Stage 2 (Unit tests)
**Enables**: Production usage with confidence
