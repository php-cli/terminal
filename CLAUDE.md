# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Commander is an MC-style (Midnight Commander) PHP console application framework for building fullscreen, keyboard-driven terminal UIs with double-buffered rendering. The namespace is `Butschster\Commander`.

## Common Commands

```bash
# Run all tests
composer test

# Run specific test suites
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Integration
vendor/bin/phpunit --testsuite=E2E

# Run single test file or method
vendor/bin/phpunit --filter=FileBrowserScreenTest
vendor/bin/phpunit --filter=testNavigateDown

# Code style
composer cs-fix      # Fix code style issues
composer cs-check    # Check without fixing

# Static analysis
composer psalm

# Refactoring (Rector)
composer refactor       # Apply refactoring
composer refactor:ci    # Dry run

# Full lint pipeline (cs-check + refactor:ci + psalm + test)
composer lint

# Run the application
./console
```

## Architecture

### Directory Structure

```
src/
├── Application.php              # Main event loop, input handling, rendering
├── ExceptionRenderer.php        # Terminal-safe exception display
├── Feature/                     # Feature modules (self-contained screens)
│   ├── CommandBrowser/          # Symfony command browser
│   ├── ComposerManager/         # Composer package manager
│   └── FileBrowser/             # File system browser
├── Infrastructure/              # Core services
│   ├── Keyboard/                # Key binding system (Key, KeyBinding, KeyCombination)
│   └── Terminal/                # Terminal I/O (Renderer, KeyboardHandler, TerminalManager)
└── UI/                          # UI framework
    ├── Component/               # Reusable UI components
    │   ├── Container/           # Layout containers (TabContainer, GridLayout, SplitLayout)
    │   ├── Display/             # Display components (TextComponent, KeyValue)
    │   ├── Input/               # Form inputs (TextField, CheckboxField, FormComponent)
    │   └── Layout/              # Layout primitives (Panel, Modal, MenuBar, StatusBar)
    ├── Menu/                    # Menu system (MenuBuilder, MenuDefinition)
    ├── Screen/                  # Screen management (ScreenInterface, ScreenManager, ScreenRegistry)
    └── Theme/                   # Color themes (ThemeInterface, ThemeContext, ColorScheme)
```

### Key Concepts

1. **Screens** (`ScreenInterface`): Full-screen views managed by `ScreenManager` as a stack. Screens implement `render()`, `handleInput()`, `onActivate()`, `onDeactivate()`, `update()`, and `getTitle()`.

2. **Components** (`ComponentInterface`): Reusable UI elements with focus management. Components implement `render()`, `handleInput()`, `setFocused()`, `isFocused()`, `update()`, and `getMinSize()`.

3. **Renderer**: Double-buffered rendering engine that minimizes ANSI sequences. Uses `beginFrame()` / `endFrame()` pattern. Access theme via `$renderer->getThemeContext()`.

4. **Driver Abstraction**: `TerminalDriverInterface` allows swapping real terminal I/O (`RealTerminalDriver`) with virtual implementation (`VirtualTerminalDriver` in tests).

5. **Key Bindings**: Type-safe keyboard system with `Key` enum, `KeyCombination` for modifier parsing, and `KeyBindingRegistry` for action mapping.

### Testing Framework

Tests use `TerminalTestCase` base class with virtual terminal driver:

```php
class MyTest extends TerminalTestCase
{
    public function testSomething(): void
    {
        $this->terminal()->setSize(180, 64);

        $this->keys()
            ->down(3)
            ->enter()
            ->applyTo($this->terminal());

        $this->runApp(new MyScreen());

        $this->assertScreenContains('Expected text');
    }
}
```

Key testing APIs:
- `$this->terminal()` - Virtual terminal driver
- `$this->keys()` - Fluent key sequence builder with `down()`, `up()`, `enter()`, `escape()`, `tab()`, `fn()`, `ctrl()`, `type()`, `frame()`
- `$this->runApp()` - Run application until input exhausted
- `$this->runUntil()` - Run until condition met
- `$this->capture()` - Get screen snapshot for assertions
- Assertions: `assertScreenContains()`, `assertScreenContainsAll()`, `assertLineContains()`, `assertTextAt()`, `assertCurrentScreen()`

### Creating a Screen

Screens can use the `#[Screen]` attribute for auto-registration:

```php
use Butschster\Commander\UI\Screen\Attribute\Screen;
use Butschster\Commander\UI\Screen\ScreenInterface;

#[Screen(
    id: 'my_screen',
    title: 'My Screen',
    category: 'tools',
    shortcut: 'F4'
)]
final class MyScreen implements ScreenInterface
{
    public function render(Renderer $renderer, int $x, int $y, ?int $width, ?int $height): void
    {
        // Use $renderer->writeAt(), $renderer->drawBox(), etc.
    }

    public function handleInput(string $key): bool
    {
        // Return true if key was handled
        return false;
    }

    // ... other interface methods
}
```

### Theme System

Themes implement `ThemeInterface`. Access colors via `ThemeContext`:
- `$themeContext->getNormalText()` - Standard text color
- `$themeContext->getSelectedText()` - Highlighted/selected text
- `$themeContext->getPanelBackground()` - Panel fill color
- `$themeContext->getBorderColors()` - Border color set

Available themes: `MidnightTheme`, `DarkTheme`, `LightTheme`
