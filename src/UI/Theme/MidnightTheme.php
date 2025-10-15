<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

/**
 * Classic Midnight Commander theme (blue background)
 */
final class MidnightTheme extends AbstractTheme
{
    public function getName(): string
    {
        return 'Midnight Commander';
    }

    public function getNormalBg(): string
    {
        return ColorScheme::BG_BLUE;
    }

    public function getNormalFg(): string
    {
        return ColorScheme::FG_WHITE;
    }

    public function getMenuBg(): string
    {
        return ColorScheme::BG_CYAN;
    }

    public function getMenuFg(): string
    {
        return ColorScheme::FG_BLACK;
    }

    public function getMenuHotkey(): string
    {
        return ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::FG_YELLOW);
    }

    public function getStatusBg(): string
    {
        return ColorScheme::BG_CYAN;
    }

    public function getStatusFg(): string
    {
        return ColorScheme::FG_BLACK;
    }

    public function getStatusKey(): string
    {
        return ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::BOLD, ColorScheme::FG_WHITE);
    }

    public function getSelectedBg(): string
    {
        return ColorScheme::BG_CYAN;
    }

    public function getSelectedFg(): string
    {
        return ColorScheme::FG_WHITE;
    }

    #[\Override]
    public function getSelectedText(): string
    {
        return ColorScheme::combine(
            ColorScheme::BG_CYAN,
            ColorScheme::FG_WHITE,
            ColorScheme::BOLD,
        );
    }

    public function getActiveBorder(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_BRIGHT_WHITE);
    }

    public function getInactiveBorder(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_GRAY);
    }

    public function getInputBg(): string
    {
        return ColorScheme::BG_BLACK;
    }

    public function getInputFg(): string
    {
        return ColorScheme::FG_YELLOW;
    }

    public function getInputCursor(): string
    {
        return ColorScheme::REVERSE;
    }

    public function getScrollbar(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_CYAN);
    }

    public function getErrorText(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_RED);
    }

    public function getWarningText(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW);
    }

    public function getHighlightText(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_WHITE, ColorScheme::BOLD);
    }
}
