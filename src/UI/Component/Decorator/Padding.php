<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Decorator;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\ComponentInterface;
use Butschster\Commander\UI\Component\Container\AbstractLayoutComponent;

/**
 * Padding decorator - adds spacing around a component
 *
 * Usage:
 *   new Padding($content, top: 1, right: 2, bottom: 1, left: 2)
 *   Padding::all($content, 2)
 *   Padding::symmetric($content, vertical: 1, horizontal: 2)
 *   Padding::only($content, top: 1, left: 2)
 */
final class Padding extends AbstractLayoutComponent
{
    public function __construct(
        private readonly ComponentInterface $content,
        private readonly int $top = 0,
        private readonly int $right = 0,
        private readonly int $bottom = 0,
        private readonly int $left = 0,
    ) {
        $this->addChild($content);
    }

    /**
     * Create padding with same value on all sides
     */
    public static function all(ComponentInterface $content, int $padding): self
    {
        return new self($content, $padding, $padding, $padding, $padding);
    }

    /**
     * Create padding with symmetric values
     */
    public static function symmetric(
        ComponentInterface $content,
        int $vertical = 0,
        int $horizontal = 0,
    ): self {
        return new self($content, $vertical, $horizontal, $vertical, $horizontal);
    }

    /**
     * Create padding with only specified sides
     */
    public static function only(
        ComponentInterface $content,
        int $top = 0,
        int $right = 0,
        int $bottom = 0,
        int $left = 0,
    ): self {
        return new self($content, $top, $right, $bottom, $left);
    }

    #[\Override]
    public function measure(int $availableWidth, int $availableHeight): array
    {
        // Calculate space available for content after padding
        $contentWidth = \max(0, $availableWidth - $this->left - $this->right);
        $contentHeight = \max(0, $availableHeight - $this->top - $this->bottom);

        // Measure content
        $contentSize = ['width' => $contentWidth, 'height' => $contentHeight];

        if ($this->content instanceof \Butschster\Commander\UI\Component\LayoutComponent) {
            $contentSize = $this->content->measure($contentWidth, $contentHeight);
        }

        // Add padding back to get total size
        $this->measuredWidth = $contentSize['width'] + $this->left + $this->right;
        $this->measuredHeight = $contentSize['height'] + $this->top + $this->bottom;

        return [
            'width' => $this->measuredWidth,
            'height' => $this->measuredHeight,
        ];
    }

    #[\Override]
    public function layout(int $width, int $height): void
    {
        parent::layout($width, $height);

        // Calculate content area after padding
        $contentWidth = \max(0, $width - $this->left - $this->right);
        $contentHeight = \max(0, $height - $this->top - $this->bottom);

        // Layout content
        if ($this->content instanceof \Butschster\Commander\UI\Component\LayoutComponent) {
            $this->content->layout($contentWidth, $contentHeight);
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        // Delegate input to content
        return $this->content->isFocused() && $this->content->handleInput($key);
    }

    #[\Override]
    public function setFocused(bool $focused): void
    {
        parent::setFocused($focused);

        // Propagate focus to content
        $this->content->setFocused($focused);
    }

    #[\Override]
    public function getMinSize(): array
    {
        $contentMinSize = $this->content->getMinSize();

        return [
            'width' => $contentMinSize['width'] + $this->left + $this->right,
            'height' => $contentMinSize['height'] + $this->top + $this->bottom,
        ];
    }

    protected function draw(Renderer $renderer, int $x, int $y): void
    {
        // Calculate content position (with padding offset)
        $contentX = $x + $this->left;
        $contentY = $y + $this->top;
        $contentWidth = \max(0, $this->allocatedWidth - $this->left - $this->right);
        $contentHeight = \max(0, $this->allocatedHeight - $this->top - $this->bottom);

        // Render content at padded position
        $this->content->render($renderer, $contentX, $contentY, $contentWidth, $contentHeight);
    }
}
