<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Container;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\ComponentInterface;
use Butschster\Commander\UI\Component\LayoutComponent;

/**
 * Stack layout - arranges children in vertical or horizontal sequence
 *
 * Usage:
 *   $stack = new StackLayout(Direction::VERTICAL, gap: 1);
 *   $stack->addChild($header, size: 3);  // Fixed height
 *   $stack->addChild($content);          // Takes remaining space
 *   $stack->addChild($footer, size: 1);
 */
final class StackLayout extends AbstractLayoutComponent
{
    /** @var array<array{component: ComponentInterface, size: int|string|null}> */
    private array $items = [];

    /** @var array<int> Calculated sizes for each child */
    private array $calculatedSizes = [];

    public function __construct(
        private readonly Direction $direction = Direction::VERTICAL,
        private readonly int $gap = 0,
    ) {}

    /**
     * Add a child component
     *
     * @param ComponentInterface $component Child to add
     * @param int|string|null $size Fixed size or null for flexible
     *                              int: fixed size in cells
     *                              string: '50%', '2fr', '*'
     *                              null: takes remaining space (same as '*')
     */
    #[\Override]
    public function addChild(ComponentInterface $component, int|string|null $size = null): void
    {
        parent::addChild($component);

        $this->items[] = [
            'component' => $component,
            'size' => $size,
        ];
    }

    #[\Override]
    public function measure(int $availableWidth, int $availableHeight): array
    {
        if (empty($this->items)) {
            return ['width' => 0, 'height' => 0];
        }

        $isVertical = $this->direction === Direction::VERTICAL;
        $mainAxis = $isVertical ? $availableHeight : $availableWidth;
        $crossAxis = $isVertical ? $availableWidth : $availableHeight;

        // Calculate sizes for children
        $this->calculatedSizes = $this->calculateChildSizes($mainAxis);

        // Measure cross-axis (take maximum)
        $maxCrossAxis = 0;

        foreach ($this->items as $index => $item) {
            $childSize = $this->calculatedSizes[$index];

            // Measure child
            if ($item['component'] instanceof LayoutComponent) {
                $measured = $isVertical
                    ? $item['component']->measure($crossAxis, $childSize)
                    : $item['component']->measure($childSize, $crossAxis);

                $childCrossAxis = $isVertical ? $measured['width'] : $measured['height'];
                $maxCrossAxis = \max($maxCrossAxis, $childCrossAxis);
            }
        }

        // Total main axis = sum of sizes + gaps
        $totalMainAxis = \array_sum($this->calculatedSizes) + ($this->gap * (\count($this->items) - 1));

        $this->measuredWidth = $isVertical ? $maxCrossAxis : $totalMainAxis;
        $this->measuredHeight = $isVertical ? $totalMainAxis : $maxCrossAxis;

        return [
            'width' => $this->measuredWidth,
            'height' => $this->measuredHeight,
        ];
    }

    #[\Override]
    public function layout(int $width, int $height): void
    {
        parent::layout($width, $height);

        if (empty($this->items)) {
            return;
        }

        $isVertical = $this->direction === Direction::VERTICAL;
        $mainAxis = $isVertical ? $height : $width;
        $crossAxis = $isVertical ? $width : $height;

        // Recalculate sizes with actual allocated space
        $this->calculatedSizes = $this->calculateChildSizes($mainAxis);

        // Layout each child
        foreach ($this->items as $index => $item) {
            $childMainSize = $this->calculatedSizes[$index];

            if ($item['component'] instanceof LayoutComponent) {
                if ($isVertical) {
                    $item['component']->layout($crossAxis, $childMainSize);
                } else {
                    $item['component']->layout($childMainSize, $crossAxis);
                }
            }
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        // Delegate to focused child
        foreach ($this->items as $item) {
            if ($item['component']->isFocused() && $item['component']->handleInput($key)) {
                return true;
            }
        }

        return false;
    }

    #[\Override]
    public function getMinSize(): array
    {
        if (empty($this->items)) {
            return ['width' => 0, 'height' => 0];
        }

        $isVertical = $this->direction === Direction::VERTICAL;
        $totalMainAxis = 0;
        $maxCrossAxis = 0;

        foreach ($this->items as $item) {
            $minSize = $item['component']->getMinSize();

            if ($isVertical) {
                $totalMainAxis += $minSize['height'];
                $maxCrossAxis = \max($maxCrossAxis, $minSize['width']);
            } else {
                $totalMainAxis += $minSize['width'];
                $maxCrossAxis = \max($maxCrossAxis, $minSize['height']);
            }
        }

        // Add gaps
        $totalMainAxis += $this->gap * (\count($this->items) - 1);

        return $isVertical
            ? ['width' => $maxCrossAxis, 'height' => $totalMainAxis]
            : ['width' => $totalMainAxis, 'height' => $maxCrossAxis];
    }

    protected function draw(Renderer $renderer, int $x, int $y): void
    {
        $isVertical = $this->direction === Direction::VERTICAL;
        $currentPos = 0;

        foreach ($this->items as $index => $item) {
            $childSize = $this->calculatedSizes[$index];

            // Calculate child position
            $childX = $isVertical ? $x : $x + $currentPos;
            $childY = $isVertical ? $y + $currentPos : $y;

            // Calculate child dimensions
            $childWidth = $isVertical ? $this->allocatedWidth : $childSize;
            $childHeight = $isVertical ? $childSize : $this->allocatedHeight;

            // Render child
            $item['component']->render($renderer, $childX, $childY, $childWidth, $childHeight);

            // Move position for next child
            $currentPos += $childSize + $this->gap;
        }
    }

    /**
     * Calculate sizes for all children
     *
     * @param int $availableSpace Total space on main axis
     * @return array<int> Calculated size for each child
     */
    private function calculateChildSizes(int $availableSpace): array
    {
        // Subtract gaps from available space
        $totalGaps = $this->gap * (\count($this->items) - 1);
        $spaceForChildren = \max(0, $availableSpace - $totalGaps);

        // Parse size specifications
        $units = [];
        $fixedTotal = 0;
        $flexTotal = 0.0;

        foreach ($this->items as $item) {
            if ($item['size'] === null) {
                // null = flexible (1fr)
                $unit = SizeUnit::parse('*');
            } elseif (\is_int($item['size'])) {
                $unit = SizeUnit::parse($item['size']);
            } else {
                $unit = SizeUnit::parse($item['size']);
            }

            $units[] = $unit;

            if ($unit->isFixed()) {
                $fixedTotal += $unit->calculate($spaceForChildren);
            } elseif ($unit->isFlexible()) {
                $flexTotal += $unit->value;
            }
        }

        // Calculate remaining space for flexible items
        $remainingSpace = \max(0, $spaceForChildren - $fixedTotal);

        // Calculate percentage sizes based on remaining space after fixed items
        $percentageTotal = 0;
        foreach ($units as $unit) {
            if ($unit->type === \Butschster\Commander\UI\Component\Container\SizeType::PERCENTAGE) {
                $percentageTotal += $unit->calculate($spaceForChildren);
            }
        }

        // Adjust remaining space for percentages
        $remainingSpace = \max(0, $remainingSpace - $percentageTotal);

        // Calculate fractional unit value
        $fractionUnit = $flexTotal > 0 ? $remainingSpace / $flexTotal : 0;

        // Calculate final sizes
        $sizes = [];
        foreach ($units as $unit) {
            $sizes[] = $unit->calculate($spaceForChildren, $fractionUnit);
        }

        return $sizes;
    }
}
