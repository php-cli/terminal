# Commander - MC-Style PHP Console Application

<p align="center">
  <strong>Component-based Midnight Commander-style terminal application framework.</strong><br>
  Create fullscreen, keyboard-driven interfaces with double-buffered rendering.
</p>

<p align="center">
  <img src="docs/screenshot.png" alt="Commander Screenshot" width="800">
</p>

---

## 🚀 Why Commander?

Build terminal UIs like you build web apps - with reusable components, clean architecture, and modern PHP.

```php
// Simple as creating a PHP file
$app = new Application($symfonyApp);
$app->run(new CommandsScreen($commandDiscovery, $commandExecutor));
```

**No complex setup. No learning curve. Just beautiful terminal UIs.**

---

## ✨ Features

- 🎨 **Multiple Color Themes** - Midnight Commander, Dark, and Light themes
- ⚡ **Double-Buffered Rendering** - Flicker-free display with minimal ANSI sequences
- 🧩 **Component-Based Architecture** - Reusable UI components (Tables, Forms, Lists, Panels)
- ⌨️ **Full Keyboard Navigation** - Function keys, arrow keys, and shortcuts
- 📦 **Built-in Features** - Command Browser, File Browser, Composer Manager
- 🔌 **Extensible** - Easy to create custom screens and components

---

## 📋 Requirements

- PHP 8.3 or higher
- Symfony Console 7.3+
- Terminal with ANSI color support (most modern terminals)

---

## 🚀 Quick Start

### 1. Installation

Install via Composer:

```bash
composer require cli/terminal
```

### 2. Create Launch Script

Create a simple PHP file (e.g., `console` or `ui`) that launches the Commander interface:

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Butschster\Commander\Application;
use Butschster\Commander\Feature\CommandBrowser\Screen\CommandsScreen;
use Butschster\Commander\Feature\CommandBrowser\Service\CommandDiscovery;
use Butschster\Commander\Feature\CommandBrowser\Service\CommandExecutor;
use Butschster\Commander\UI\Component\Layout\MenuBar;
use Butschster\Commander\UI\Theme\ColorScheme;
use Butschster\Commander\UI\Theme\MidnightTheme;
use Symfony\Component\Console\Application as SymfonyApplication;

// 1. Create Symfony Console Application
$symfonyApp = new SymfonyApplication('My App', '1.0.0');

// You can add your Symfony commands here:
// $symfonyApp->add(new YourCustomCommand());

// 2. Choose and apply theme
ColorScheme::applyTheme(new MidnightTheme());
// Available: MidnightTheme, DarkTheme, LightTheme

// 3. Create Commander application
$app = new Application($symfonyApp);
$app->setTargetFps(30);

// 4. Setup global menu bar (optional)
$globalMenu = new MenuBar([
    'F1' => ' Help',
    'F2' => ' Commands',
    'F10' => ' Quit',
]);
$app->setGlobalMenuBar($globalMenu);

// 5. Register global shortcuts (optional)
$app->registerGlobalShortcut('F10', function () use ($app) {
    $app->stop(); // Quit application
});

// 6. Create services and initial screen
$commandDiscovery = new CommandDiscovery($symfonyApp);
$commandExecutor = new CommandExecutor($symfonyApp);
$initialScreen = new CommandsScreen($commandDiscovery, $commandExecutor);

// 7. Run the application
$app->run($initialScreen);
```

### 3. Make It Executable and Launch

```bash
chmod +x console
./console
```

**That's it!** You now have a fullscreen terminal UI with:

- ✨ Midnight Commander-style interface
- 📋 Command browser with search and execution
- ⌨️ Keyboard navigation (↑↓, Enter, F10 to quit)
- ⚡ Smooth, flicker-free rendering

---

### Alternative: Symfony Console Command (Optional)

If you're working within a Symfony/Laravel/Spiral application, you can create a Console command instead:

<details>
<summary>Click to see Symfony Console Command example</summary>

```php
<?php

declare(strict_types=1);

namespace App\Console;

use Butschster\Commander\Application;
use Butschster\Commander\Feature\CommandBrowser\Screen\CommandsScreen;
use Butschster\Commander\Feature\CommandBrowser\Service\CommandDiscovery;
use Butschster\Commander\Feature\CommandBrowser\Service\CommandExecutor;
use Butschster\Commander\UI\Theme\ColorScheme;
use Butschster\Commander\UI\Theme\MidnightTheme;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:ui', description: 'Launch Commander UI')]
final class UICommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyApp = $this->getApplication();
        ColorScheme::applyTheme(new MidnightTheme());

        $app = new Application($symfonyApp);
        $commandDiscovery = new CommandDiscovery($symfonyApp);
        $commandExecutor = new CommandExecutor($symfonyApp);
        $screen = new CommandsScreen($commandDiscovery, $commandExecutor);

        $app->run($screen);

        return Command::SUCCESS;
    }
}
```

Then run: `php bin/console app:ui`

</details>

---

## 🎨 Available Themes

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

**💡 Tip:** Call `ColorScheme::applyTheme()` before creating your Application instance.

---

## 📦 Built-in Screens

Commander includes several ready-to-use screens that you can integrate into your application:

### 1. Command Browser Screen

Browse and execute all registered Symfony Console commands.

```php
use Butschster\Commander\Feature\CommandBrowser\Screen\CommandsScreen;
use Butschster\Commander\Feature\CommandBrowser\Service\CommandDiscovery;
use Butschster\Commander\Feature\CommandBrowser\Service\CommandExecutor;

$commandDiscovery = new CommandDiscovery($symfonyApp);
$commandExecutor = new CommandExecutor($symfonyApp);
$screen = new CommandsScreen($commandDiscovery, $commandExecutor);
```

**Features:**

- ✅ Lists all available Symfony commands
- 🔍 Search/filter commands by name or namespace
- 📝 Shows command descriptions and arguments
- ⚡ Execute commands directly from UI
- 📊 Displays command output in real-time

**Keyboard Shortcuts:**

- `↑↓` - Navigate commands
- `Enter` - Execute selected command
- `Ctrl+R` - Refresh command list
- `/` - Search commands
- `Escape` - Cancel/go back

### 2. File Browser Screen

Navigate and browse the filesystem with a two-panel layout.

```php
use Butschster\Commander\Feature\FileBrowser\Screen\FileBrowserScreen;
use Butschster\Commander\Feature\FileBrowser\Service\FileSystemService;

$fileSystem = new FileSystemService();
$screen = new FileBrowserScreen(
    $fileSystem, 
    $screenManager, 
    '/path/to/start/directory'
);
```

**Features:**

- 📁 Two-panel layout (list + preview)
- 📄 File/directory navigation
- 👁️ File preview with syntax highlighting
- 📊 Shows file sizes and modification times
- 🔄 Sort by name, size, or date

**Keyboard Shortcuts:**

- `↑↓` - Navigate files
- `Enter` - Open directory / view file
- `Backspace` - Go to parent directory
- `Tab` - Switch between panels
- `F5` - Copy file
- `F6` - Move file
- `F8` - Delete file

### 3. Composer Manager Screen

Manage Composer packages with tabbed interface.

```php
use Butschster\Commander\Feature\ComposerManager\Screen\ComposerManagerScreen;
use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;

$composerService = new ComposerService('/path/to/project');
$screen = new ComposerManagerScreen($composerService);
$screen->setScreenManager($screenManager);
```

**Features:**

- 📦 View installed packages
- 🔄 Check for outdated packages
- 🔒 Security audit
- ⬆️ Update packages
- ➕ Install new packages
- ❌ Remove packages

**Tabs:**

1. **Installed** - All installed packages with version info
2. **Outdated** - Packages that have newer versions available
3. **Security** - Packages with known security vulnerabilities

**Keyboard Shortcuts:**

- `Ctrl+←/→` - Switch between tabs
- `↑↓` - Navigate packages
- `Enter` - View package details
- `U` - Update selected package
- `D` - Remove selected package
- `Tab` - Switch between list and details panel

---

## 🔧 Customizing Screens

All screens support customization of keyboard shortcuts and behavior:

### Example: Custom Global Shortcuts

```php
$app = new Application($symfonyApp);
$screenManager = $app->getScreenManager();

// F2: Switch to Command Browser
$app->registerGlobalShortcut('F2', function ($sm) use ($commandDiscovery, $commandExecutor) {
    $screen = new CommandsScreen($commandDiscovery, $commandExecutor);
    $sm->pushScreen($screen);
});

// F3: Switch to File Browser
$app->registerGlobalShortcut('F3', function ($sm) use ($fileSystem) {
    $screen = new FileBrowserScreen($fileSystem, $sm, getcwd());
    $sm->pushScreen($screen);
});

// F5: Switch to Composer Manager
$app->registerGlobalShortcut('F5', function ($sm) use ($composerService) {
    $screen = new ComposerManagerScreen($composerService);
    $screen->setScreenManager($sm);
    $sm->pushScreen($screen);
});

// F10: Quit
$app->registerGlobalShortcut('F10', function () use ($app) {
    $app->stop();
});
```

### Example: Custom Menu Bar

```php
$globalMenu = new MenuBar([
    'F1' => ' Help',
    'F2' => ' Commands', 
    'F3' => ' Files',
    'F5' => ' Composer',
    'F9' => ' Settings',
    'F10' => ' Quit',
]);
$app->setGlobalMenuBar($globalMenu);
```

### Example: Combining Multiple Screens

```php
// Start with command browser
$welcomeScreen = new CommandsScreen($commandDiscovery, $commandExecutor);

// User can navigate to other screens via shortcuts
$app->registerGlobalShortcut('F3', function ($sm) {
    // Pop all screens and push file browser
    $sm->popUntil(fn($screen) => $screen instanceof FileBrowserScreen);
    
    if (!($sm->getCurrentScreen() instanceof FileBrowserScreen)) {
        $screen = new FileBrowserScreen($fileSystem, $sm, getcwd());
        $sm->pushScreen($screen);
    }
});

$app->run($welcomeScreen);
```

---

## 📚 Creating Custom Screens

Want to create your own screen? Check out these guides:

- 📖 [Creating Custom Screens](docs/creating-screens.md)
- 🎨 [Component System](docs/components.md)
- ⌨️ [Keyboard Handling](docs/keyboard-handling.md)
- 🎭 [Styling with Themes](docs/themes.md)

**Quick Example:**

```php
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\Infrastructure\Terminal\Renderer;

final class MyCustomScreen implements ScreenInterface
{
    public function render(Renderer $renderer): void
    {
        $size = $renderer->getSize();
        // Render your content...
    }
    
    public function handleInput(string $key): bool
    {
        // Handle keyboard input...
        return true;
    }
    
    public function getTitle(): string
    {
        return 'My Custom Screen';
    }
    
    // ... implement other required methods
}
```

---

## 🧩 Available Components

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

---

## 🎯 Real-World Example

Here's a complete example showing all features together (see the included `console` file):

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Butschster\Commander\Application;
use Butschster\Commander\Feature\CommandBrowser\Screen\CommandsScreen;
use Butschster\Commander\Feature\CommandBrowser\Service\CommandDiscovery;
use Butschster\Commander\Feature\CommandBrowser\Service\CommandExecutor;
use Butschster\Commander\Feature\ComposerManager\Screen\ComposerManagerScreen;
use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;
use Butschster\Commander\Feature\FileBrowser\Screen\FileBrowserScreen;
use Butschster\Commander\Feature\FileBrowser\Service\FileSystemService;
use Butschster\Commander\UI\Component\Layout\MenuBar;
use Butschster\Commander\UI\Theme\ColorScheme;
use Butschster\Commander\UI\Theme\MidnightTheme;
use Symfony\Component\Console\Application as SymfonyApplication;

// Create Symfony Console Application
$symfonyApp = new SymfonyApplication('My Console Application', '1.0.0');

// Apply theme
ColorScheme::applyTheme(new MidnightTheme());

// Create and configure Commander application
$app = new Application($symfonyApp);
$app->setTargetFps(30);
$screenManager = $app->getScreenManager();

// Create services
$commandDiscovery = new CommandDiscovery($symfonyApp);
$commandExecutor = new CommandExecutor($symfonyApp);
$fileSystem = new FileSystemService();

// Setup global menu bar
$globalMenu = new MenuBar([
    'F1' => ' Help',
    'F2' => ' Commands',
    'F3' => ' Files',
    'F5' => ' Composer',
    'F10' => ' Quit',
]);
$app->setGlobalMenuBar($globalMenu);

// Register global shortcuts for screen switching
$app->registerGlobalShortcut('F2', function ($sm) use ($commandDiscovery, $commandExecutor) {
    // Switch to command browser
    $sm->popUntil(fn($screen) => $screen instanceof CommandsScreen);
    if (!($sm->getCurrentScreen() instanceof CommandsScreen)) {
        $sm->pushScreen(new CommandsScreen($commandDiscovery, $commandExecutor));
    }
});

$app->registerGlobalShortcut('F3', function ($sm) use ($fileSystem) {
    // Switch to file browser
    $sm->popUntil(fn($screen) => $screen instanceof FileBrowserScreen);
    if (!($sm->getCurrentScreen() instanceof FileBrowserScreen)) {
        $sm->pushScreen(new FileBrowserScreen($fileSystem, $sm, getcwd()));
    }
});

$app->registerGlobalShortcut('F5', function ($sm) {
    // Switch to composer manager
    $sm->popUntil(fn($screen) => $screen instanceof ComposerManagerScreen);
    if (!($sm->getCurrentScreen() instanceof ComposerManagerScreen)) {
        $composerService = new ComposerService(getcwd());
        $screen = new ComposerManagerScreen($composerService);
        $screen->setScreenManager($sm);
        $sm->pushScreen($screen);
    }
});

$app->registerGlobalShortcut('F10', function () use ($app) {
    $app->stop();
});

// Start with command browser screen
$welcomeScreen = new CommandsScreen($commandDiscovery, $commandExecutor);
$app->run($welcomeScreen);
```

**This example demonstrates:**

- ✅ All three built-in screens (Commands, Files, Composer)
- ✅ Global menu bar with function key shortcuts
- ✅ Screen switching via F2, F3, F5
- ✅ Proper screen stack management
- ✅ Service initialization
- ✅ Theme application

---

## 🎓 Architecture Overview

### Project Structure

```
src/
├── Application.php                      # Main application entry point
├── Feature/                             # Feature modules
│   ├── CommandBrowser/                  # Command browser feature
│   ├── FileBrowser/                     # File browser feature
│   └── ComposerManager/                 # Composer manager feature
├── Infrastructure/                      # Core infrastructure
│   └── Terminal/
│       ├── KeyboardHandler.php         # Keyboard input processing
│       ├── Renderer.php                # Double-buffered rendering
│       └── TerminalManager.php         # Terminal control
└── UI/                                  # UI framework
    ├── Component/                       # Reusable components
    ├── Screen/                          # Screen management
    └── Theme/                           # Color themes
```

### Key Concepts

1. **Screens** - Full-screen views (like pages in a web app)
2. **Components** - Reusable UI elements (tables, forms, panels)
3. **Double Buffering** - Prevents flickering by rendering off-screen first
4. **Event System** - Components communicate via callbacks
5. **Theme System** - Customizable color schemes

---

## 🌟 Use Cases

Commander is perfect for:

- **DevOps Tools** - Server management, deployment dashboards
- **CLI Dashboards** - System monitoring, log viewers
- **Development Tools** - Code generators, project scaffolders
- **Admin Panels** - Database managers, configuration editors
- **Interactive Wizards** - Multi-step installation processes
- **File Managers** - Custom file browsers with preview
- **Package Managers** - Dependency management UIs

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## 📄 License

MIT License. See [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

Inspired by [Midnight Commander](https://midnight-commander.org/) - the legendary file manager.
