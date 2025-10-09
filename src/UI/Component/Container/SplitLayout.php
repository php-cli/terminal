<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Container;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\ComponentInterface;

/**
 * Split layout - convenient helper for 2-panel layouts
 *
 * Usage:
 *   $split = SplitLayout::horizontal($leftPanel, $rightPanel, 0.3);
 *   $split = SplitLayout::vertical($topPanel, $bottomPanel, 0.5);
 */
final class SplitLayout extends AbstractLayoutComponent
{
    private GridLayout $grid;

    private function __construct(
        Direction $direction,
        ComponentInterface $first,
        ComponentInterface $second,
        float $ratio,
        int $gap = 0,
    ) {
        // Calculate percentages from ratio
        $firstPercent = ($ratio * 100) . '%';
        $secondPercent = ((1.0 - $ratio) * 100) . '%';

        // Create grid based on direction
        if ($direction === Direction::HORIZONTAL) {
            $this->grid = new GridLayout(columns: [$firstPercent, $secondPercent], gap: $gap);
            $this->grid->setColumn(0, $first);
            $this->grid->setColumn(1, $second);
        } else {
            $this->grid = new GridLayout(rows: [$firstPercent, $secondPercent], gap: $gap);
            $this->grid->setRow(0, $first);
            $this->grid->setRow(1, $second);
        }

        $this->addChild($this->grid);
    }

    /**
     * Create horizontal split (left/right)
     *
     * @param ComponentInterface $left Left panel
     * @param ComponentInterface $right Right panel
     * @param float $ratio Ratio for left panel (0.0 to 1.0)
     * @param int $gap Space between panels
     */
    public static function horizontal(
        ComponentInterface $left,
        ComponentInterface $right,
        float $ratio = 0.5,
        int $gap = 0,
    ): self {
        return new self(Direction::HORIZONTAL, $left, $right, $ratio, $gap);
    }

    /**
     * Create vertical split (top/bottom)
     *
     * @param ComponentInterface $top Top panel
     * @param ComponentInterface $bottom Bottom panel
     * @param float $ratio Ratio for top panel (0.0 to 1.0)
     * @param int $gap Space between panels
     */
    public static function vertical(
        ComponentInterface $top,
        ComponentInterface $bottom,
        float $ratio = 0.5,
        int $gap = 0,
    ): self {
        return new self(Direction::VERTICAL, $top, $bottom, $ratio, $gap);
    }

    #[\Override]
    public function measure(int $availableWidth, int $availableHeight): array
    {
        return $this->grid->measure($availableWidth, $availableHeight);
    }

    #[\Override]
    public function layout(int $width, int $height): void
    {
        parent::layout($width, $height);
        $this->grid->layout($width, $height);
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        return $this->grid->handleInput($key);
    }

    #[\Override]
    public function getMinSize(): array
    {
        return $this->grid->getMinSize();
    }

    protected function draw(Renderer $renderer, int $x, int $y): void
    {
        $this->grid->render($renderer, $x, $y, $this->allocatedWidth, $this->allocatedHeight);
    }
}
