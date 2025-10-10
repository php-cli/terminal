<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

/**
 * Modern dark theme (black background)
 */
final class DarkTheme extends AbstractTheme
{
    public function getName(): string
    {
        return 'Dark';
    }

    public function getNormalBg(): string
    {
        return ColorScheme::BG_BLACK;
    }

    public function getNormalFg(): string
    {
        return ColorScheme::FG_WHITE;
    }

    public function getMenuBg(): string
    {
        return ColorScheme::BG_GRAY;
    }

    public function getMenuFg(): string
    {
        return ColorScheme::FG_CYAN;
    }

    public function getMenuHotkey(): string
    {
        return ColorScheme::combine(ColorScheme::BG_GRAY, ColorScheme::FG_YELLOW, ColorScheme::BOLD);
    }

    public function getStatusBg(): string
    {
        return ColorScheme::BG_GRAY;
    }

    public function getStatusFg(): string
    {
        return ColorScheme::FG_WHITE;
    }

    public function getStatusKey(): string
    {
        return ColorScheme::combine(ColorScheme::BG_GRAY, ColorScheme::FG_CYAN, ColorScheme::BOLD);
    }

    public function getSelectedBg(): string
    {
        return ColorScheme::BG_BLUE;
    }

    public function getSelectedFg(): string
    {
        return ColorScheme::FG_WHITE;
    }

    public function getActiveBorder(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLACK, ColorScheme::FG_CYAN);
    }

    public function getInactiveBorder(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLACK, ColorScheme::FG_GRAY);
    }

    public function getInputBg(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::BOLD);
    }

    public function getInputFg(): string
    {
        return ColorScheme::combine(ColorScheme::FG_CYAN, ColorScheme::BOLD);
    }

    public function getInputCursor(): string
    {
        return ColorScheme::REVERSE;
    }

    public function getScrollbar(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLACK, ColorScheme::FG_GRAY);
    }

    public function getErrorText(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLACK, ColorScheme::FG_RED, ColorScheme::BOLD);
    }

    public function getWarningText(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLACK, ColorScheme::FG_YELLOW);
    }

    public function getHighlightText(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLACK, ColorScheme::FG_CYAN, ColorScheme::BOLD);
    }
}
