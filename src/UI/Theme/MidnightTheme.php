<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

/**
 * Classic Midnight Commander theme (blue background)
 */
final class MidnightTheme extends AbstractTheme
{
    #[\Override]
    public function getName(): string
    {
        return 'Midnight Commander';
    }

    #[\Override]
    public function getNormalBg(): string
    {
        return ColorScheme::BG_BLUE;
    }

    #[\Override]
    public function getNormalFg(): string
    {
        return ColorScheme::FG_WHITE;
    }

    #[\Override]
    public function getMenuBg(): string
    {
        return ColorScheme::BG_CYAN;
    }

    #[\Override]
    public function getMenuFg(): string
    {
        return ColorScheme::FG_BLACK;
    }

    #[\Override]
    public function getMenuHotkey(): string
    {
        return ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::FG_YELLOW);
    }

    #[\Override]
    public function getStatusBg(): string
    {
        return ColorScheme::BG_CYAN;
    }

    #[\Override]
    public function getStatusFg(): string
    {
        return ColorScheme::FG_BLACK;
    }

    #[\Override]
    public function getStatusKey(): string
    {
        return ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::BOLD, ColorScheme::FG_WHITE);
    }

    #[\Override]
    public function getSelectedBg(): string
    {
        return ColorScheme::BG_CYAN;
    }

    #[\Override]
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

    #[\Override]
    public function getActiveBorder(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_BRIGHT_WHITE);
    }

    #[\Override]
    public function getInactiveBorder(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_GRAY);
    }

    #[\Override]
    public function getInputBg(): string
    {
        return ColorScheme::BG_BLACK;
    }

    #[\Override]
    public function getInputFg(): string
    {
        return ColorScheme::FG_YELLOW;
    }

    #[\Override]
    public function getInputCursor(): string
    {
        return ColorScheme::REVERSE;
    }

    #[\Override]
    public function getScrollbar(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_CYAN);
    }

    #[\Override]
    public function getErrorText(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_RED);
    }

    #[\Override]
    public function getWarningText(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW);
    }

    #[\Override]
    public function getHighlightText(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_WHITE, ColorScheme::BOLD);
    }

    #[\Override]
    public function getMutedText(): string
    {
        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_GRAY);
    }
}
