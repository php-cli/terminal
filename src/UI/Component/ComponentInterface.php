<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component;

use Butschster\Commander\Infrastructure\Terminal\Renderer;

/**
 * Interface for all UI components
 */
interface ComponentInterface
{
    /**
     * Render the component to the given renderer
     *
     * @param Renderer $renderer The renderer to draw to
     * @param int $x Left position
     * @param int $y Top position
     * @param int $width Component width
     * @param int $height Component height
     */
    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void;

    /**
     * Handle keyboard input
     *
     * @param string $key The key pressed (e.g., 'UP', 'DOWN', 'ENTER', 'a', 'b')
     * @return bool True if the key was handled, false to propagate to parent
     */
    public function handleInput(string $key): bool;

    /**
     * Set focus state
     *
     * @param bool $focused Whether this component has focus
     */
    public function setFocused(bool $focused): void;

    /**
     * Check if component is focused
     */
    public function isFocused(): bool;

    /**
     * Update component state (called every frame)
     */
    public function update(): void;

    /**
     * Get component's desired minimum size
     *
     * @return array{width: int, height: int}
     */
    public function getMinSize(): array;
}
