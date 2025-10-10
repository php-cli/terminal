<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

/**
 * Light theme (white/light background)
 */
final class LightTheme extends AbstractTheme
{
    public function getName(): string
    {
        return 'Light';
    }

    public function getNormalBg(): string
    {
        return ColorScheme::BG_WHITE;
    }

    public function getNormalFg(): string
    {
        return ColorScheme::FG_BLACK;
    }

    public function getMenuBg(): string
    {
        return ColorScheme::BG_BLUE;
    }

    public function getMenuFg(): string
    {
        return ColorScheme::FG_WHITE;
    }

    public function getMenuHotkey(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW, ColorScheme::BOLD);
    }

    public function getStatusBg(): string
    {
        return ColorScheme::BG_BLUE;
    }

    public function getStatusFg(): string
    {
        return ColorScheme::FG_WHITE;
    }

    public function getStatusKey(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW, ColorScheme::BOLD);
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
        return ColorScheme::combine(ColorScheme::BG_WHITE, ColorScheme::FG_BRIGHT_BLUE, ColorScheme::BOLD);
    }

    public function getInactiveBorder(): string
    {
        return ColorScheme::combine(ColorScheme::BG_WHITE, ColorScheme::FG_BLACK);
    }

    public function getInputBg(): string
    {
        return ColorScheme::combine(ColorScheme::BG_GRAY, ColorScheme::BOLD);
    }

    public function getInputFg(): string
    {
        return ColorScheme::combine(ColorScheme::FG_BLACK, ColorScheme::BOLD);
    }

    public function getInputCursor(): string
    {
        return ColorScheme::REVERSE;
    }

    public function getScrollbar(): string
    {
        return ColorScheme::combine(ColorScheme::BG_WHITE, ColorScheme::FG_BLACK);
    }

    public function getErrorText(): string
    {
        return ColorScheme::combine(ColorScheme::BG_WHITE, ColorScheme::FG_BRIGHT_RED, ColorScheme::BOLD);
    }

    public function getWarningText(): string
    {
        return ColorScheme::combine(ColorScheme::BG_WHITE, ColorScheme::FG_BRIGHT_YELLOW, ColorScheme::BOLD);
    }

    public function getHighlightText(): string
    {
        return ColorScheme::combine(ColorScheme::BG_WHITE, ColorScheme::FG_BLUE, ColorScheme::BOLD);
    }
}
