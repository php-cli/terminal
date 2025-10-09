<?php

declare(strict_types=1);

namespace Butschster\Commander\Theme;

/**
 * Midnight Commander color scheme using ANSI escape codes
 */
final class ColorScheme
{
    // Base colors
    public const RESET = "\033[0m";

    // Foreground colors
    public const FG_BLACK = "\033[30m";
    public const FG_RED = "\033[31m";
    public const FG_GREEN = "\033[32m";
    public const FG_YELLOW = "\033[33m";
    public const FG_BLUE = "\033[34m";
    public const FG_MAGENTA = "\033[35m";
    public const FG_CYAN = "\033[36m";
    public const FG_WHITE = "\033[37m";
    public const FG_GRAY = "\033[90m";
    public const FG_BRIGHT_WHITE = "\033[1;37m";

    // Background colors
    public const BG_BLACK = "\033[40m";
    public const BG_RED = "\033[41m";
    public const BG_GREEN = "\033[42m";
    public const BG_YELLOW = "\033[43m";
    public const BG_BLUE = "\033[44m";
    public const BG_MAGENTA = "\033[45m";
    public const BG_CYAN = "\033[46m";
    public const BG_WHITE = "\033[47m";

    // Text styles
    public const BOLD = "\033[1m";
    public const DIM = "\033[2m";
    public const ITALIC = "\033[3m";
    public const UNDERLINE = "\033[4m";
    public const BLINK = "\033[5m";
    public const REVERSE = "\033[7m";

    // MC-specific color combinations
    public const NORMAL_BG = self::BG_BLUE;
    public const NORMAL_FG = self::FG_WHITE;
    public const NORMAL_TEXT = self::BG_BLUE . self::FG_WHITE;

    // Menu bar (top)
    public const MENU_BG = self::BG_CYAN;
    public const MENU_FG = self::FG_BLACK;
    public const MENU_TEXT = self::BG_CYAN . self::FG_BLACK;
    public const MENU_HOTKEY = self::BG_CYAN . self::FG_YELLOW;

    // Status bar (bottom)
    public const STATUS_BG = self::BG_CYAN;
    public const STATUS_FG = self::FG_BLACK;
    public const STATUS_TEXT = self::BG_CYAN . self::FG_BLACK;
    public const STATUS_KEY = self::BG_CYAN . self::BOLD . self::FG_WHITE;

    // Selection
    public const SELECTED_BG = self::BG_CYAN;
    public const SELECTED_FG = self::FG_BLACK;
    public const SELECTED_TEXT = self::BG_CYAN . self::FG_BLACK;

    // Borders
    public const ACTIVE_BORDER = self::BG_BLUE . self::FG_BRIGHT_WHITE;
    public const INACTIVE_BORDER = self::BG_BLUE . self::FG_GRAY;

    // Input fields
    public const INPUT_BG = self::BG_BLACK;
    public const INPUT_FG = self::FG_YELLOW;
    public const INPUT_TEXT = self::BG_BLACK . self::FG_YELLOW;
    public const INPUT_CURSOR = self::REVERSE;

    // Scrollbar
    public const SCROLLBAR = self::BG_BLUE . self::FG_CYAN;

    // Error/Warning
    public const ERROR_TEXT = self::BG_BLUE . self::FG_RED;
    public const WARNING_TEXT = self::BG_BLUE . self::FG_YELLOW;

    /**
     * Combine multiple ANSI codes
     */
    public static function combine(string ...$codes): string
    {
        return implode('', $codes);
    }

    /**
     * Wrap text with color and reset
     */
    public static function colorize(string $text, string $color): string
    {
        return $color . $text . self::RESET;
    }
}
