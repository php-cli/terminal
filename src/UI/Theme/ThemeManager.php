<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

/**
 * Manages application themes
 */
final class ThemeManager
{
    private static ?ThemeInterface $currentTheme = null;

    /** @var array<string, class-string<ThemeInterface>> */
    private static array $availableThemes = [
        'midnight' => MidnightTheme::class,
        'dark' => DarkTheme::class,
        'light' => LightTheme::class,
    ];

    /**
     * Get current active theme
     */
    public static function getCurrentTheme(): ThemeInterface
    {
        if (self::$currentTheme === null) {
            self::$currentTheme = new MidnightTheme(); // Default to Midnight Commander theme
        }

        return self::$currentTheme;
    }

    /**
     * Set active theme by name
     *
     * @param string $name Theme name (midnight, dark, light)
     * @return bool True if theme was set successfully
     */
    public static function setTheme(string $name): bool
    {
        $name = \strtolower($name);

        if (!isset(self::$availableThemes[$name])) {
            return false;
        }

        $themeClass = self::$availableThemes[$name];
        self::$currentTheme = new $themeClass();

        return true;
    }

    /**
     * Set active theme by instance
     */
    public static function setThemeInstance(ThemeInterface $theme): void
    {
        self::$currentTheme = $theme;
    }

    /**
     * Get list of available theme names
     *
     * @return array<string>
     */
    public static function getAvailableThemes(): array
    {
        return \array_keys(self::$availableThemes);
    }

    /**
     * Register a custom theme
     *
     * @param string $name Theme identifier
     * @param class-string<ThemeInterface> $themeClass Theme class name
     */
    public static function registerTheme(string $name, string $themeClass): void
    {
        self::$availableThemes[\strtolower($name)] = $themeClass;
    }
}
