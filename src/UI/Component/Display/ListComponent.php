<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Display;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Scrollable list component with keyboard navigation
 */
final class ListComponent extends AbstractComponent
{
    /** @var array<string> */
    private array $items = [];

    private int $selectedIndex = 0;
    private int $scrollOffset = 0;
    private int $visibleRows = 0;

    /** @var callable|null Callback when item is selected */
    private $onSelect = null;

    /** @var callable|null Callback when selection changes */
    private $onChange = null;

    /**
     * @param array<string> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Set list items
     *
     * @param array<string> $items
     */
    public function setItems(array $items): void
    {
        $this->items = $items;
        $this->selectedIndex = 0;
        $this->scrollOffset = 0;

        // Trigger change callback
        if ($this->onChange !== null) {
            ($this->onChange)($this->getSelectedItem(), $this->selectedIndex);
        }
    }

    /**
     * Get selected item
     */
    public function getSelectedItem(): ?string
    {
        return $this->items[$this->selectedIndex] ?? null;
    }

    /**
     * Get selected index
     */
    public function getSelectedIndex(): int
    {
        return $this->selectedIndex;
    }

    /**
     * Set callback for when item is selected (Enter pressed)
     *
     * @param callable(string, int): void $callback
     */
    public function onSelect(callable $callback): void
    {
        $this->onSelect = $callback;
    }

    /**
     * Set callback for when selection changes (arrow keys)
     *
     * @param callable(string|null, int): void $callback
     */
    public function onChange(callable $callback): void
    {
        $this->onChange = $callback;
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);
        $this->visibleRows = $height;

        if (empty($this->items)) {
            // Show empty state
            $emptyText = '(No items)';
            $emptyX = $x + (int) (($width - mb_strlen($emptyText)) / 2);
            $emptyY = $y + (int) ($height / 2);

            $renderer->writeAt($emptyX, $emptyY, $emptyText, ColorScheme::NORMAL_TEXT);
            return;
        }

        // Calculate visible range
        $endIndex = min(
            $this->scrollOffset + $this->visibleRows,
            count($this->items),
        );

        // Render items
        for ($i = $this->scrollOffset; $i < $endIndex; $i++) {
            $rowY = $y + ($i - $this->scrollOffset);
            $item = $this->items[$i];

            // Truncate or pad item to fit width
            $displayText = mb_substr($item, 0, $width);
            $displayText = str_pad($displayText, $width);

            // Highlight selected item
            if ($i === $this->selectedIndex && $this->isFocused()) {
                $renderer->writeAt(
                    $x,
                    $rowY,
                    $displayText,
                    ColorScheme::SELECTED_TEXT,
                );
            } else {
                $renderer->writeAt(
                    $x,
                    $rowY,
                    $displayText,
                    ColorScheme::NORMAL_TEXT,
                );
            }
        }

        // Draw scrollbar if needed
        if (count($this->items) > $this->visibleRows) {
            $this->drawScrollbar($renderer, $x + $width - 1, $y, $height);
        }
    }

    public function handleInput(string $key): bool
    {
        $oldIndex = $this->selectedIndex;

        switch ($key) {
            case 'UP':
                if ($this->selectedIndex > 0) {
                    $this->selectedIndex--;
                    $this->adjustScroll();
                }
                break;

            case 'DOWN':
                if ($this->selectedIndex < count($this->items) - 1) {
                    $this->selectedIndex++;
                    $this->adjustScroll();
                }
                break;

            case 'PAGE_UP':
                $this->selectedIndex = max(0, $this->selectedIndex - $this->visibleRows);
                $this->adjustScroll();
                break;

            case 'PAGE_DOWN':
                $this->selectedIndex = min(
                    count($this->items) - 1,
                    $this->selectedIndex + $this->visibleRows,
                );
                $this->adjustScroll();
                break;

            case 'HOME':
                $this->selectedIndex = 0;
                $this->adjustScroll();
                break;

            case 'END':
                $this->selectedIndex = count($this->items) - 1;
                $this->adjustScroll();
                break;

            case 'ENTER':
                if ($this->onSelect !== null) {
                    $item = $this->getSelectedItem();
                    if ($item !== null) {
                        ($this->onSelect)($item, $this->selectedIndex);
                    }
                }
                return true;

            default:
                return false;
        }

        // Trigger change callback if selection changed
        if ($oldIndex !== $this->selectedIndex && $this->onChange !== null) {
            ($this->onChange)($this->getSelectedItem(), $this->selectedIndex);
        }

        return true;
    }

    /**
     * Adjust scroll offset to keep selected item visible
     */
    private function adjustScroll(): void
    {
        // Scroll up if selected item is above visible area
        if ($this->selectedIndex < $this->scrollOffset) {
            $this->scrollOffset = $this->selectedIndex;
        } // Scroll down if selected item is below visible area
        elseif ($this->selectedIndex >= $this->scrollOffset + $this->visibleRows) {
            $this->scrollOffset = $this->selectedIndex - $this->visibleRows + 1;
        }
    }

    /**
     * Draw scrollbar indicator
     */
    private function drawScrollbar(Renderer $renderer, int $x, int $y, int $height): void
    {
        $totalItems = count($this->items);

        // Calculate thumb size and position
        $thumbHeight = max(1, (int) ($height * $this->visibleRows / $totalItems));
        $thumbPosition = (int) ($height * $this->scrollOffset / $totalItems);

        for ($i = 0; $i < $height; $i++) {
            $char = ($i >= $thumbPosition && $i < $thumbPosition + $thumbHeight) ? '█' : '░';
            $renderer->writeAt($x, $y + $i, $char, ColorScheme::SCROLLBAR);
        }
    }

    public function getMinSize(): array
    {
        return ['width' => 20, 'height' => 5];
    }
}
