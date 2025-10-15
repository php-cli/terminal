<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Layout;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Menu\MenuItem;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Dropdown menu component for displaying menu items
 */
final class MenuDropdown extends AbstractComponent
{
    private int $selectedIndex = 0;
    private int $scrollOffset = 0;

    /** @var callable|null Callback when item is selected */
    private $onSelect = null;

    /** @var callable|null Callback when menu is closed */
    private $onClose = null;

    /**
     * @param array<MenuItem> $items Menu items to display
     */
    public function __construct(
        private readonly array $items,
        private readonly int $menuX,
        private readonly int $menuY,
    ) {
        // Skip separators when initializing
        $this->selectedIndex = $this->findNextSelectableItem(0, 1);
    }

    /**
     * Set callback for item selection
     */
    public function onSelect(callable $callback): void
    {
        $this->onSelect = $callback;
    }

    /**
     * Set callback for menu close
     */
    public function onClose(callable $callback): void
    {
        $this->onClose = $callback;
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);

        // Calculate dropdown dimensions (no borders, but add 1 for top separator)
        $dropdownWidth = $this->calculateWidth();
        $dropdownHeight = \min(\count($this->items), 15) + 1; // Max 15 items + top separator

        // Position dropdown below menu bar
        $dropdownX = $this->menuX;
        $dropdownY = $this->menuY;

        // Ensure dropdown stays within screen bounds
        if ($dropdownX + $dropdownWidth > $width) {
            $dropdownX = $width - $dropdownWidth;
        }
        if ($dropdownY + $dropdownHeight > $height) {
            $dropdownY = $height - $dropdownHeight;
        }

        // Draw shadow
        $this->drawShadow($renderer, $dropdownX, $dropdownY, $dropdownWidth, $dropdownHeight);

        // Fill background with cyan and black foreground, with explicit RESET first
        $dropdownBg = ColorScheme::combine(ColorScheme::RESET, ColorScheme::BG_CYAN, ColorScheme::FG_BLACK);
        $renderer->fillRect(
            $dropdownX,
            $dropdownY,
            $dropdownWidth,
            $dropdownHeight,
            ' ',
            $dropdownBg,
        );

        // Draw top separator line
        $topSeparator = \str_repeat('─', $dropdownWidth - 2);
        $renderer->writeAt(
            $dropdownX + 1,
            $dropdownY,
            $topSeparator,
            ColorScheme::combine(ColorScheme::RESET, ColorScheme::BG_CYAN, ColorScheme::FG_GRAY),
        );

        // Render menu items (start below top separator at y + 1)
        $visibleHeight = $dropdownHeight - 1; // Subtract top separator
        $startIndex = $this->scrollOffset;
        $endIndex = \min($startIndex + $visibleHeight, \count($this->items));

        for ($i = $startIndex; $i < $endIndex; $i++) {
            $item = $this->items[$i];
            $itemY = $dropdownY + 1 + ($i - $startIndex); // +1 for top separator
            $isSelected = ($i === $this->selectedIndex);

            $this->renderItem($renderer, $dropdownX, $itemY, $dropdownWidth, $item, $isSelected);
        }

        // Draw scrollbar if needed (account for top separator)
        if (\count($this->items) > $visibleHeight) {
            $this->drawScrollbar($renderer, $dropdownX, $dropdownY, $dropdownWidth, $dropdownHeight);
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        switch ($key) {
            case 'UP':
                $this->moveSelection(-1);
                return true;

            case 'DOWN':
                $this->moveSelection(1);
                return true;

            case 'ENTER':
            case ' ':
                $this->selectCurrentItem();
                return true;

            case 'ESCAPE':
                $this->close();
                return true;

            default:
                // Check for hotkey match
                if (\mb_strlen($key) === 1) {
                    return $this->handleHotkey(\mb_strtolower($key));
                }
                return false;
        }
    }

    #[\Override]
    public function getMinSize(): array
    {
        return [
            'width' => $this->calculateWidth() + 10,
            'height' => \min(\count($this->items), 15) + 6,
        ];
    }

    /**
     * Move selection up or down
     */
    private function moveSelection(int $delta): void
    {
        $newIndex = $this->selectedIndex + $delta;
        $newIndex = $this->findNextSelectableItem($newIndex, $delta);

        if ($newIndex >= 0 && $newIndex < \count($this->items)) {
            $this->selectedIndex = $newIndex;
            $this->adjustScrollOffset();
        }
    }

    /**
     * Find next selectable item (skip separators)
     */
    private function findNextSelectableItem(int $startIndex, int $direction): int
    {
        $index = $startIndex;

        while ($index >= 0 && $index < \count($this->items)) {
            if (!$this->items[$index]->isSeparator()) {
                return $index;
            }
            $index += $direction;
        }

        // Wrap around if needed
        if ($direction > 0) {
            // Moving down, wrap to first
            for ($i = 0; $i < \count($this->items); $i++) {
                if (!$this->items[$i]->isSeparator()) {
                    return $i;
                }
            }
        } else {
            // Moving up, wrap to last
            for ($i = \count($this->items) - 1; $i >= 0; $i--) {
                if (!$this->items[$i]->isSeparator()) {
                    return $i;
                }
            }
        }

        return 0;
    }

    /**
     * Adjust scroll offset to keep selected item visible
     */
    private function adjustScrollOffset(): void
    {
        $visibleHeight = \min(\count($this->items), 15);

        if ($this->selectedIndex < $this->scrollOffset) {
            $this->scrollOffset = $this->selectedIndex;
        } elseif ($this->selectedIndex >= $this->scrollOffset + $visibleHeight) {
            $this->scrollOffset = $this->selectedIndex - $visibleHeight + 1;
        }
    }

    /**
     * Select current item and execute action
     */
    private function selectCurrentItem(): void
    {
        if ($this->selectedIndex < 0 || $this->selectedIndex >= \count($this->items)) {
            return;
        }

        $item = $this->items[$this->selectedIndex];

        if ($item->isSeparator()) {
            return;
        }

        if ($this->onSelect !== null) {
            ($this->onSelect)($item);
        }

        $this->close();
    }

    /**
     * Handle hotkey press
     */
    private function handleHotkey(string $key): bool
    {
        foreach ($this->items as $index => $item) {
            if ($item->getHotkey() === $key && !$item->isSeparator()) {
                $this->selectedIndex = $index;
                $this->selectCurrentItem();
                return true;
            }
        }

        return false;
    }

    /**
     * Close dropdown
     */
    private function close(): void
    {
        if ($this->onClose !== null) {
            ($this->onClose)();
        }
    }

    /**
     * Calculate dropdown width based on longest item
     */
    private function calculateWidth(): int
    {
        $maxWidth = 20; // Minimum width

        foreach ($this->items as $item) {
            if ($item->isSeparator()) {
                continue;
            }

            $itemWidth = \mb_strlen($item->label) + 4; // Add padding

            if ($item->hotkey !== null) {
                $itemWidth += 4; // Space for hotkey hint
            }

            $maxWidth = \max($maxWidth, $itemWidth);
        }

        return \min($maxWidth, 50); // Max width 50
    }

    /**
     * Render a single menu item
     */
    private function renderItem(
        Renderer $renderer,
        int $x,
        int $y,
        int $width,
        MenuItem $item,
        bool $isSelected,
    ): void {
        if ($item->isSeparator()) {
            // Draw separator line with gray on cyan (with left/right padding)
            $line = \str_repeat('─', $width - 2);
            $renderer->writeAt(
                $x + 1,
                $y,
                $line,
                ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::FG_GRAY),
            );
            return;
        }

        // Selected item: white bg, black fg, bold - with RESET first
        // Normal item: cyan bg, black fg - with RESET first
        $color = $isSelected && $this->isFocused()
            ? ColorScheme::combine(ColorScheme::RESET, ColorScheme::BG_WHITE, ColorScheme::FG_BLACK, ColorScheme::BOLD)
            : ColorScheme::combine(ColorScheme::RESET, ColorScheme::BG_CYAN, ColorScheme::FG_BLACK);

        // Clear line (full width for background)
        $renderer->fillRect($x, $y, $width, 1, ' ', $color);

        // Render item label with left padding (1 space from left edge)
        $label = ' ' . $item->label;
        $renderer->writeAt($x + 1, $y, $label, $color);

        // Render hotkey hint if present (with right padding)
        if ($item->hotkey !== null) {
            $hotkey = \strtoupper($item->hotkey) . ' ';
            $hotkeyX = $x + $width - \mb_strlen($hotkey) - 1;

            // Hotkey color: with RESET first for clean state
            // Selected: white bg, black fg
            // Normal: cyan bg, yellow fg
            $hotkeyColor = $isSelected && $this->isFocused()
                ? ColorScheme::combine(ColorScheme::RESET, ColorScheme::BG_WHITE, ColorScheme::FG_BLACK)
                : ColorScheme::combine(ColorScheme::RESET, ColorScheme::BG_CYAN, ColorScheme::FG_YELLOW);

            $renderer->writeAt(
                $hotkeyX,
                $y,
                $hotkey,
                $hotkeyColor,
            );
        }

        // Render submenu indicator if present (with right padding)
        if ($item->isSubmenu()) {
            $renderer->writeAt($x + $width - 2, $y, '►', $color);
        }
    }

    /**
     * Draw shadow effect
     */
    private function drawShadow(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $shadowColor = ColorScheme::combine(ColorScheme::BG_BLACK, ColorScheme::FG_BLACK);

        // Bottom shadow
        for ($i = 1; $i <= $width; $i++) {
            $renderer->writeAt($x + $i, $y + $height, '▄', $shadowColor);
        }

        // Right shadow
        for ($i = 1; $i < $height; $i++) {
            $renderer->writeAt($x + $width, $y + $i, '▌', $shadowColor);
        }

        // Corner
        $renderer->writeAt($x + $width, $y + $height, '▄', $shadowColor);
    }

    /**
     * Draw scrollbar indicator
     */
    private function drawScrollbar(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $scrollbarX = $x + $width - 1;
        $contentHeight = $height - 1; // Subtract top separator
        $totalItems = \count($this->items);

        // Calculate thumb size and position
        $thumbSize = \max(1, (int) ($contentHeight * $contentHeight / $totalItems));
        $thumbPos = (int) ($this->scrollOffset * $contentHeight / $totalItems);

        for ($i = 0; $i < $contentHeight; $i++) {
            $char = ($i >= $thumbPos && $i < $thumbPos + $thumbSize) ? '█' : '│';
            $renderer->writeAt(
                $scrollbarX,
                $y + 1 + $i, // +1 to start below top separator
                $char,
                ColorScheme::combine(ColorScheme::RESET, ColorScheme::BG_CYAN, ColorScheme::FG_BLACK),
            );
        }
    }
}
