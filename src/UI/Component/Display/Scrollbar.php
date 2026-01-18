<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Display;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Theme\ThemeContext;

/**
 * Scrollbar rendering helper for scrollable components.
 *
 * This is not a UI component (doesn't implement ComponentInterface),
 * but a rendering utility that can be composed into scrollable components.
 *
 * Example:
 * ```php
 * $scrollbar = new Scrollbar();
 *
 * // Check if scrollbar is needed (to reserve space)
 * $needsScrollbar = Scrollbar::needsScrollbar($totalItems, $visibleItems);
 * $contentWidth = $needsScrollbar ? $width - 1 : $width;
 *
 * // Render scrollbar
 * if ($needsScrollbar) {
 *     $scrollbar->render(
 *         $renderer,
 *         x: $x + $contentWidth,
 *         y: $y,
 *         height: $visibleItems,
 *         theme: $theme,
 *         totalItems: $totalItems,
 *         visibleItems: $visibleItems,
 *         scrollOffset: $scrollOffset,
 *     );
 * }
 * ```
 */
final class Scrollbar
{
    private const string THUMB_CHAR = '█';
    private const string TRACK_CHAR = '░';

    /**
     * Check if scrollbar is needed for given item counts.
     */
    public static function needsScrollbar(int $totalItems, int $visibleItems): bool
    {
        return $totalItems > $visibleItems;
    }

    /**
     * Render vertical scrollbar at specified position.
     */
    public function render(
        Renderer $renderer,
        int $x,
        int $y,
        int $height,
        ThemeContext $theme,
        int $totalItems,
        int $visibleItems,
        int $scrollOffset,
    ): void {
        if (!self::needsScrollbar($totalItems, $visibleItems)) {
            return;
        }

        $thumbHeight = \max(1, (int) ($height * $visibleItems / $totalItems));
        $thumbPosition = (int) ($height * $scrollOffset / $totalItems);

        for ($i = 0; $i < $height; $i++) {
            $isThumb = $i >= $thumbPosition && $i < $thumbPosition + $thumbHeight;
            $char = $isThumb ? self::THUMB_CHAR : self::TRACK_CHAR;
            $renderer->writeAt($x, $y + $i, $char, $theme->getScrollbar());
        }
    }
}
