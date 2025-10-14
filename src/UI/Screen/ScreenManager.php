<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Screen;

use Butschster\Commander\Infrastructure\Terminal\Renderer;

/**
 * Screen navigation manager
 *
 * Manages screen stack for navigation (push/pop)
 * Routes input and rendering to the active screen
 */
final class ScreenManager
{
    /** @var ScreenInterface[] Screen stack */
    private array $stack = [];

    /**
     * Push a new screen onto the stack
     */
    public function pushScreen(ScreenInterface $screen): void
    {
        // Deactivate current screen
        if (!empty($this->stack)) {
            $this->getCurrentScreen()?->onDeactivate();
        }

        $this->stack[] = $screen;
        $screen->onActivate();
    }

    /**
     * Pop the current screen from the stack
     *
     * @return ScreenInterface|null The popped screen, or null if stack is empty
     */
    public function popScreen(): ?ScreenInterface
    {
        if (empty($this->stack)) {
            return null;
        }

        $screen = \array_pop($this->stack);
        $screen->onDeactivate();

        // Activate the new current screen
        if (!empty($this->stack)) {
            $this->getCurrentScreen()?->onActivate();
        }

        return $screen;
    }

    /**
     * Replace the current screen with a new one
     */
    public function replaceScreen(ScreenInterface $screen): void
    {
        if (!empty($this->stack)) {
            $this->popScreen();
        }

        $this->pushScreen($screen);
    }

    /**
     * Get the current (top) screen
     */
    public function getCurrentScreen(): ?ScreenInterface
    {
        return empty($this->stack) ? null : \end($this->stack);
    }

    /**
     * Get all screens in the stack
     *
     * @return ScreenInterface[]
     */
    public function getStack(): array
    {
        return $this->stack;
    }

    /**
     * Get stack depth
     */
    public function getDepth(): int
    {
        return \count($this->stack);
    }

    /**
     * Clear all screens
     */
    public function clear(): void
    {
        while (!empty($this->stack)) {
            $this->popScreen();
        }
    }

    /**
     * Check if there are screens in the stack
     */
    public function hasScreens(): bool
    {
        return !empty($this->stack);
    }

    /**
     * Pop all screens until stack is empty or callback returns true
     *
     * @param callable(ScreenInterface): bool $callback
     */
    public function popUntil(callable $callback): void
    {
        while (!empty($this->stack)) {
            $screen = $this->getCurrentScreen();

            if ($screen && $callback($screen)) {
                break;
            }

            $this->popScreen();
        }
    }

    /**
     * Render the current screen
     *
     * @param int $x X position offset
     * @param int $y Y position offset
     * @param int $width Available width
     * @param int $height Available height
     */
    public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
    {
        $screen = $this->getCurrentScreen();

        if ($screen) {
            // If width/height not specified, use full renderer size minus offset
            if ($width === null || $height === null) {
                $size = $renderer->getSize();
                $width = $size['width'] - $x;
                $height = $size['height'] - $y;
            }

            $screen->render($renderer, $x, $y, $width, $height);
        }
    }

    /**
     * Handle input for the current screen
     *
     * @return bool True if input was handled
     */
    public function handleInput(string $key): bool
    {
        $screen = $this->getCurrentScreen();

        return $screen ? $screen->handleInput($key) : false;
    }

    /**
     * Update the current screen
     */
    public function update(): void
    {
        $screen = $this->getCurrentScreen();

        if ($screen) {
            $screen->update();
        }
    }
}
