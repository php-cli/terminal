<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Screen;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Container\Direction;
use Butschster\Commander\UI\Component\Container\StackLayout;
use Butschster\Commander\UI\Component\Decorator\Padding;
use Butschster\Commander\UI\Component\Display\TextDisplay;
use Butschster\Commander\UI\Component\Layout\Panel;
use Butschster\Commander\UI\Component\Layout\StatusBar;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\UI\Screen\ScreenManager;

/**
 * Screen for showing real-time Composer command output
 * 
 * Executes a composer command and streams output to the screen.
 * User can press ESC to go back after completion.
 */
final class ComposerOutputScreen implements ScreenInterface
{
    private StackLayout $rootLayout;
    private Panel $outputPanel;
    private TextDisplay $outputDisplay;
    private StatusBar $statusBar;

    private bool $isRunning = true;
    private bool $hasError = false;
    private int $exitCode = 0;
    private ?callable $onCompleteCallback = null;
    private ?ScreenManager $screenManager = null;

    /**
     * @param string $title Screen title
     * @param callable(callable(string): void): array{exitCode: int, error: string} $command
     */
    public function __construct(
        private readonly string $title,
        private $command,
    ) {
        $this->initializeComponents();
        $this->executeCommand();
    }

    public function setScreenManager(ScreenManager $screenManager): void
    {
        $this->screenManager = $screenManager;
    }

    /**
     * Set callback to execute when command completes successfully
     */
    public function onComplete(callable $callback): void
    {
        $this->onCompleteCallback = $callback;
    }

    private function initializeComponents(): void
    {
        $this->outputDisplay = new TextDisplay();
        $this->outputDisplay->setAutoScroll(true);

        $paddedOutput = Padding::symmetric($this->outputDisplay, horizontal: 1, vertical: 0);
        $this->outputPanel = new Panel($this->title, $paddedOutput);
        $this->outputPanel->setFocused(true);

        $this->statusBar = new StatusBar([
            '' => 'Running...',
        ]);

        $this->rootLayout = new StackLayout(Direction::VERTICAL);
        $this->rootLayout->addChild($this->outputPanel);
        $this->rootLayout->addChild($this->statusBar, size: 1);
    }

    private function executeCommand(): void
    {
        // Execute in background (simulated)
        // In real implementation, this would be in a separate process/thread
        
        try {
            $result = ($this->command)(function(string $output): void {
                $this->outputDisplay->appendText($output);
            });

            $this->exitCode = $result['exitCode'];
            $this->hasError = $this->exitCode !== 0;

            if ($this->hasError && !empty($result['error'])) {
                $this->outputDisplay->appendText("\n\nErrors:\n" . $result['error']);
            }
        } catch (\Throwable $e) {
            $this->hasError = true;
            $this->exitCode = 1;
            $this->outputDisplay->appendText("\n\nException: {$e->getMessage()}\n");
            $this->outputDisplay->appendText($e->getTraceAsString());
        } finally {
            $this->isRunning = false;
            $this->updateStatusBar();

            // Call completion callback if successful
            if (!$this->hasError && $this->onCompleteCallback !== null) {
                ($this->onCompleteCallback)();
            }
        }
    }

    private function updateStatusBar(): void
    {
        if ($this->isRunning) {
            $this->statusBar = new StatusBar([
                '' => 'Running... (this may take a while)',
            ]);
        } elseif ($this->hasError) {
            $this->statusBar = new StatusBar([
                '' => "❌ Failed (exit code: {$this->exitCode})",
                'ESC' => 'Back',
            ]);
        } else {
            $this->statusBar = new StatusBar([
                '' => '✓ Completed successfully',
                'ESC' => 'Back',
            ]);
        }
    }

    // ScreenInterface implementation

    public function render(Renderer $renderer): void
    {
        $size = $renderer->getSize();
        $this->rootLayout->render($renderer, 0, 1, $size['width'], $size['height'] - 1);
    }

    public function handleInput(string $key): bool
    {
        // Can't interrupt while running
        if ($this->isRunning) {
            return true;
        }

        // Allow going back after completion
        if ($key === 'ESCAPE') {
            $this->screenManager?->popScreen();
            return true;
        }

        // Allow scrolling output
        return $this->outputPanel->handleInput($key);
    }

    public function onActivate(): void
    {
        // Nothing to do
    }

    public function onDeactivate(): void
    {
        // Nothing to do
    }

    public function update(): void
    {
        // Update status bar if state changed
        $this->updateStatusBar();
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
