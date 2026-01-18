<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Layout;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
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

    /** @var \Closure(MenuItem): void */
    private \Closure $onSelect;

    /** @var \Closure(): void */
    private \Closure $onClose;

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
        $this->onSelect = static fn(MenuItem $item) => null;
        $this->onClose = static fn() => null;
    }

    /**
     * Set callback for item selection
     */
    public function onSelect(callable $callback): void
    {
        $this->onSelect = $callback(...);
    }

    /**
     * Set callback for menu close
     */
    public function onClose(callable $callback): void
    {
        $this->onClose = $callback(...);
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);

        // Calculate dropdown dimensions with borders
        $dropdownWidth = $this->calculateWidth();
        $dropdownHeight = \min(\count($this->items), 15) + 2; // Max 15 items + top/bottom borders

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

        // Fill background with cyan and white foreground
        $dropdownBg = ColorScheme::combine(ColorScheme::RESET, ColorScheme::BG_GREEN, ColorScheme::FG_WHITE);
        $renderer->fillRect(
            $dropdownX,
            $dropdownY,
            $dropdownWidth,
            $dropdownHeight,
            ' ',
            $dropdownBg,
        );

        // Draw border
        $this->drawBorder($renderer, $dropdownX, $dropdownY, $dropdownWidth, $dropdownHeight);

        // Render menu items (inside borders, starting at y + 1)
        $visibleHeight = $dropdownHeight - 2; // Subtract top/bottom borders
        $startIndex = $this->scrollOffset;
        $endIndex = \min($startIndex + $visibleHeight, \count($this->items));

        for ($i = $startIndex; $i < $endIndex; $i++) {
            $item = $this->items[$i];
            $itemY = $dropdownY + 1 + ($i - $startIndex); // +1 for top border
            $isSelected = ($i === $this->selectedIndex);

            $this->renderItem($renderer, $dropdownX, $itemY, $dropdownWidth, $item, $isSelected);
        }

        // Draw scrollbar if needed
        if (\count($this->items) > $visibleHeight) {
            $this->drawScrollbar($renderer, $dropdownX, $dropdownY, $dropdownWidth, $dropdownHeight);
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        $input = KeyInput::from($key);

        return match (true) {
            $input->is(Key::UP) => $this->moveSelection(-1) ?? true,
            $input->is(Key::DOWN) => $this->moveSelection(1) ?? true,
            $input->is(Key::ENTER) => $this->selectCurrentItem() ?? true,
            $input->is(Key::ESCAPE) => $this->close() ?? true,
            $input->isSpace() => $this->selectCurrentItem() ?? true,
            $input->isPrintable() => $this->handleHotkey(\mb_strtolower($input->raw)),
            default => false,
        };
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

        ($this->onSelect)($item);

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
        ($this->onClose)();
    }

    /**
     * Calculate dropdown width based on longest item
     */
    private function calculateWidth(): int
    {
        $minWidth = 25; // Minimum width (~150px at typical terminal font size)
        $maxWidth = $minWidth;

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

        return \min(\max($maxWidth, $minWidth), 50); // Min 25, Max 50
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
            // Draw separator line (horizontal line inside borders)
            $line = '├' . \str_repeat('─', $width - 2) . '┤';
            $renderer->writeAt(
                $x,
                $y,
                $line,
                ColorScheme::combine(ColorScheme::RESET, ColorScheme::BG_CYAN, ColorScheme::FG_WHITE),
            );
            return;
        }

        // Selected item: black bg, white fg, bold
        // Normal item: cyan bg, white fg
        $color = $isSelected && $this->isFocused()
            ? ColorScheme::combine(ColorScheme::RESET, ColorScheme::BG_BLACK, ColorScheme::FG_WHITE, ColorScheme::BOLD)
            : ColorScheme::combine(ColorScheme::RESET, ColorScheme::BG_CYAN, ColorScheme::FG_WHITE);

        // Clear line (inside borders, so width - 2)
        $renderer->fillRect($x + 1, $y, $width - 2, 1, ' ', $color);

        // Render item label with left padding (2 spaces from left edge for border)
        $label = ' ' . $item->label;
        $renderer->writeAt($x + 1, $y, $label, $color);

        // Render hotkey hint if present (right-aligned before right border)
        if ($item->hotkey !== null) {
            $hotkey = \strtoupper($item->hotkey) . ' ';
            $hotkeyX = $x + $width - \mb_strlen($hotkey) - 1; // -1 for border

            // Hotkey color: same background as item, but yellow text when not selected
            $hotkeyColor = $isSelected && $this->isFocused()
                ? ColorScheme::combine(
                    ColorScheme::RESET,
                    ColorScheme::BG_BLACK,
                    ColorScheme::FG_WHITE,
                    ColorScheme::BOLD,
                )
                : ColorScheme::combine(ColorScheme::RESET, ColorScheme::BG_CYAN, ColorScheme::FG_YELLOW);

            $renderer->writeAt(
                $hotkeyX,
                $y,
                $hotkey,
                $hotkeyColor,
            );
        }

        // Render submenu indicator if present
        if ($item->isSubmenu()) {
            $renderer->writeAt($x + $width - 2, $y, '►', $color);
        }
    }

    /**
     * Draw border around dropdown
     */
    private function drawBorder(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $borderColor = ColorScheme::combine(ColorScheme::RESET, ColorScheme::BG_CYAN, ColorScheme::FG_WHITE);

        // Top border
        $renderer->writeAt($x, $y, '┌' . \str_repeat('─', $width - 2) . '┐', $borderColor);

        // Side borders
        for ($i = 1; $i < $height - 1; $i++) {
            $renderer->writeAt($x, $y + $i, '│', $borderColor);
            $renderer->writeAt($x + $width - 1, $y + $i, '│', $borderColor);
        }

        // Bottom border
        $renderer->writeAt($x, $y + $height - 1, '└' . \str_repeat('─', $width - 2) . '┘', $borderColor);
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
        $scrollbarX = $x + $width - 2; // -2 for border
        $contentHeight = $height - 2; // Subtract top/bottom borders
        $totalItems = \count($this->items);

        // Calculate thumb size and position
        $thumbSize = \max(1, (int) ($contentHeight * $contentHeight / $totalItems));
        $thumbPos = (int) ($this->scrollOffset * $contentHeight / $totalItems);

        for ($i = 0; $i < $contentHeight; $i++) {
            $char = ($i >= $thumbPos && $i < $thumbPos + $thumbSize) ? '█' : '│';
            $renderer->writeAt(
                $scrollbarX,
                $y + 1 + $i, // +1 to start below top border
                $char,
                ColorScheme::combine(ColorScheme::RESET, ColorScheme::BG_CYAN, ColorScheme::FG_WHITE),
            );
        }
    }
}
