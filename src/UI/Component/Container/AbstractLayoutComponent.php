<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Container;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Component\LayoutComponent;

/**
 * Base class for layout components
 *
 * Implements the two-phase rendering protocol:
 * 1. measure() - calculate desired size
 * 2. layout() - apply allocated size
 * 3. render() - draw with allocated size
 */
abstract class AbstractLayoutComponent extends AbstractComponent implements LayoutComponent
{
    /** Measured dimensions (from measure phase) */
    protected int $measuredWidth = 0;

    protected int $measuredHeight = 0;

    /** Allocated dimensions (from layout phase) */
    protected int $allocatedWidth = 0;

    protected int $allocatedHeight = 0;

    /**
     * Render component - orchestrates measure/layout/draw cycle
     */
    #[\Override]
    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);

        // Phase 1: Measure desired size
        $this->measure($width, $height);

        // Phase 2: Apply allocated size
        $this->layout($width, $height);

        // Phase 3: Actual rendering
        $this->draw($renderer, $x, $y);
    }

    /**
     * Default implementation: store allocated dimensions
     */
    #[\Override]
    public function layout(int $width, int $height): void
    {
        $this->allocatedWidth = $width;
        $this->allocatedHeight = $height;
    }

    /**
     * Default implementation: request all available space
     */
    #[\Override]
    public function measure(int $availableWidth, int $availableHeight): array
    {
        $this->measuredWidth = $availableWidth;
        $this->measuredHeight = $availableHeight;

        return [
            'width' => $this->measuredWidth,
            'height' => $this->measuredHeight,
        ];
    }

    /**
     * Actual drawing implementation
     *
     * Override this instead of render() in subclasses.
     * At this point, layout is complete and dimensions are known.
     *
     * @param Renderer $renderer Renderer to draw to
     * @param int $x Left position
     * @param int $y Top position
     */
    abstract protected function draw(Renderer $renderer, int $x, int $y): void;
}
