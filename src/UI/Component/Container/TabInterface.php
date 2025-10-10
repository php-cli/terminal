<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Container;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\ComponentInterface;

/**
 * Tab Interface
 *
 * Represents a single tab in a TabContainer.
 * Each tab manages its own content, state, and keyboard shortcuts.
 */
interface TabInterface extends ComponentInterface
{
    /**
     * Get tab title (displayed in tab header)
     */
    public function getTitle(): string;

    /**
     * Get tab-specific keyboard shortcuts for status bar
     *
     * @return array<string, string> Key => Description
     */
    public function getShortcuts(): array;

    /**
     * Called when tab becomes active
     */
    public function onActivate(): void;

    /**
     * Called when tab is deactivated
     */
    public function onDeactivate(): void;

    /**
     * Update tab state (called every frame)
     */
    public function update(): void;

    /**
     * Render tab content
     */
    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void;

    /**
     * Handle keyboard input
     *
     * @return bool True if input was handled
     */
    public function handleInput(string $key): bool;

    /**
     * Set focus state
     */
    public function setFocused(bool $focused): void;

    /**
     * Check if tab is focused
     */
    public function isFocused(): bool;
}
