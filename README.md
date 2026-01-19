# Commander - MC-Style PHP Console Application

<p align="center">
  <strong>Component-based Midnight Commander-style terminal application framework.</strong><br>
  Create fullscreen, keyboard-driven interfaces with double-buffered rendering.
</p>

---

## Why Commander?

Build terminal UIs like you build web apps - with reusable components, clean architecture, and modern PHP.

```php
use Butschster\Commander\SDK\Builder\ApplicationBuilder;
use Butschster\Commander\Module\FileBrowser\FileBrowserModule;

$app = ApplicationBuilder::create()
    ->withModule(new FileBrowserModule())
    ->withInitialScreen('file_browser')
    ->build();

$app->run();
```

**No complex setup. No learning curve. Just beautiful terminal UIs.**

---

## Features

- **Multiple Color Themes** - Midnight Commander, Dark, and Light themes
- **Double-Buffered Rendering** - Flicker-free display with minimal ANSI sequences
- **Component-Based Architecture** - Reusable UI components (Tables, Forms, Lists, Panels)
- **Module SDK** - Extensible plugin system for adding new features
- **Full Keyboard Navigation** - Function keys, arrow keys, and shortcuts
- **Built-in Modules** - File Browser, Command Browser, Composer Manager, Git

---

## Requirements

- PHP 8.3 or higher
- Symfony Console 7.3+
- Terminal with ANSI color support

---

## Quick Start

### 1. Installation

```bash
composer require cli/terminal
```

### 2. Create Launch Script

Create a PHP file (e.g., `console`) that launches the Commander interface:

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Butschster\Commander\SDK\Builder\ApplicationBuilder;
use Butschster\Commander\Module\FileBrowser\FileBrowserModule;
use Butschster\Commander\Module\CommandBrowser\CommandBrowserModule;
use Butschster\Commander\Module\ComposerManager\ComposerModule;
use Butschster\Commander\Module\Git\GitModule;
use Butschster\Commander\UI\Theme\ColorScheme;
use Butschster\Commander\UI\Theme\MidnightTheme;
use Symfony\Component\Console\Application as SymfonyApplication;

// 1. Create Symfony Console Application (for command browser)
$symfonyApp = new SymfonyApplication('My App', '1.0.0');

// 2. Apply theme
ColorScheme::applyTheme(new MidnightTheme());

// 3. Build application with modules
$app = ApplicationBuilder::create()
    ->withModule(new FileBrowserModule())
    ->withModule(new CommandBrowserModule($symfonyApp))
    ->withModule(new ComposerModule(getcwd()))
    ->withModule(new GitModule())
    ->withInitialScreen('file_browser')
    ->build();

// 4. Run
$app->run();
```

### 3. Make It Executable and Launch

```bash
chmod +x console
./console
```

**That's it!** You now have a fullscreen terminal UI with:

- Midnight Commander-style interface
- File browser with dual-panel layout
- Command browser for Symfony Console commands
- Composer package manager
- Git repository browser
- Smooth, flicker-free rendering

---

## Available Themes

Commander comes with three built-in themes:

### Midnight Commander (Default)

Classic blue theme with cyan highlights - just like the original Midnight Commander.

```php
use Butschster\Commander\UI\Theme\MidnightTheme;
ColorScheme::applyTheme(new MidnightTheme());
```

### Dark Theme

Modern dark theme with black background and green/cyan accents.

```php
use Butschster\Commander\UI\Theme\DarkTheme;
ColorScheme::applyTheme(new DarkTheme());
```

### Light Theme

Light theme with white background - perfect for well-lit environments.

```php
use Butschster\Commander\UI\Theme\LightTheme;
ColorScheme::applyTheme(new LightTheme());
```

**Tip:** Call `ColorScheme::applyTheme()` before building your Application.

---

## Built-in Modules

Commander includes four ready-to-use modules:

### 1. File Browser Module

Navigate and browse the filesystem with a two-panel layout.

```php
use Butschster\Commander\Module\FileBrowser\FileBrowserModule;

->withModule(new FileBrowserModule('/path/to/start'))
```

**Features:**

- Dual-panel layout (list + preview)
- File/directory navigation
- File preview with syntax highlighting
- Shows file sizes and modification times

**Keyboard:** `F1` menu | `↑↓` navigate | `Enter` open | `Tab` switch panels | `Ctrl+E` view file

[Full Documentation](src/Module/FileBrowser/README.md)

### 2. Command Browser Module

Browse and execute all registered Symfony Console commands.

```php
use Butschster\Commander\Module\CommandBrowser\CommandBrowserModule;

->withModule(new CommandBrowserModule($symfonyApp))
```

**Features:**

- Lists all available Symfony commands
- Dynamic form generation for arguments and options
- Shows command output in real-time
- Dangerous command confirmation

**Keyboard:** `F2` menu | `↑↓` navigate | `Tab` switch panels | `Ctrl+E` execute

[Full Documentation](src/Module/CommandBrowser/README.md)

### 3. Composer Manager Module

Manage Composer packages with tabbed interface.

```php
use Butschster\Commander\Module\ComposerManager\ComposerModule;

->withModule(new ComposerModule('/path/to/project'))
```

**Features:**

- View installed packages
- Check for outdated packages
- Security audit
- Run Composer scripts

**Tabs:** Scripts | Installed | Outdated | Security

**Keyboard:** `F3` menu | `Ctrl+←/→` switch tabs | `↑↓` navigate

[Full Documentation](src/Module/ComposerManager/README.md)

### 4. Git Module

Browse and manage Git repositories.

```php
use Butschster\Commander\Module\Git\GitModule;

->withModule(new GitModule('/path/to/repo'))
```

**Features:**

- View repository status
- Stage/unstage files
- View file diffs
- Browse branches and tags

**Tabs:** Status | Branches | Tags

**Keyboard:** `F4` menu | `Ctrl+←/→` switch tabs | `Ctrl+G` global shortcut

[Full Documentation](src/Module/Git/README.md)

---

## Module SDK

Create your own modules using the Module SDK. See the [Module SDK Developer Guide](docs/guides/module-sdk.md) for
complete documentation.

### Quick Example

```php
<?php

declare(strict_types=1);

namespace MyApp\Module\Todo;

use Butschster\Commander\SDK\Module\ModuleInterface;
use Butschster\Commander\SDK\Module\ModuleMetadata;
use Butschster\Commander\SDK\Module\ModuleContext;
use Butschster\Commander\SDK\Provider\ScreenProviderInterface;
use Butschster\Commander\SDK\Container\ContainerInterface;

final readonly class TodoModule implements ModuleInterface, ScreenProviderInterface
{
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata(
            name: 'todo',
            title: 'Todo Manager',
            version: '1.0.0',
        );
    }

    public function screens(ContainerInterface $container): iterable
    {
        yield new TodoScreen();
    }

    public function boot(ModuleContext $context): void
    {
        // Initialize module
    }

    public function shutdown(): void
    {
        // Cleanup
    }
}
```

### Using Custom Modules

```php
$app = ApplicationBuilder::create()
    ->withModule(new FileBrowserModule())
    ->withModule(new TodoModule())  // Your custom module
    ->withInitialScreen('todo')
    ->build();
```

---

## Available Components

Commander provides rich set of UI components:

### Display Components

- `ListComponent` - Scrollable list with selection
- `TableComponent` - Multi-column table with sorting
- `TextDisplay` - Scrollable text viewer with word wrap

### Input Components

- `FormComponent` - Form container with validation
- `TextField` - Text input field
- `CheckboxField` - Checkbox/toggle
- `ArrayField` - Comma-separated array input

### Layout Components

- `Panel` - Container with border and title
- `Modal` - Overlay dialog (info/error/warning/confirm)
- `MenuBar` - Top menu bar
- `StatusBar` - Bottom status bar with shortcuts
- `TabContainer` - Tabbed interface
- `GridLayout` - Column-based layout
- `SplitLayout` - Two-panel split layout
- `StackLayout` - Vertical/horizontal stacking

---

## Architecture Overview

### Project Structure

```
src/
├── Application.php              # Main application entry point
├── Module/                      # Built-in modules
│   ├── FileBrowser/             # File browser module
│   ├── CommandBrowser/          # Command browser module
│   ├── ComposerManager/         # Composer manager module
│   └── Git/                     # Git module
├── SDK/                         # Module SDK
│   ├── Builder/                 # ApplicationBuilder
│   ├── Container/               # Dependency injection
│   ├── Module/                  # Module interfaces
│   └── Provider/                # Provider interfaces
├── Infrastructure/              # Core infrastructure
│   ├── Keyboard/                # Key binding system
│   └── Terminal/                # Rendering, input handling
└── UI/                          # UI framework
    ├── Component/               # Reusable components
    ├── Screen/                  # Screen management
    └── Theme/                   # Color themes
```

### Key Concepts

1. **Modules** - Self-contained features (File Browser, Git, etc.)
2. **Screens** - Full-screen views (like pages in a web app)
3. **Components** - Reusable UI elements (tables, forms, panels)
4. **Double Buffering** - Prevents flickering by rendering off-screen first
5. **Theme System** - Customizable color schemes

---

## Documentation

- [Module SDK Developer Guide](docs/guides/module-sdk.md) - Create custom modules
- [Keyboard Architecture](docs/guides/keyboard-architecture.md) - Key handling internals
- [Testing Guide](docs/guides/testing.md) - Testing screens and components
- [Menu System](docs/guides/menu-system.md) - Menus and navigation

---

## Use Cases

Commander is perfect for:

- **DevOps Tools** - Server management, deployment dashboards
- **CLI Dashboards** - System monitoring, log viewers
- **Development Tools** - Code generators, project scaffolders
- **Admin Panels** - Database managers, configuration editors
- **Interactive Wizards** - Multi-step installation processes
- **File Managers** - Custom file browsers with preview
- **Package Managers** - Dependency management UIs

---

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## License

MIT License. See [LICENSE](LICENSE) file for details.

---

## Acknowledgments

Inspired by [Midnight Commander](https://midnight-commander.org/) - the legendary file manager.
