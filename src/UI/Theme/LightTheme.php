<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

/**
 * Light theme (white/light background)
 */
final class LightTheme extends AbstractTheme
{
    #[\Override]
    public function getName(): string
    {
        return 'Light';
    }

    #[\Override]
    public function getNormalBg(): string
    {
        return ColorScheme::BG_WHITE;
    }

    #[\Override]
    public function getNormalFg(): string
    {
        return ColorScheme::FG_BLACK;
    }

    #[\Override]
    public function getMenuBg(): string
    {
        return ColorScheme::BG_BLUE;
    }

    #[\Override]
    public function getMenuFg(): string
    {
        return ColorScheme::FG_WHITE;
    }

    #[\Override]
    public function getMenuHotkey(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW, ColorScheme::BOLD);
    }

    #[\Override]
    public function getStatusBg(): string
    {
        return ColorScheme::BG_BLUE;
    }

    #[\Override]
    public function getStatusFg(): string
    {
        return ColorScheme::FG_WHITE;
    }

    #[\Override]
    public function getStatusKey(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW, ColorScheme::BOLD);
    }

    #[\Override]
    public function getSelectedBg(): string
    {
        return ColorScheme::BG_BLUE;
    }

    #[\Override]
    public function getSelectedFg(): string
    {
        return ColorScheme::FG_WHITE;
    }

    #[\Override]
    public function getActiveBorder(): string
    {
        return ColorScheme::combine(ColorScheme::BG_WHITE, ColorScheme::FG_BRIGHT_BLUE, ColorScheme::BOLD);
    }

    #[\Override]
    public function getInactiveBorder(): string
    {
        return ColorScheme::combine(ColorScheme::BG_WHITE, ColorScheme::FG_BLACK);
    }

    #[\Override]
    public function getInputBg(): string
    {
        return ColorScheme::combine(ColorScheme::BG_GRAY, ColorScheme::BOLD);
    }

    #[\Override]
    public function getInputFg(): string
    {
        return ColorScheme::combine(ColorScheme::FG_BLACK, ColorScheme::BOLD);
    }

    #[\Override]
    public function getInputCursor(): string
    {
        return ColorScheme::REVERSE;
    }

    #[\Override]
    public function getScrollbar(): string
    {
        return ColorScheme::combine(ColorScheme::BG_WHITE, ColorScheme::FG_BLACK);
    }

    #[\Override]
    public function getErrorText(): string
    {
        return ColorScheme::combine(ColorScheme::BG_WHITE, ColorScheme::FG_BRIGHT_RED, ColorScheme::BOLD);
    }

    #[\Override]
    public function getWarningText(): string
    {
        return ColorScheme::combine(ColorScheme::BG_WHITE, ColorScheme::FG_BRIGHT_YELLOW, ColorScheme::BOLD);
    }

    #[\Override]
    public function getHighlightText(): string
    {
        return ColorScheme::combine(ColorScheme::BG_WHITE, ColorScheme::FG_BLUE, ColorScheme::BOLD);
    }
}
