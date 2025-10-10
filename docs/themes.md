# Color Themes

Commander supports customizable color themes to match your preferences.

## Available Themes

### 1. Midnight Commander (default)
Classic Midnight Commander blue theme with cyan highlights.
- Blue background
- Cyan menu/status bars
- Traditional MC feel

### 2. Dark
Modern dark theme with black background.
- Black background
- Cyan and green accents
- Perfect for dark terminals

### 3. Light
Light theme for bright environments.
- White background
- Blue accents
- Easy on the eyes in well-lit rooms

## Usage

### Setting a Theme Programmatically

```php
use Butschster\Commander\UI\Theme\ThemeManager;
use Butschster\Commander\UI\Theme\ColorScheme;

// Switch to dark theme
ThemeManager::setTheme('dark');
ColorScheme::applyTheme();

// Switch to light theme
ThemeManager::setTheme('light');
ColorScheme::applyTheme();

// Switch to midnight theme (default)
ThemeManager::setTheme('midnight');
ColorScheme::applyTheme();
```

### Using Theme Colors in Components

Components can use the dynamic theme colors:

```php
use Butschster\Commander\UI\Theme\ColorScheme;

// Use static properties for theme-aware colors
$renderer->writeAt(0, 0, "Hello", ColorScheme::$NORMAL_TEXT);
$renderer->writeAt(0, 1, "Selected", ColorScheme::$SELECTED_TEXT);

// Or use constants for backward compatibility (always Midnight theme)
$renderer->writeAt(0, 2, "Static", ColorScheme::NORMAL_TEXT);
```

## Creating Custom Themes

You can create your own theme by implementing `ThemeInterface`:

```php
use Butschster\Commander\UI\Theme\AbstractTheme;
use Butschster\Commander\UI\Theme\ColorScheme;

final class MyCustomTheme extends AbstractTheme
{
    public function getName(): string
    {
        return 'My Custom Theme';
    }

    public function getNormalBg(): string
    {
        return ColorScheme::BG_MAGENTA;
    }

    public function getNormalFg(): string
    {
        return ColorScheme::FG_WHITE;
    }

    // Implement other required methods...
}
```

Then register and use it:

```php
use Butschster\Commander\UI\Theme\ThemeManager;

ThemeManager::registerTheme('custom', MyCustomTheme::class);
ThemeManager::setTheme('custom');
ColorScheme::applyTheme();
```

## Theme Components

Each theme defines colors for:
- **Normal text** - Default background and foreground
- **Menu bar** - Top menu colors
- **Status bar** - Bottom status line
- **Selection** - Highlighted/selected items
- **Borders** - Active and inactive borders
- **Input fields** - Text input areas
- **Scrollbar** - Scroll indicators
- **Error/Warning** - Error and warning messages

## Best Practices

1. **Apply theme early** - Set theme during application initialization
2. **Use static properties** - Use `ColorScheme::$NORMAL_TEXT` for theme-aware colors
3. **Constants for compatibility** - Use `ColorScheme::NORMAL_TEXT` if you always want Midnight theme
4. **Reapply after switching** - Always call `ColorScheme::applyTheme()` after changing themes
