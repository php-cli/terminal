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
    /**
     * @return array<string, array{string, string}>
     */
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
    // === Animation Workflow Tests ===

    public function testSpinnerAnimatesDuringLoading(): void
    {
        $this->terminal()->setSize(180, 50);

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
        $this->terminal()->setSize(180, 50);

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
        $this->terminal()->setSize(180, 50);

        $spinner = Spinner::createAndStart(Spinner::STYLE_BRAILLE);

        $screen = new class($spinner) implements ScreenInterface {
            private int $frameCount = 0;

            public function __construct(
                private readonly Spinner $spinner,
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

            public function handleInput(string $key): bool
            {
                return false;
            }

            public function onActivate(): void {}

            public function onDeactivate(): void {}

            public function update(): void
            {
                $this->spinner->update();
                $this->frameCount++;

                // Simulate operation completing after 3 frames
                if ($this->frameCount >= 3 && $this->spinner->isRunning()) {
                    $this->spinner->stop();
                }
            }

            public function getTitle(): string
            {
                return 'Loading';
            }
        };

        // Run until "Complete!" appears
        $this->runUntil(
            $screen,
            static fn($capture) => $capture->contains('Complete!'),
            maxFrames: 20,
        );

        $this->assertScreenContains('Complete!');
        $this->assertScreenNotContains('Processing...');
        $this->assertFalse($spinner->isRunning());
    }

    // === Layout Integration Tests ===

    public function testSpinnerRendersInHorizontalStack(): void
    {
        $this->terminal()->setSize(180, 50);

        $spinner = new Spinner(Spinner::STYLE_LINE);
        $spinner->setPrefix('[ ')->setSuffix(' ]');

        $screen = $this->createStackLayoutScreen($spinner, 'Loading data...');

        $this->runApp($screen);

        $this->assertScreenContains('[ - ]');
        $this->assertScreenContains('Loading data...');
    }

    public function testSpinnerWithCustomColorRendersCorrectly(): void
    {
        $this->terminal()->setSize(180, 50);

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
        $this->terminal()->setSize(180, 50);

        $spinner = new Spinner(Spinner::STYLE_BRAILLE, 0.01);

        // Create screen that cycles start/stop
        $screen = new class($spinner) implements ScreenInterface {
            private int $cycle = 0;

            public function __construct(private readonly Spinner $spinner) {}

            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
            {
                $status = $this->spinner->isRunning() ? 'RUNNING' : 'STOPPED';
                $renderer->writeAt($x + 5, $y + 5, "Cycle {$this->cycle}: {$status}", $renderer->getThemeContext()->getNormalText());
                $this->spinner->render($renderer, $x + 5, $y + 7, 10, 1);
            }

            public function handleInput(string $key): bool
            {
                return false;
            }

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

            public function getTitle(): string
            {
                return 'Cycle Test';
            }
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
        $this->terminal()->setSize(180, 50);

        $spinner = new Spinner($style);
        $screen = $this->createSpinnerOnlyScreen($spinner);

        $this->runApp($screen);

        $this->assertScreenContains($expectedFirstFrame);
    }

    // === Multiple Spinners Tests ===

    public function testMultipleSpinnersOnSameScreen(): void
    {
        $this->terminal()->setSize(180, 50);

        $spinner1 = new Spinner(Spinner::STYLE_LINE);
        $spinner1->setPrefix('Task 1: ');

        $spinner2 = new Spinner(Spinner::STYLE_BRAILLE);
        $spinner2->setPrefix('Task 2: ');

        $spinner3 = new Spinner(Spinner::STYLE_DOTS);
        $spinner3->setPrefix('Task 3: ');

        $screen = new readonly class($spinner1, $spinner2, $spinner3) implements ScreenInterface {
            public function __construct(
                private Spinner $spinner1,
                private Spinner $spinner2,
                private Spinner $spinner3,
            ) {}

            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
            {
                $this->spinner1->render($renderer, $x + 5, $y + 5, 20, 1);
                $this->spinner2->render($renderer, $x + 5, $y + 7, 20, 1);
                $this->spinner3->render($renderer, $x + 5, $y + 9, 20, 1);
            }

            public function handleInput(string $key): bool
            {
                return false;
            }

            public function onActivate(): void {}

            public function onDeactivate(): void {}

            public function update(): void
            {
                $this->spinner1->update();
                $this->spinner2->update();
                $this->spinner3->update();
            }

            public function getTitle(): string
            {
                return 'Multi Spinner';
            }
        };

        $this->runApp($screen);

        $this->assertScreenContains('Task 1:');
        $this->assertScreenContains('Task 2:');
        $this->assertScreenContains('Task 3:');
    }

    public function testSpinnersWithDifferentStates(): void
    {
        $this->terminal()->setSize(180, 50);

        $runningSpinner = Spinner::createAndStart(Spinner::STYLE_LINE, 0.01);
        $stoppedSpinner = new Spinner(Spinner::STYLE_BRAILLE);
        // stoppedSpinner is not started

        $screen = new class($runningSpinner, $stoppedSpinner) implements ScreenInterface {
            private int $frames = 0;

            public function __construct(
                private readonly Spinner $running,
                private readonly Spinner $stopped,
            ) {}

            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
            {
                $runningStatus = $this->running->isRunning() ? 'RUNNING' : 'STOPPED';
                $stoppedStatus = $this->stopped->isRunning() ? 'RUNNING' : 'STOPPED';

                $renderer->writeAt($x + 5, $y + 5, "Spinner 1: {$runningStatus}", $renderer->getThemeContext()->getNormalText());
                $this->running->render($renderer, $x + 30, $y + 5, 5, 1);

                $renderer->writeAt($x + 5, $y + 7, "Spinner 2: {$stoppedStatus}", $renderer->getThemeContext()->getNormalText());
                $this->stopped->render($renderer, $x + 30, $y + 7, 5, 1);
            }

            public function handleInput(string $key): bool
            {
                return false;
            }

            public function onActivate(): void {}

            public function onDeactivate(): void {}

            public function update(): void
            {
                $this->running->update();
                $this->stopped->update();
                $this->frames++;
            }

            public function getTitle(): string
            {
                return 'State Test';
            }
        };

        $this->keys()->frame()->frame()->frame()->applyTo($this->terminal());
        $this->runApp($screen);

        $this->assertScreenContains('Spinner 1: RUNNING');
        $this->assertScreenContains('Spinner 2: STOPPED');
    }

    // === Screen Size Tests ===

    public function testSpinnerRendersInSmallTerminal(): void
    {
        $this->terminal()->setSize(160, 40);

        $spinner = new Spinner(Spinner::STYLE_LINE);
        $spinner->setPrefix('[ ')->setSuffix(' ]');

        $screen = $this->createSpinnerOnlyScreen($spinner);

        $this->runApp($screen);

        $this->assertScreenContains('[ - ]');
    }

    public function testSpinnerRendersInLargeTerminal(): void
    {
        $this->terminal()->setSize(200, 60);

        $spinner = new Spinner(Spinner::STYLE_BRAILLE);
        $spinner->setPrefix('Loading: ')->setSuffix(' please wait...');

        // Use a screen with enough width for full content
        $screen = new readonly class($spinner) implements ScreenInterface {
            public function __construct(private Spinner $spinner) {}

            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
            {
                $this->spinner->render($renderer, $x + 5, $y + 5, 50, 1); // 50 width for full text
            }

            public function handleInput(string $key): bool
            {
                return false;
            }

            public function onActivate(): void {}

            public function onDeactivate(): void {}

            public function update(): void
            {
                $this->spinner->update();
            }

            public function getTitle(): string
            {
                return 'Large Terminal';
            }
        };

        $this->runApp($screen);

        $this->assertScreenContains('Loading:');
        $this->assertScreenContains('please wait...');
    }

    // === Progress Simulation Tests ===

    public function testSpinnerWithProgressCounter(): void
    {
        $this->terminal()->setSize(180, 50);

        $spinner = Spinner::createAndStart(Spinner::STYLE_DOTS);

        $screen = new class($spinner) implements ScreenInterface {
            private int $progress = 0;

            public function __construct(private readonly Spinner $spinner) {}

            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
            {
                // Render spinner first
                $this->spinner->render($renderer, $x + 5, $y + 5, 2, 1);
                // Render progress text with enough spacing
                $renderer->writeAt($x + 5, $y + 7, "Done: {$this->progress}%", $renderer->getThemeContext()->getNormalText());
            }

            public function handleInput(string $key): bool
            {
                return false;
            }

            public function onActivate(): void {}

            public function onDeactivate(): void {}

            public function update(): void
            {
                $this->spinner->update();
                if ($this->progress < 100) {
                    $this->progress += 10;
                }
            }

            public function getTitle(): string
            {
                return 'Progress';
            }
        };

        $this->keys()
            ->frame()->frame()->frame()->frame()->frame()
            ->applyTo($this->terminal());

        $this->runApp($screen);

        $this->assertScreenContains('Done:');
    }

    public function testSpinnerStopsAtProgressComplete(): void
    {
        $this->terminal()->setSize(180, 50);

        $spinner = Spinner::createAndStart(Spinner::STYLE_CIRCLE);

        $screen = new class($spinner) implements ScreenInterface {
            private int $progress = 0;

            public function __construct(private readonly Spinner $spinner) {}

            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
            {
                if ($this->progress < 100) {
                    $this->spinner->render($renderer, $x + 5, $y + 5, 5, 1);
                    $renderer->writeAt($x + 12, $y + 5, "{$this->progress}%", $renderer->getThemeContext()->getNormalText());
                } else {
                    $renderer->writeAt($x + 5, $y + 5, 'Done!', $renderer->getThemeContext()->getNormalText());
                }
            }

            public function handleInput(string $key): bool
            {
                return false;
            }

            public function onActivate(): void {}

            public function onDeactivate(): void {}

            public function update(): void
            {
                $this->spinner->update();
                $this->progress += 20;
                if ($this->progress >= 100) {
                    $this->spinner->stop();
                }
            }

            public function getTitle(): string
            {
                return 'Progress';
            }
        };

        $this->runUntil(
            $screen,
            static fn($capture) => $capture->contains('Done!'),
            maxFrames: 20,
        );

        $this->assertScreenContains('Done!');
        $this->assertFalse($spinner->isRunning());
    }

    // === Visibility Toggle Tests ===

    public function testSpinnerVisibilityToggle(): void
    {
        $this->terminal()->setSize(180, 50);

        $spinner = new Spinner(Spinner::STYLE_LINE);

        $screen = new class($spinner) implements ScreenInterface {
            private bool $visible = true;
            private int $toggleCount = 0;

            public function __construct(private readonly Spinner $spinner) {}

            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
            {
                if ($this->visible) {
                    $this->spinner->render($renderer, $x + 5, $y + 5, 10, 1);
                }
                $status = $this->visible ? 'YES' : 'NO';
                $renderer->writeAt($x + 5, $y + 7, "Status {$status}", $renderer->getThemeContext()->getNormalText());
            }

            public function handleInput(string $key): bool
            {
                return false;
            }

            public function onActivate(): void {}

            public function onDeactivate(): void {}

            public function update(): void
            {
                $this->spinner->update();
                $this->toggleCount++;
                // Toggle visibility every 3 frames
                if ($this->toggleCount % 3 === 0) {
                    $this->visible = !$this->visible;
                }
            }

            public function getTitle(): string
            {
                return 'Toggle';
            }
        };

        $this->keys()
            ->frame()->frame()->frame()->frame()->frame()->frame()
            ->applyTo($this->terminal());

        $this->runApp($screen);

        // Should show visibility status (either YES or NO depending on frame count)
        $this->assertScreenContains('Status');
    }

    // === Error State Tests ===

    public function testSpinnerInErrorState(): void
    {
        $this->terminal()->setSize(180, 50);

        $spinner = Spinner::createAndStart(Spinner::STYLE_BRAILLE);

        $screen = new class($spinner) implements ScreenInterface {
            private bool $hasError = false;
            private int $frames = 0;

            public function __construct(private readonly Spinner $spinner) {}

            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
            {
                if ($this->hasError) {
                    $renderer->writeAt($x + 5, $y + 5, 'ERROR: Operation failed', $renderer->getThemeContext()->getNormalText());
                } else {
                    $this->spinner->render($renderer, $x + 5, $y + 5, 5, 1);
                    $renderer->writeAt($x + 12, $y + 5, 'Working...', $renderer->getThemeContext()->getNormalText());
                }
            }

            public function handleInput(string $key): bool
            {
                return false;
            }

            public function onActivate(): void {}

            public function onDeactivate(): void {}

            public function update(): void
            {
                $this->spinner->update();
                $this->frames++;
                // Simulate error after 3 frames
                if ($this->frames >= 3) {
                    $this->hasError = true;
                    $this->spinner->stop();
                }
            }

            public function getTitle(): string
            {
                return 'Error Test';
            }
        };

        $this->runUntil(
            $screen,
            static fn($capture) => $capture->contains('ERROR'),
            maxFrames: 20,
        );

        $this->assertScreenContains('ERROR: Operation failed');
        $this->assertFalse($spinner->isRunning());
    }

    // === Chained Configuration Tests ===

    public function testSpinnerWithChainedConfiguration(): void
    {
        $this->terminal()->setSize(180, 50);

        $spinner = Spinner::create(Spinner::STYLE_ARROW)
            ->setPrefix('>>> ')
            ->setSuffix(' <<<')
            ->setColor("\033[1;36m"); // Cyan

        $screen = $this->createSpinnerOnlyScreen($spinner);

        $this->runApp($screen);

        $this->assertScreenContains('>>>');
        $this->assertScreenContains('<<<');
    }

    // === Helper Methods ===

    private function createLoadingScreen(Spinner $spinner, int $loadingSteps): ScreenInterface
    {
        return new class($spinner, $loadingSteps) implements ScreenInterface {
            private int $step = 0;

            public function __construct(
                private readonly Spinner $spinner,
                private readonly int $loadingSteps,
            ) {}

            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
            {
                $this->spinner->render($renderer, $x + 5, $y + 5, 10, 1);
                $renderer->writeAt($x + 10, $y + 5, "Loading... Step {$this->step}/{$this->loadingSteps}", $renderer->getThemeContext()->getNormalText());
            }

            public function handleInput(string $key): bool
            {
                return false;
            }

            public function onActivate(): void {}

            public function onDeactivate(): void {}

            public function update(): void
            {
                $this->spinner->update();
                if ($this->step < $this->loadingSteps) {
                    $this->step++;
                }
            }

            public function getTitle(): string
            {
                return 'Loading';
            }
        };
    }

    private function createSpinnerOnlyScreen(Spinner $spinner): ScreenInterface
    {
        return new readonly class($spinner) implements ScreenInterface {
            public function __construct(private Spinner $spinner) {}

            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
            {
                $this->spinner->render($renderer, $x + 5, $y + 5, 20, 1);
            }

            public function handleInput(string $key): bool
            {
                return false;
            }

            public function onActivate(): void {}

            public function onDeactivate(): void {}

            public function update(): void
            {
                $this->spinner->update();
            }

            public function getTitle(): string
            {
                return 'Spinner Test';
            }
        };
    }

    private function createStackLayoutScreen(Spinner $spinner, string $text): ScreenInterface
    {
        return new readonly class($spinner, $text) implements ScreenInterface {
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

            public function handleInput(string $key): bool
            {
                return false;
            }

            public function onActivate(): void {}

            public function onDeactivate(): void {}

            public function update(): void
            {
                $this->spinner->update();
            }

            public function getTitle(): string
            {
                return 'Stack Test';
            }
        };
    }
}
