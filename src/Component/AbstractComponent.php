<?php

declare(strict_types=1);

namespace Butschster\Commander\Component;

use Butschster\Commander\Service\Renderer;

/**
 * Base implementation of ComponentInterface with common functionality
 */
abstract class AbstractComponent implements ComponentInterface
{
    protected bool $focused = false;

    /** @var ComponentInterface[] */
    protected array $children = [];

    protected ?ComponentInterface $parent = null;

    protected int $x = 0;
    protected int $y = 0;
    protected int $width = 0;
    protected int $height = 0;

    public function setFocused(bool $focused): void
    {
        $this->focused = $focused;

        // Propagate focus change to children if needed
        if (!$focused) {
            foreach ($this->children as $child) {
                $child->setFocused(false);
            }
        }
    }

    public function isFocused(): bool
    {
        return $this->focused;
    }

    public function update(): void
    {
        // Update all children
        foreach ($this->children as $child) {
            $child->update();
        }
    }

    /**
     * Add a child component
     */
    public function addChild(ComponentInterface $component): void
    {
        $this->children[] = $component;

        if ($component instanceof self) {
            $component->parent = $this;
        }
    }

    /**
     * Remove a child component
     */
    public function removeChild(ComponentInterface $component): void
    {
        $this->children = array_filter(
            $this->children,
            fn(ComponentInterface $child) => $child !== $component,
        );

        if ($component instanceof self) {
            $component->parent = null;
        }
    }

    /**
     * Get all children
     *
     * @return ComponentInterface[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Get parent component
     */
    public function getParent(): ?ComponentInterface
    {
        return $this->parent;
    }

    /**
     * Store component bounds for later use
     */
    protected function setBounds(int $x, int $y, int $width, int $height): void
    {
        $this->x = $x;
        $this->y = $y;
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * Get component bounds
     *
     * @return array{x: int, y: int, width: int, height: int}
     */
    public function getBounds(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    public function getMinSize(): array
    {
        return ['width' => 10, 'height' => 3];
    }

    /**
     * Default implementation: propagate input to focused child
     */
    public function handleInput(string $key): bool
    {
        foreach ($this->children as $child) {
            if ($child->isFocused() && $child->handleInput($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render all children (helper method)
     */
    protected function renderChildren(Renderer $renderer): void
    {
        foreach ($this->children as $child) {
            $child->render(
                $renderer,
                $this->x,
                $this->y,
                $this->width,
                $this->height,
            );
        }
    }
}
