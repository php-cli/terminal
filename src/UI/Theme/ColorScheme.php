<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

/**
 * Midnight Commander color scheme using ANSI escape codes
 */
final class ColorScheme
{
    // Base colors
    public const string RESET = "\033[0m";

    // Foreground colors
    public const string FG_BLACK = "\033[30m";
    public const string FG_RED = "\033[31m";
    public const string FG_GREEN = "\033[32m";
    public const string FG_YELLOW = "\033[33m";
    public const string FG_BLUE = "\033[34m";
    public const string FG_MAGENTA = "\033[35m";
    public const string FG_CYAN = "\033[36m";
    public const string FG_WHITE = "\033[37m";
    public const string FG_GRAY = "\033[90m";
    public const string FG_BRIGHT_WHITE = "\033[1;37m";

    // Background colors
    public const string BG_BLACK = "\033[40m";
    public const string BG_RED = "\033[41m";
    public const string BG_GREEN = "\033[42m";
    public const string BG_YELLOW = "\033[43m";
    public const string BG_BLUE = "\033[44m";
    public const string BG_MAGENTA = "\033[45m";
    public const string BG_CYAN = "\033[46m";
    public const string BG_WHITE = "\033[47m";

    // Text styles
    public const string BOLD = "\033[1m";
    public const string DIM = "\033[2m";
    public const string ITALIC = "\033[3m";
    public const string UNDERLINE = "\033[4m";
    public const string BLINK = "\033[5m";
    public const string REVERSE = "\033[7m";

    // MC-specific color combinations
    public const string NORMAL_BG = self::BG_BLUE;
    public const string NORMAL_FG = self::FG_WHITE;
    public const string NORMAL_TEXT = self::BG_BLUE . self::FG_WHITE;

    // Menu bar (top)
    public const string MENU_BG = self::BG_CYAN;
    public const string MENU_FG = self::FG_BLACK;
    public const string MENU_TEXT = self::BG_CYAN . self::FG_BLACK;
    public const string MENU_HOTKEY = self::BG_CYAN . self::FG_YELLOW;

    // Status bar (bottom)
    public const string STATUS_BG = self::BG_CYAN;
    public const string STATUS_FG = self::FG_BLACK;
    public const string STATUS_TEXT = self::BG_CYAN . self::FG_BLACK;
    public const string STATUS_KEY = self::BG_CYAN . self::BOLD . self::FG_WHITE;

    // Selection
    public const string SELECTED_BG = self::BG_CYAN;
    public const string SELECTED_FG = self::FG_BLACK;
    public const string SELECTED_TEXT = self::BG_CYAN . self::FG_BLACK;

    // Borders
    public const string ACTIVE_BORDER = self::BG_BLUE . self::FG_BRIGHT_WHITE;
    public const string INACTIVE_BORDER = self::BG_BLUE . self::FG_GRAY;

    // Input fields
    public const string INPUT_BG = self::BG_BLACK;
    public const string INPUT_FG = self::FG_YELLOW;
    public const string INPUT_TEXT = self::BG_BLACK . self::FG_YELLOW;
    public const string INPUT_CURSOR = self::REVERSE;

    // Scrollbar
    public const string SCROLLBAR = self::BG_BLUE . self::FG_CYAN;

    // Error/Warning
    public const string ERROR_TEXT = self::BG_BLUE . self::FG_RED;
    public const string WARNING_TEXT = self::BG_BLUE . self::FG_YELLOW;

    /**
     * Combine multiple ANSI codes
     */
    public static function combine(string ...$codes): string
    {
        return \implode('', $codes);
    }

    /**
     * Wrap text with color and reset
     */
    public static function colorize(string $text, string $color): string
    {
        return $color . $text . self::RESET;
    }
}
