<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component;

/**
 * Extended interface for layout-aware components
 *
 * Layout components participate in a two-phase rendering process:
 * 1. measure() - calculate desired size based on available space
 * 2. layout() - apply final size allocated by parent
 * 3. render() - draw content with allocated dimensions
 */
interface LayoutComponent extends ComponentInterface
{
    /**
     * Calculate the component's desired size
     *
     * Called by parent before layout() to determine space requirements.
     * Component should return its preferred size considering:
     * - Content size
     * - Children's measured sizes
     * - Constraints (min/max)
     *
     * @param int $availableWidth Maximum width available
     * @param int $availableHeight Maximum height available
     * @return array{width: int, height: int} Desired size
     */
    public function measure(int $availableWidth, int $availableHeight): array;

    /**
     * Apply the final allocated size
     *
     * Called by parent after measure() to set actual dimensions.
     * Component should:
     * - Store allocated dimensions
     * - Layout children if container
     * - Prepare for rendering
     *
     * @param int $width Allocated width
     * @param int $height Allocated height
     */
    public function layout(int $width, int $height): void;
}
