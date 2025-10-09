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
     * Render the entire screen
     *
     * @param Renderer $renderer The renderer to draw to
     */
    public function render(Renderer $renderer): void;
    
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
