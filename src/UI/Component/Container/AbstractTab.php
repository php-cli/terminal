<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Container;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;

/**
 * Abstract base class for tabs
 *
 * Provides common functionality for tab implementations
 */
abstract class AbstractTab extends AbstractComponent implements TabInterface
{
    private bool $isActive = false;

    /**
     * Get tab title
     */
    abstract public function getTitle(): string;

    /**
     * Get tab-specific shortcuts
     */
    public function getShortcuts(): array
    {
        return [];
    }

    /**
     * Called when tab becomes active
     */
    public function onActivate(): void
    {
        $this->isActive = true;
        $this->onTabActivated();
    }

    /**
     * Called when tab is deactivated
     */
    public function onDeactivate(): void
    {
        $this->isActive = false;
        $this->onTabDeactivated();
    }

    /**
     * Check if tab is currently active
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Update tab state
     */
    #[\Override]
    public function update(): void
    {
        // Override in subclasses if needed
    }

    /**
     * Render tab content
     */
    abstract public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void;

    /**
     * Handle keyboard input
     */
    #[\Override]
    public function handleInput(string $key): bool
    {
        return false;
    }

    /**
     * Override this method to perform actions when tab is activated
     */
    protected function onTabActivated(): void
    {
        // Override in subclasses
    }

    /**
     * Override this method to perform actions when tab is deactivated
     */
    protected function onTabDeactivated(): void
    {
        // Override in subclasses
    }
}
