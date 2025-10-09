<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Container;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\ComponentInterface;
use Butschster\Commander\UI\Component\LayoutComponent;

/**
 * Grid layout - arranges children in columns or rows
 *
 * Usage:
 *   $grid = new GridLayout(columns: ['30%', '70%']);
 *   $grid->setColumn(0, $sidebar);
 *   $grid->setColumn(1, $content);
 *
 * Or:
 *   $grid = new GridLayout(columns: ['1fr', '2fr', '1fr']);
 *   $grid->setColumn(0, $left)->setColumn(1, $center)->setColumn(2, $right);
 */
final class GridLayout extends AbstractLayoutComponent
{
    /** @var array<string|int> Column/row size specifications */
    private array $tracks;

    /** @var array<int, ComponentInterface|null> Components for each track */
    private array $components = [];

    /** @var array<int> Calculated sizes for each track */
    private array $calculatedSizes = [];

    private readonly bool $isColumnBased;

    /**
     * @param array<string|int>|null $columns Column size specifications (null for row-based)
     * @param array<string|int>|null $rows Row size specifications (null for column-based)
     * @param int $gap Space between tracks
     */
    public function __construct(
        ?array $columns = null,
        ?array $rows = null,
        private readonly int $gap = 0,
    ) {
        if ($columns !== null && $rows !== null) {
            throw new \InvalidArgumentException('Cannot specify both columns and rows');
        }

        if ($columns === null && $rows === null) {
            throw new \InvalidArgumentException('Must specify either columns or rows');
        }

        $this->isColumnBased = $columns !== null;
        $this->tracks = $columns ?? $rows;

        // Initialize components array
        foreach ($this->tracks as $index => $_) {
            $this->components[$index] = null;
        }
    }

    /**
     * Set component for a column
     */
    public function setColumn(int $index, ComponentInterface $component): self
    {
        if (!$this->isColumnBased) {
            throw new \LogicException('Cannot set column on row-based grid');
        }

        return $this->setTrack($index, $component);
    }

    /**
     * Set component for a row
     */
    public function setRow(int $index, ComponentInterface $component): self
    {
        if ($this->isColumnBased) {
            throw new \LogicException('Cannot set row on column-based grid');
        }

        return $this->setTrack($index, $component);
    }

    #[\Override]
    public function measure(int $availableWidth, int $availableHeight): array
    {
        $mainAxis = $this->isColumnBased ? $availableWidth : $availableHeight;
        $crossAxis = $this->isColumnBased ? $availableHeight : $availableWidth;

        // Calculate track sizes
        $this->calculatedSizes = $this->calculateTrackSizes($mainAxis);

        // Measure cross-axis (take maximum from all tracks)
        $maxCrossAxis = 0;

        foreach ($this->components as $index => $component) {
            if ($component === null) {
                continue;
            }

            $trackSize = $this->calculatedSizes[$index];

            if ($component instanceof LayoutComponent) {
                $measured = $this->isColumnBased
                    ? $component->measure($trackSize, $crossAxis)
                    : $component->measure($crossAxis, $trackSize);

                $componentCrossAxis = $this->isColumnBased ? $measured['height'] : $measured['width'];
                $maxCrossAxis = \max($maxCrossAxis, $componentCrossAxis);
            }
        }

        // Total main axis = sum of track sizes + gaps
        $totalMainAxis = \array_sum($this->calculatedSizes) + ($this->gap * (\count($this->tracks) - 1));

        $this->measuredWidth = $this->isColumnBased ? $totalMainAxis : $maxCrossAxis;
        $this->measuredHeight = $this->isColumnBased ? $maxCrossAxis : $totalMainAxis;

        return [
            'width' => $this->measuredWidth,
            'height' => $this->measuredHeight,
        ];
    }

    #[\Override]
    public function layout(int $width, int $height): void
    {
        parent::layout($width, $height);

        $mainAxis = $this->isColumnBased ? $width : $height;
        $crossAxis = $this->isColumnBased ? $height : $width;

        // Recalculate track sizes with allocated space
        $this->calculatedSizes = $this->calculateTrackSizes($mainAxis);

        // Layout each component
        foreach ($this->components as $index => $component) {
            if ($component === null) {
                continue;
            }

            $trackSize = $this->calculatedSizes[$index];

            if ($component instanceof LayoutComponent) {
                if ($this->isColumnBased) {
                    $component->layout($trackSize, $crossAxis);
                } else {
                    $component->layout($crossAxis, $trackSize);
                }
            }
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        // Delegate to focused component
        foreach ($this->components as $component) {
            if ($component !== null && $component->isFocused() && $component->handleInput($key)) {
                return true;
            }
        }

        return false;
    }

    #[\Override]
    public function getMinSize(): array
    {
        $totalMainAxis = 0;
        $maxCrossAxis = 0;

        foreach ($this->components as $component) {
            if ($component === null) {
                continue;
            }

            $minSize = $component->getMinSize();

            if ($this->isColumnBased) {
                $totalMainAxis += $minSize['width'];
                $maxCrossAxis = \max($maxCrossAxis, $minSize['height']);
            } else {
                $totalMainAxis += $minSize['height'];
                $maxCrossAxis = \max($maxCrossAxis, $minSize['width']);
            }
        }

        // Add gaps
        $totalMainAxis += $this->gap * (\count($this->tracks) - 1);

        return $this->isColumnBased
            ? ['width' => $totalMainAxis, 'height' => $maxCrossAxis]
            : ['width' => $maxCrossAxis, 'height' => $totalMainAxis];
    }

    protected function draw(Renderer $renderer, int $x, int $y): void
    {
        $currentPos = 0;

        foreach ($this->components as $index => $component) {
            if ($component === null) {
                // Move position for empty track
                $currentPos += $this->calculatedSizes[$index] + $this->gap;
                continue;
            }

            $trackSize = $this->calculatedSizes[$index];

            // Calculate component position
            $compX = $this->isColumnBased ? $x + $currentPos : $x;
            $compY = $this->isColumnBased ? $y : $y + $currentPos;

            // Calculate component dimensions
            $compWidth = $this->isColumnBased ? $trackSize : $this->allocatedWidth;
            $compHeight = $this->isColumnBased ? $this->allocatedHeight : $trackSize;

            // Render component
            $component->render($renderer, $compX, $compY, $compWidth, $compHeight);

            // Move position for next track
            $currentPos += $trackSize + $this->gap;
        }
    }

    /**
     * Set component for a track (internal)
     */
    private function setTrack(int $index, ComponentInterface $component): self
    {
        if (!isset($this->tracks[$index])) {
            throw new \OutOfBoundsException("Track index {$index} does not exist");
        }

        // Remove old component if exists
        if ($this->components[$index] !== null) {
            $this->removeChild($this->components[$index]);
        }

        $this->components[$index] = $component;
        $this->addChild($component);

        return $this;
    }

    /**
     * Calculate sizes for all tracks
     *
     * @param int $availableSpace Total space on main axis
     * @return array<int> Calculated size for each track
     */
    private function calculateTrackSizes(int $availableSpace): array
    {
        // Subtract gaps from available space
        $totalGaps = $this->gap * (\count($this->tracks) - 1);
        $spaceForTracks = \max(0, $availableSpace - $totalGaps);

        // Parse size specifications
        $units = [];
        $fixedTotal = 0;
        $flexTotal = 0.0;

        foreach ($this->tracks as $trackSpec) {
            $unit = SizeUnit::parse($trackSpec);
            $units[] = $unit;

            if ($unit->isFixed()) {
                $fixedTotal += $unit->calculate($spaceForTracks);
            } elseif ($unit->isFlexible()) {
                $flexTotal += $unit->value;
            }
        }

        // Calculate remaining space for flexible items
        $remainingSpace = \max(0, $spaceForTracks - $fixedTotal);

        // Calculate percentage sizes based on remaining space after fixed items
        $percentageTotal = 0;
        foreach ($units as $unit) {
            if ($unit->type === \Butschster\Commander\UI\Component\Container\SizeType::PERCENTAGE) {
                $percentageTotal += $unit->calculate($spaceForTracks);
            }
        }

        // Adjust remaining space for percentages
        $remainingSpace = \max(0, $remainingSpace - $percentageTotal);

        // Calculate fractional unit value
        $fractionUnit = $flexTotal > 0 ? $remainingSpace / $flexTotal : 0;

        // Calculate final sizes
        $sizes = [];
        foreach ($units as $unit) {
            $sizes[] = $unit->calculate($spaceForTracks, $fractionUnit);
        }

        return $sizes;
    }
}
