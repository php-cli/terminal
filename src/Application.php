<?php

declare(strict_types=1);

namespace Butschster\Commander;

use Butschster\Commander\Screen\ScreenInterface;
use Butschster\Commander\Service\KeyboardHandler;
use Butschster\Commander\Service\Renderer;
use Butschster\Commander\Service\ScreenManager;
use Butschster\Commander\Service\TerminalManager;
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

    private TerminalManager $terminal;
    private Renderer $renderer;
    private KeyboardHandler $keyboard;
    private ScreenManager $screenManager;

    private ?SymfonyApplication $symfonyApp = null;

    public function __construct(?SymfonyApplication $symfonyApp = null)
    {
        $this->symfonyApp = $symfonyApp;
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
        $this->targetFps = max(1, min(60, $fps));
        $this->frameTime = 1.0 / $this->targetFps;
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
     * Main event loop
     */
    private function mainLoop(): void
    {
        $lastFrameTime = microtime(true);

        while ($this->running && $this->screenManager->hasScreens()) {
            $frameStart = microtime(true);

            // Handle input
            $this->handleInput();

            // Update screen state
            $this->screenManager->update();

            // Render frame
            $this->renderFrame();

            // Frame timing
            $frameEnd = microtime(true);
            $frameDuration = $frameEnd - $frameStart;

            // Sleep to maintain target FPS
            if ($frameDuration < $this->frameTime) {
                usleep((int) (($this->frameTime - $frameDuration) * 1000000));
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
                } else {
                    $this->stop();
                }
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

        // Render current screen
        $this->screenManager->render($this->renderer);

        // End frame (flush to terminal)
        $this->renderer->endFrame();
    }

    /**
     * Setup signal handlers for graceful shutdown
     */
    private function setupSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        // Handle SIGINT (Ctrl+C)
        pcntl_signal(SIGINT, function () {
            $this->stop();
        });

        // Handle SIGTERM
        pcntl_signal(SIGTERM, function () {
            $this->stop();
        });

        // Handle SIGWINCH (terminal resize)
        pcntl_signal(SIGWINCH, function () {
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
}
