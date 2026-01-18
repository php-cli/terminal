<?php

declare(strict_types=1);

namespace Butschster\Commander;

use Butschster\Commander\Infrastructure\Terminal\KeyboardHandler;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\Infrastructure\Terminal\TerminalManager;
use Butschster\Commander\UI\Component\Layout\MenuSystem;
use Butschster\Commander\UI\Menu\MenuDefinition;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\UI\Screen\ScreenManager;
use Butschster\Commander\UI\Screen\ScreenRegistry;
use Butschster\Commander\UI\Theme\ColorScheme;
use Butschster\Commander\UI\Theme\ThemeContext;
use Butschster\Commander\UI\Theme\ThemeManager;

/**
 * Main MC-style console application
 *
 * Manages the event loop, rendering, and keyboard input with automatic
 * screen registration and menu system generation.
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
    private readonly ThemeContext $themeContext;

    /** @var array<string, callable> Global function key shortcuts */
    private array $globalShortcuts = [];

    /** @var MenuSystem|null Global menu system (menu bar + dropdowns) */
    private ?MenuSystem $menuSystem = null;

    /** @var ScreenRegistry|null Screen registry for automatic navigation */
    private ?ScreenRegistry $screenRegistry = null;

    /** Track screen depth to detect screen changes */
    private int $lastScreenDepth = 0;

    public function __construct()
    {
        $this->frameTime = 1.0 / $this->targetFps;

        // Initialize theme context from current theme
        $theme = ThemeManager::getCurrentTheme();
        $this->themeContext = new ThemeContext($theme);

        // Apply theme to ColorScheme for backward compatibility
        ColorScheme::applyTheme($theme);

        // Initialize services
        $this->terminal = new TerminalManager();
        $this->renderer = new Renderer($this->terminal, $this->themeContext);
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
     * Set screen registry (enables automatic screen navigation)
     */
    public function setScreenRegistry(ScreenRegistry $registry): void
    {
        $this->screenRegistry = $registry;
    }

    /**
     * Set menu system from MenuBuilder
     *
     * Automatically registers F-key shortcuts for menu navigation.
     *
     * @param array<string, MenuDefinition> $menus
     */
    public function setMenuSystem(array $menus): void
    {
        if ($this->screenRegistry === null) {
            throw new \RuntimeException('Screen registry must be set before menu system');
        }

        // Create MenuSystem component
        $this->menuSystem = new MenuSystem(
            $menus,
            $this->screenRegistry,
            $this->screenManager,
        );

        // Set quit callback
        $this->menuSystem->onQuit(fn() => $this->stop());

        // Note: F-key shortcuts are now handled by MenuSystem itself
        // No need to register them separately as global shortcuts
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
            // Priority 1: Menu system (handles both dropdown when open AND F-key presses)
            if ($this->menuSystem !== null) {
                $handled = $this->menuSystem->handleInput($key);
                if ($handled) {
                    $this->checkScreenChange();
                    continue;
                }
            }

            // Priority 2: Global shortcuts (Ctrl+C, custom shortcuts)
            if (isset($this->globalShortcuts[$key])) {
                $callback = $this->globalShortcuts[$key];

                $callback($this->screenManager);

                // Invalidate renderer after global shortcut (likely changed screen)
                $this->checkScreenChange();
                continue;
            }

            // Priority 4: Route to current screen
            $handled = $this->screenManager->handleInput($key);

            // Check if screen changed (e.g., via navigation in the current screen)
            $this->checkScreenChange();

            // Priority 5: ESC to go back (if not handled by screen)
            if (!$handled && $key === 'ESCAPE') {
                if ($this->screenManager->getDepth() > 1) {
                    $this->screenManager->popScreen();
                    $this->checkScreenChange();
                }
            }
        }
    }

    /**
     * Check if screen depth changed and invalidate renderer if so
     */
    private function checkScreenChange(): void
    {
        $currentDepth = $this->screenManager->getDepth();

        if ($currentDepth !== $this->lastScreenDepth) {
            // Screen changed - invalidate renderer to force full redraw
            $this->renderer->invalidate();
            $this->lastScreenDepth = $currentDepth;
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

        // 1. Render menu bar at top
        $menuHeight = 1;
        if ($this->menuSystem !== null) {
            $this->menuSystem->render($this->renderer, 0, 0, $width, $height);
        }

        // 2. Render current screen below menu
        $this->screenManager->render($this->renderer, 0, $menuHeight, $width, $height - $menuHeight);

        // 3. Render dropdown LAST (on top of everything)
        if ($this->menuSystem !== null) {
            $this->menuSystem->renderDropdown($this->renderer, 0, 0, $width, $height);
        }

        // End frame (flush to terminal)
        $this->renderer->endFrame();
    }

    /**
     * Register F-key shortcut for menu navigation
     *
     * When F-key is pressed, navigate to first screen in that menu category.
     */
    private function registerMenuShortcut(MenuDefinition $menu): void
    {
        if ($menu->fkey === null || $this->screenRegistry === null) {
            return;
        }

        // Special handling for F10 (Quit)
        if ($menu->fkey === 'F10') {
            $this->registerGlobalShortcut('F10', fn() => $this->stop());
            return;
        }

        // Get first screen from menu
        $firstItem = $menu->getFirstItem();
        if ($firstItem === null || !$firstItem->isScreen()) {
            return;
        }

        $screenName = $firstItem->screenName;

        // Register shortcut to navigate to this screen
        $this->registerGlobalShortcut($menu->fkey, function (ScreenManager $screenManager) use ($screenName): void {
            $screen = $this->screenRegistry?->getScreen($screenName);

            if ($screen === null) {
                return;
            }

            // Check if we're already on this screen
            $current = $screenManager->getCurrentScreen();
            if ($current !== null && $current::class === $screen::class) {
                // Already on this screen, don't navigate
                return;
            }

            // Check if screen is already in stack
            $found = false;
            foreach ($screenManager->getStack() as $stackScreen) {
                if ($stackScreen::class === $screen::class) {
                    $found = true;
                    break;
                }
            }

            if ($found) {
                // Screen already in stack, pop until we reach it
                $screenManager->popUntil(static fn($s): bool => $s::class === $screen::class);
            } else {
                // Not in stack, push it
                $screenManager->pushScreen($screen);
            }
        });
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
