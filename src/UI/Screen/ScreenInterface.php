<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Screen;

use Butschster\Commander\Infrastructure\Terminal\Renderer;

/**
 * Interface for full-screen views
 */
interface ScreenInterface
{
    /**
     * Get screen metadata for registration
     *
     * This static method allows automatic screen discovery and menu generation.
     */
    public function getMetadata(): ScreenMetadata;

    /**
     * Render the entire screen
     *
     * @param Renderer $renderer The renderer to draw to
     * @param int $x X position offset (typically 0)
     * @param int $y Y position offset (typically 1 to account for menu bar)
     * @param int $width Available width
     * @param int $height Available height
     */
    public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void;

    /**
     * Handle keyboard input
     *
     * @param string $key The key pressed
     * @return bool True if the key was handled
     */
    public function handleInput(string $key): bool;

    /**
     * Called when screen becomes active
     */
    public function onActivate(): void;

    /**
     * Called when screen is deactivated
     */
    public function onDeactivate(): void;

    /**
     * Update screen state (called every frame)
     */
    public function update(): void;

    /**
     * Get screen title
     */
    public function getTitle(): string;
}
