<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Concerns;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;

/**
 * Trait providing common input handling patterns for UI components.
 *
 * Reduces boilerplate for navigation, scrolling, and text editing.
 */
trait HandlesInput
{
    /**
     * Handle vertical list navigation (UP/DOWN/PAGE_UP/PAGE_DOWN/HOME/END).
     *
     * @param KeyInput $input The keyboard input
     * @param int $index Current selected index (modified by reference)
     * @param int $totalItems Total number of items in list
     * @param int $pageSize Number of items per page (for PAGE_UP/PAGE_DOWN)
     * @return bool|null True if handled, null if not a navigation key
     */
    protected function handleVerticalNavigation(
        KeyInput $input,
        int &$index,
        int $totalItems,
        int $pageSize,
    ): ?bool {
        if ($totalItems === 0) {
            return null;
        }

        $oldIndex = $index;

        $result = match ($input->key()) {
            Key::UP => $index > 0 ? --$index : $index,
            Key::DOWN => $index < $totalItems - 1 ? ++$index : $index,
            Key::PAGE_UP => $index = \max(0, $index - $pageSize),
            Key::PAGE_DOWN => $index = \min($totalItems - 1, $index + $pageSize),
            Key::HOME => $index = 0,
            Key::END => $index = $totalItems - 1,
            default => null,
        };

        if ($result === null) {
            return null;
        }

        return $oldIndex !== $index;
    }

    /**
     * Handle horizontal navigation (LEFT/RIGHT).
     *
     * @param KeyInput $input The keyboard input
     * @param int $index Current index (modified by reference)
     * @param int $totalItems Total number of items
     * @return bool|null True if handled and changed, false if at boundary, null if not navigation
     */
    protected function handleHorizontalNavigation(
        KeyInput $input,
        int &$index,
        int $totalItems,
    ): ?bool {
        if ($totalItems === 0) {
            return null;
        }

        $oldIndex = $index;

        $result = match ($input->key()) {
            Key::LEFT => $index > 0 ? --$index : $index,
            Key::RIGHT => $index < $totalItems - 1 ? ++$index : $index,
            default => null,
        };

        if ($result === null) {
            return null;
        }

        return $oldIndex !== $index;
    }

    /**
     * Handle text cursor navigation (LEFT/RIGHT/HOME/END).
     *
     * @param KeyInput $input The keyboard input
     * @param int $cursor Current cursor position (modified by reference)
     * @param int $textLength Length of the text
     * @return bool|null True if handled, null if not a cursor navigation key
     */
    protected function handleCursorNavigation(
        KeyInput $input,
        int &$cursor,
        int $textLength,
    ): ?bool {
        $oldCursor = $cursor;

        $result = match ($input->key()) {
            Key::LEFT => $cursor > 0 ? --$cursor : $cursor,
            Key::RIGHT => $cursor < $textLength ? ++$cursor : $cursor,
            Key::HOME => $cursor = 0,
            Key::END => $cursor = $textLength,
            default => null,
        };

        if ($result === null) {
            return null;
        }

        return $oldCursor !== $cursor;
    }

    /**
     * Handle scroll navigation (UP/DOWN/PAGE_UP/PAGE_DOWN/HOME/END).
     *
     * @param KeyInput $input The keyboard input
     * @param int $offset Current scroll offset (modified by reference)
     * @param int $totalLines Total number of lines
     * @param int $visibleLines Number of visible lines
     * @return bool|null True if handled, null if not a scroll key
     */
    protected function handleScrollNavigation(
        KeyInput $input,
        int &$offset,
        int $totalLines,
        int $visibleLines,
    ): ?bool {
        $maxOffset = \max(0, $totalLines - $visibleLines);
        $oldOffset = $offset;

        $result = match ($input->key()) {
            Key::UP => $offset > 0 ? --$offset : $offset,
            Key::DOWN => $offset < $maxOffset ? ++$offset : $offset,
            Key::PAGE_UP => $offset = \max(0, $offset - $visibleLines),
            Key::PAGE_DOWN => $offset = \min($maxOffset, $offset + $visibleLines),
            Key::HOME => $offset = 0,
            Key::END => $offset = $maxOffset,
            default => null,
        };

        if ($result === null) {
            return null;
        }

        return $oldOffset !== $offset;
    }

    /**
     * Handle Ctrl+Arrow navigation for tab switching.
     *
     * @param KeyInput $input The keyboard input
     * @param int $index Current tab index (modified by reference)
     * @param int $totalTabs Total number of tabs
     * @param bool $wrap Whether to wrap around at boundaries
     * @return bool|null True if handled, null if not Ctrl+Arrow
     */
    protected function handleCtrlArrowNavigation(
        KeyInput $input,
        int &$index,
        int $totalTabs,
        bool $wrap = true,
    ): ?bool {
        if ($totalTabs === 0) {
            return null;
        }

        $oldIndex = $index;

        if ($input->isCtrl(Key::LEFT)) {
            $index = $wrap
                ? ($index - 1 + $totalTabs) % $totalTabs
                : \max(0, $index - 1);
            return $oldIndex !== $index;
        }

        if ($input->isCtrl(Key::RIGHT)) {
            $index = $wrap
                ? ($index + 1) % $totalTabs
                : \min($totalTabs - 1, $index + 1);
            return $oldIndex !== $index;
        }

        return null;
    }
}
