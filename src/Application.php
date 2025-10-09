<?php

declare(strict_types=1);

namespace Butschster\Commander;

use Butschster\Commander\Infrastructure\Terminal\KeyboardHandler;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\Infrastructure\Terminal\TerminalManager;
use Butschster\Commander\UI\Component\Layout\MenuBar;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\UI\Screen\ScreenManager;
use Symfony\Component\Console\Application as SymfonyApplication;

/**
 * Main MC-style console application
 *
 * Manages the event loop, rendering, and keyboard input
 */
final class Application
{
    private bool $running = false;
    private int $targetFps = 30;
    private float $frameTime;
    private readonly TerminalManager $terminal;
    private readonly Renderer $renderer;
    private readonly KeyboardHandler $keyboard;
    private readonly ScreenManager $screenManager;

    /** @var array<string, callable> Global function key shortcuts */
    private array $globalShortcuts = [];

    /** @var MenuBar|null Global menu bar */
    private ?MenuBar $globalMenuBar = null;

    public function __construct(private readonly ?SymfonyApplication $symfonyApp = null)
    {
        $this->frameTime = 1.0 / $this->targetFps;

        // Initialize services
        $this->terminal = new TerminalManager();
        $this->renderer = new Renderer($this->terminal);
        $this->keyboard = new KeyboardHandler();
        $this->screenManager = new ScreenManager();

        // Setup signal handlers for graceful shutdown
        $this->setupSignalHandlers();
    }

    /**
     * Set target FPS
     */
    public function setTargetFps(int $fps): void
    {
        $this->targetFps = \max(1, \min(60, $fps));
        $this->frameTime = 1.0 / $this->targetFps;
    }

    /**
     * Register a global function key shortcut
     *
     * @param string $key Function key (e.g., 'F3', 'F4')
     * @param callable $callback Callback to execute (receives ScreenManager)
     */
    public function registerGlobalShortcut(string $key, callable $callback): void
    {
        $this->globalShortcuts[$key] = $callback;
    }

    /**
     * Set global menu bar
     */
    public function setGlobalMenuBar(?MenuBar $menuBar): void
    {
        $this->globalMenuBar = $menuBar;
    }

    /**
     * Get Symfony Application instance
     */
    public function getSymfonyApplication(): ?SymfonyApplication
    {
        return $this->symfonyApp;
    }

    /**
     * Get screen manager
     */
    public function getScreenManager(): ScreenManager
    {
        return $this->screenManager;
    }

    /**
     * Push initial screen and start the application
     */
    public function run(ScreenInterface $initialScreen): void
    {
        try {
            // Initialize terminal
            $this->terminal->initialize();
            $this->keyboard->enableNonBlocking();

            // Push initial screen
            $this->screenManager->pushScreen($initialScreen);

            // Start main loop
            $this->running = true;
            $this->mainLoop();
        } catch (\Throwable $e) {
            // Ensure terminal is cleaned up even on error
            $this->cleanup();
            throw $e;
        }

        $this->cleanup();
    }

    /**
     * Stop the application
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Get renderer (for debugging/testing)
     */
    public function getRenderer(): Renderer
    {
        return $this->renderer;
    }

    /**
     * Get terminal manager (for debugging/testing)
     */
    public function getTerminal(): TerminalManager
    {
        return $this->terminal;
    }

    /**
     * Get keyboard handler (for debugging/testing)
     */
    public function getKeyboard(): KeyboardHandler
    {
        return $this->keyboard;
    }

    /**
     * Main event loop
     */
    private function mainLoop(): void
    {
        $lastFrameTime = \microtime(true);

        while ($this->running && $this->screenManager->hasScreens()) {
            $frameStart = \microtime(true);

            // Handle input
            $this->handleInput();

            // Update screen state
            $this->screenManager->update();

            // Render frame
            $this->renderFrame();

            // Frame timing
            $frameEnd = \microtime(true);
            $frameDuration = $frameEnd - $frameStart;

            // Sleep to maintain target FPS
            if ($frameDuration < $this->frameTime) {
                \usleep((int) (($this->frameTime - $frameDuration) * 1000000));
            }

            $lastFrameTime = $frameEnd;
        }
    }

    /**
     * Handle keyboard input
     */
    private function handleInput(): void
    {
        while (($key = $this->keyboard->getKey()) !== null) {
            // Global shortcuts have highest priority
            if (isset($this->globalShortcuts[$key])) {
                $callback = $this->globalShortcuts[$key];
                $callback($this->screenManager);
                continue;
            }

            // Global shortcuts
            if ($key === 'CTRL_C') {
                $this->stop();
                return;
            }

            // Route to screen manager
            $handled = $this->screenManager->handleInput($key);

            // If not handled and ESC pressed, go back
            if (!$handled && $key === 'ESCAPE') {
                if ($this->screenManager->getDepth() > 1) {
                    $this->screenManager->popScreen();
                }
                // $this->stop();

            }
        }
    }

    /**
     * Render a frame
     */
    private function renderFrame(): void
    {
        // Check for terminal resize
        $this->renderer->handleResize();

        // Begin frame
        $this->renderer->beginFrame();

        $size = $this->renderer->getSize();
        $width = $size['width'];
        $height = $size['height'];

        // Render global menu bar at top if set
        if ($this->globalMenuBar !== null) {
            $this->globalMenuBar->render($this->renderer, 0, 0, $width, 1);
        }

        // Render current screen (screen handles its own status bar)
        $this->screenManager->render($this->renderer);

        // End frame (flush to terminal)
        $this->renderer->endFrame();
    }

    /**
     * Setup signal handlers for graceful shutdown
     */
    private function setupSignalHandlers(): void
    {
        if (!\function_exists('pcntl_signal')) {
            return;
        }

        // Handle SIGINT (Ctrl+C)
        \pcntl_signal(SIGINT, function (): void {
            $this->stop();
        });

        // Handle SIGTERM
        \pcntl_signal(SIGTERM, function (): void {
            $this->stop();
        });

        // Handle SIGWINCH (terminal resize)
        \pcntl_signal(SIGWINCH, function (): void {
            $this->renderer->handleResize();
        });
    }

    /**
     * Cleanup and restore terminal
     */
    private function cleanup(): void
    {
        $this->keyboard->disableNonBlocking();
        $this->terminal->cleanup();
    }
}
