<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

/**
 * Color scheme with ANSI escape codes and theme support
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
    public const string FG_BRIGHT_RED = "\033[91m";
    public const string FG_BRIGHT_GREEN = "\033[92m";
    public const string FG_BRIGHT_YELLOW = "\033[93m";
    public const string FG_BRIGHT_BLUE = "\033[94m";
    public const string FG_BRIGHT_MAGENTA = "\033[95m";
    public const string FG_BRIGHT_CYAN = "\033[96m";
    public const string FG_BRIGHT_WHITE = "\033[97m";

    // Background colors
    public const string BG_BLACK = "\033[40m";
    public const string BG_RED = "\033[41m";
    public const string BG_GREEN = "\033[42m";
    public const string BG_YELLOW = "\033[43m";
    public const string BG_BLUE = "\033[44m";
    public const string BG_MAGENTA = "\033[45m";
    public const string BG_CYAN = "\033[46m";
    public const string BG_WHITE = "\033[47m";
    public const string BG_GRAY = "\033[100m";
    public const string BG_BRIGHT_RED = "\033[101m";
    public const string BG_BRIGHT_GREEN = "\033[102m";
    public const string BG_BRIGHT_YELLOW = "\033[103m";
    public const string BG_BRIGHT_BLUE = "\033[104m";
    public const string BG_BRIGHT_MAGENTA = "\033[105m";
    public const string BG_BRIGHT_CYAN = "\033[106m";
    public const string BG_BRIGHT_WHITE = "\033[107m";

    // Text styles
    public const string BOLD = "\033[1m";
    public const string DIM = "\033[2m";
    public const string ITALIC = "\033[3m";
    public const string UNDERLINE = "\033[4m";
    public const string BLINK = "\033[5m";
    public const string REVERSE = "\033[7m";

    // Backward compatibility - use constants for default theme
    public const string NORMAL_BG = self::BG_BLUE;
    public const string NORMAL_TEXT = self::BG_BLUE . self::FG_WHITE;

    // Theme-aware color getters (use current theme)
    public static string $NORMAL_BG;
    public static string $NORMAL_FG;
    public static string $NORMAL_TEXT;
    public static string $MENU_BG;
    public static string $MENU_FG;
    public static string $MENU_TEXT;
    public static string $MENU_HOTKEY;
    public static string $STATUS_BG;
    public static string $STATUS_FG;
    public static string $STATUS_TEXT;
    public static string $STATUS_KEY;
    public static string $SELECTED_BG;
    public static string $SELECTED_FG;
    public static string $SELECTED_TEXT;
    public static string $ACTIVE_BORDER;
    public static string $INACTIVE_BORDER;
    public static string $INPUT_BG;
    public static string $INPUT_FG;
    public static string $INPUT_TEXT;
    public static string $INPUT_CURSOR;
    public static string $SCROLLBAR;
    public static string $ERROR_TEXT;
    public static string $WARNING_TEXT;
    public static string $HIGHLIGHT_TEXT;
    public static string $MUTED_TEXT;

    /**
     * Initialize theme colors from current theme
     */
    public static function applyTheme(?ThemeInterface $theme = null): void
    {
        if ($theme === null) {
            $theme = ThemeManager::getCurrentTheme();
        }

        self::$NORMAL_BG = $theme->getNormalBg();
        self::$NORMAL_FG = $theme->getNormalFg();
        self::$NORMAL_TEXT = $theme->getNormalText();
        self::$MENU_BG = $theme->getMenuBg();
        self::$MENU_FG = $theme->getMenuFg();
        self::$MENU_TEXT = $theme->getMenuText();
        self::$MENU_HOTKEY = $theme->getMenuHotkey();
        self::$STATUS_BG = $theme->getStatusBg();
        self::$STATUS_FG = $theme->getStatusFg();
        self::$STATUS_TEXT = $theme->getStatusText();
        self::$STATUS_KEY = $theme->getStatusKey();
        self::$SELECTED_BG = $theme->getSelectedBg();
        self::$SELECTED_FG = $theme->getSelectedFg();
        self::$SELECTED_TEXT = $theme->getSelectedText();
        self::$ACTIVE_BORDER = $theme->getActiveBorder();
        self::$INACTIVE_BORDER = $theme->getInactiveBorder();
        self::$INPUT_BG = $theme->getInputBg();
        self::$INPUT_FG = $theme->getInputFg();
        self::$INPUT_TEXT = $theme->getInputText();
        self::$INPUT_CURSOR = $theme->getInputCursor();
        self::$SCROLLBAR = $theme->getScrollbar();
        self::$ERROR_TEXT = $theme->getErrorText();
        self::$WARNING_TEXT = $theme->getWarningText();
        self::$HIGHLIGHT_TEXT = $theme->getHighlightText();
        self::$MUTED_TEXT = $theme->getMutedText();
    }

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
