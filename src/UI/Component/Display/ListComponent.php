<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Display;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Component\Concerns\HandlesInput;

/**
 * Scrollable list component with keyboard navigation
 */
final class ListComponent extends AbstractComponent
{
    use HandlesInput;

    private int $selectedIndex = 0;
    private int $scrollOffset = 0;
    private int $visibleRows = 0;

    /** @var \Closure(string, int): void */
    private \Closure $onSelect;

    /** @var \Closure(string|null, int): void */
    private \Closure $onChange;

    private readonly Scrollbar $scrollbar;

    /**
     * @param array<string> $items
     */
    public function __construct(private array $items = [])
    {
        $this->scrollbar = new Scrollbar();
        $this->onSelect = static fn(string $item, int $index) => null;
        $this->onChange = static fn(?string $item, int $index) => null;
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

        ($this->onChange)($this->getSelectedItem(), $this->selectedIndex);
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
        $this->onSelect = $callback(...);
    }

    /**
     * Set callback for when selection changes (arrow keys)
     *
     * @param callable(string|null, int): void $callback
     */
    public function onChange(callable $callback): void
    {
        $this->onChange = $callback(...);
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);
        $this->visibleRows = $height;
        $theme = $renderer->getThemeContext();

        if (empty($this->items)) {
            // Show empty state
            $emptyText = '(No items)';
            $emptyX = $x + (int) (($width - \mb_strlen($emptyText)) / 2);
            $emptyY = $y + (int) ($height / 2);

            $renderer->writeAt($emptyX, $emptyY, $emptyText, $theme->getNormalText());
            return;
        }

        // Check if scrollbar is needed and reserve space for it
        $needsScrollbar = Scrollbar::needsScrollbar(\count($this->items), $this->visibleRows);
        $contentWidth = $needsScrollbar ? $width - 1 : $width;

        // Calculate visible range
        $endIndex = \min(
            $this->scrollOffset + $this->visibleRows,
            \count($this->items),
        );

        // Render items
        for ($i = $this->scrollOffset; $i < $endIndex; $i++) {
            $rowY = $y + ($i - $this->scrollOffset);
            $item = $this->items[$i];

            // Truncate or pad item to fit content width (excluding scrollbar)
            $displayText = \mb_substr($item, 0, $contentWidth);
            $displayText = \str_pad($displayText, $contentWidth);

            // Highlight selected item
            if ($i === $this->selectedIndex && $this->isFocused()) {
                $renderer->writeAt(
                    $x,
                    $rowY,
                    $displayText,
                    $theme->getSelectedText(),
                );
            } else {
                $renderer->writeAt(
                    $x,
                    $rowY,
                    $displayText,
                    $theme->getNormalText(),
                );
            }
        }

        // Draw scrollbar if needed
        if ($needsScrollbar) {
            $this->scrollbar->render(
                $renderer,
                x: $x + $contentWidth,
                y: $y,
                height: $height,
                theme: $theme,
                totalItems: \count($this->items),
                visibleItems: $this->visibleRows,
                scrollOffset: $this->scrollOffset,
            );
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        $input = KeyInput::from($key);
        $oldIndex = $this->selectedIndex;

        // Handle vertical navigation using trait
        $handled = $this->handleVerticalNavigation(
            $input,
            $this->selectedIndex,
            \count($this->items),
            $this->visibleRows,
        );

        if ($handled !== null) {
            $this->adjustScroll();
            if ($oldIndex !== $this->selectedIndex) {
                ($this->onChange)($this->getSelectedItem(), $this->selectedIndex);
            }
            return true;
        }

        // Handle Enter key
        if ($input->is(Key::ENTER)) {
            return $this->handleEnter();
        }

        return false;
    }

    #[\Override]
    public function getMinSize(): array
    {
        return ['width' => 20, 'height' => 5];
    }

    private function handleEnter(): bool
    {
        $item = $this->getSelectedItem();
        if ($item !== null) {
            ($this->onSelect)($item, $this->selectedIndex);
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
}
