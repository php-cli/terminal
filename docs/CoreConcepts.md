# MC-Style PHP Console Application

## Project Overview

This is a production-ready, component-based Midnight Commander-style terminal application built on Symfony Console. It
provides a fullscreen, keyboard-driven interface with double-buffered rendering to eliminate flickering.

---

## 📁 Project Structure

```
src/
├── Application.php                      # Main application entry point
├── Feature/                             # Feature modules
│   ├── CommandBrowser/                  # Command browser feature
│   │   ├── Screen/
│   │   │   └── CommandsScreen.php      # Main screen: browse & execute commands
│   │   └── Service/
│   │       ├── CommandDiscovery.php    # Discovers Symfony commands
│   │       ├── CommandExecutor.php     # Executes commands & captures output
│   │       └── CommandMetadata.php     # Command metadata structures
│   ├── FileBrowser/                     # File browser feature (example)
│   │   ├── Screen/
│   │   │   └── FileBrowserScreen.php
│   │   ├── Component/
│   │   │   ├── FileListComponent.php   # Custom file list component
│   │   │   └── FilePreviewComponent.php
│   │   └── Service/
│   │       └── FileSystemService.php
│   └── Example/
│       └── Command/
│           └── ExampleCommand.php       # Sample Symfony command
├── Infrastructure/                      # Core infrastructure
│   └── Terminal/
│       ├── KeyboardHandler.php         # Keyboard input processing
│       ├── Renderer.php                # Double-buffered rendering
│       └── TerminalManager.php         # Terminal control (size, raw mode)
└── UI/                                  # UI framework
    ├── Component/                       # Reusable components
    │   ├── ComponentInterface.php      # Component contract
    │   ├── AbstractComponent.php       # Base component implementation
    │   ├── Display/                    # Display-only components
    │   │   ├── ListComponent.php       # Scrollable list
    │   │   └── TextDisplay.php         # Scrollable text viewer
    │   ├── Input/                      # Input components
    │   │   ├── FormComponent.php       # Form container
    │   │   ├── FormField.php           # Base field class
    │   │   ├── TextField.php           # Text input field
    │   │   ├── CheckboxField.php       # Checkbox field
    │   │   └── ArrayField.php          # Comma-separated array field
    │   └── Layout/                     # Layout components
    │       ├── MenuBar.php             # Top menu bar
    │       ├── StatusBar.php           # Bottom status bar
    │       ├── Panel.php               # Panel with border
    │       └── Modal.php               # Modal dialog
    ├── Screen/                          # Screen management
    │   ├── ScreenInterface.php         # Screen contract
    │   └── ScreenManager.php           # Screen navigation stack
    └── Theme/
        └── ColorScheme.php             # MC color definitions
```

---

## 🎯 Core Concepts

### 1. **Application Flow**

```
Application::run()
    ↓
Initialize Terminal (raw mode, alt screen, hide cursor)
    ↓
Push Initial Screen to ScreenManager
    ↓
Main Event Loop:
    ├─ Handle Keyboard Input
    ├─ Update Screen State
    ├─ Render Frame (double-buffered)
    └─ Maintain Target FPS (30 default)
    ↓
Cleanup Terminal (restore normal mode)
```

### 2. **Component Hierarchy**

```
Screen (ScreenInterface)
  ├─ MenuBar (global, optional)
  ├─ StatusBar (global, optional)
  └─ Components (ComponentInterface)
      ├─ Panel (with border & title)
      │   └─ Content Component
      │       ├─ ListComponent
      │       ├─ FormComponent
      │       ├─ TextDisplay
      │       └─ Custom Components
      └─ Modal (overlay)
```

### 3. **Rendering Pipeline**

```
Renderer::beginFrame()
    ↓
Clear back buffer (fill with blue background)
    ↓
Components write to back buffer via Renderer::writeAt()
    ↓
Renderer::endFrame()
    ↓
Diff back buffer vs front buffer
    ↓
Generate minimal ANSI sequences for changed cells only
    ↓
Flush to terminal (single write operation)
    ↓
Update front buffer
```

---

## 🚀 How to Create a New Screen

### Step 1: Create Screen Class

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\MyFeature\Screen;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Layout\MenuBar;
use Butschster\Commander\UI\Component\Layout\Panel;
use Butschster\Commander\UI\Component\Layout\StatusBar;
use Butschster\Commander\UI\Component\Display\ListComponent;
use Butschster\Commander\UI\Screen\ScreenInterface;

final class MyCustomScreen implements ScreenInterface
{
    private MenuBar $menuBar;
    private StatusBar $statusBar;
    private Panel $mainPanel;
    private ListComponent $list;
    
    public function __construct()
    {
        $this->initializeComponents();
    }
    
    private function initializeComponents(): void
    {
        // Top menu
        $this->menuBar = new MenuBar([
            'F1' => 'Help',
            'F10' => 'Quit',
        ]);
        
        // Bottom status bar
        $this->statusBar = new StatusBar([
            'F1' => 'Help',
            'F10' => 'Quit',
            '↑↓' => 'Navigate',
            'Enter' => 'Select',
        ]);
        
        // Main content
        $this->list = new ListComponent(['Item 1', 'Item 2', 'Item 3']);
        $this->list->setFocused(true);
        
        // Set up callbacks
        $this->list->onSelect(function (string $item, int $index) {
            // Handle item selection
        });
        
        // Wrap in panel
        $this->mainPanel = new Panel('My List', $this->list);
        $this->mainPanel->setFocused(true);
    }
    
    public function render(Renderer $renderer): void
    {
        $size = $renderer->getSize();
        $width = $size['width'];
        $height = $size['height'];
        
        // Render menu bar at top
        $this->menuBar->render($renderer, 0, 0, $width, 1);
        
        // Render status bar at bottom
        $this->statusBar->render($renderer, 0, $height - 1, $width, 1);
        
        // Render main panel in between
        $panelHeight = $height - 2;
        $this->mainPanel->render($renderer, 0, 1, $width, $panelHeight);
    }
    
    public function handleInput(string $key): bool
    {
        // Global shortcuts
        if ($key === 'F10') {
            return false; // Let application handle quit
        }
        
        // Delegate to main panel
        return $this->mainPanel->handleInput($key);
    }
    
    public function onActivate(): void
    {
        // Called when screen becomes active
    }
    
    public function onDeactivate(): void
    {
        // Called when screen is hidden
    }
    
    public function update(): void
    {
        // Called every frame (optional state updates)
    }
    
    public function getTitle(): string
    {
        return 'My Custom Screen';
    }
}
```

### Step 2: Register and Push Screen

```php
use Butschster\Commander\Application;
use Butschster\Commander\Feature\MyFeature\Screen\MyCustomScreen;

$app = new Application($symfonyApp);

// Create and push your screen
$myScreen = new MyCustomScreen();
$app->getScreenManager()->pushScreen($myScreen);

// Run application
$app->run($myScreen);
```

---

## ⌨️ Keyboard Handling

### Available Key Codes

The `KeyboardHandler` maps raw terminal input to logical key codes:

**Navigation Keys:**

- `UP`, `DOWN`, `LEFT`, `RIGHT` - Arrow keys
- `PAGE_UP`, `PAGE_DOWN` - Page scrolling
- `HOME`, `END` - Jump to start/end

**Action Keys:**

- `ENTER` - Confirm/select
- `TAB` - Switch focus/next field
- `ESCAPE` - Cancel/go back
- `BACKSPACE`, `DELETE` - Text editing

**Function Keys:**

- `F1` through `F12` - Function keys

**Control Keys:**

- `CTRL_C` - Interrupt (handled globally)
- `CTRL_D`, `CTRL_Z` - Additional control keys

**Regular Keys:**

- All printable characters (a-z, 0-9, symbols)

### Handling Input in Components

```php
public function handleInput(string $key): bool
{
    switch ($key) {
        case 'UP':
            // Move selection up
            return true;
            
        case 'DOWN':
            // Move selection down
            return true;
            
        case 'ENTER':
            // Confirm action
            if ($this->onSelect !== null) {
                ($this->onSelect)();
            }
            return true;
            
        case 'ESCAPE':
            // Cancel or go back
            return true;
            
        default:
            // Handle regular character input
            if (mb_strlen($key) === 1) {
                // It's a printable character
                $this->value .= $key;
                return true;
            }
            return false; // Unhandled
    }
}
```

### Input Event Bubbling

Input flows from top to bottom:

1. **Screen** receives input first
2. If not handled, routes to **focused component**
3. Component can handle or return `false` to bubble up
4. Parent components can intercept unhandled input

```php
// In Screen
public function handleInput(string $key): bool
{
    // Handle global shortcuts first
    if ($key === 'F10') {
        $this->screenManager->popScreen();
        return true;
    }
    
    // Delegate to focused component
    return $this->mainPanel->handleInput($key);
}
```

---

## 🎨 Using Components

### ListComponent (Scrollable List)

```php
use Butschster\Commander\UI\Component\Display\ListComponent;

$list = new ListComponent([
    'Option 1',
    'Option 2',
    'Option 3',
]);

$list->setFocused(true);

// Set callbacks
$list->onChange(function (?string $item, int $index) {
    // Called when selection changes (arrow keys)
    echo "Selected: $item at index $index\n";
});

$list->onSelect(function (string $item, int $index) {
    // Called when item is confirmed (Enter key)
    echo "Confirmed: $item\n";
});

// Render
$list->render($renderer, 0, 0, 40, 20);
```

**Features:**

- Auto-scrolling when navigating beyond visible area
- Scrollbar indicator when content doesn't fit
- Keyboard: `↑↓`, `Page Up/Down`, `Home/End`, `Enter`

### FormComponent (Input Form)

```php
use Butschster\Commander\UI\Component\Input\FormComponent;

$form = new FormComponent();

// Add text field
$form->addTextField(
    name: 'username',
    label: 'Username',
    required: true,
    default: '',
    description: 'Enter your username'
);

// Add checkbox
$form->addCheckboxField(
    name: 'remember',
    label: 'Remember me',
    default: false,
    description: 'Keep me logged in'
);

// Add array field (comma-separated)
$form->addArrayField(
    name: 'tags',
    label: 'Tags',
    required: false,
    description: 'Comma-separated tags'
);

// Set callbacks
$form->onSubmit(function (array $values) {
    // $values = ['username' => '...', 'remember' => true, 'tags' => [...]]
    var_dump($values);
});

$form->onCancel(function () {
    echo "Form cancelled\n";
});

// Validate
$errors = $form->validate();
if (!empty($errors)) {
    // Show errors
}

// Render
$form->render($renderer, 0, 0, 60, 20);
```

**Navigation:**

- `↑↓` / `Tab` - Move between fields
- `←→` - Move cursor in text fields
- `Space` / `Enter` - Toggle checkboxes
- `F2` / `Enter` on last field - Submit form
- `Escape` - Cancel form

### TextDisplay (Scrollable Text Viewer)

```php
use Butschster\Commander\UI\Component\Display\TextDisplay;

$display = new TextDisplay();

// Set content
$display->setText("Line 1\nLine 2\nLine 3");

// Append content
$display->appendText("\nLine 4");

// Clear content
$display->clear();

// Auto-scroll to bottom
$display->setAutoScroll(true);

// Render
$display->render($renderer, 0, 0, 60, 20);
```

**Features:**

- Auto-scrolling (optional)
- Word wrapping for long lines
- Scrollbar when content exceeds height
- Keyboard: `↑↓`, `Page Up/Down`, `Home/End`

### Panel (Container with Border)

```php
use Butschster\Commander\UI\Component\Layout\Panel;
use Butschster\Commander\UI\Component\Display\ListComponent;

$list = new ListComponent(['Item 1', 'Item 2']);
$panel = new Panel('My Panel Title', $list);

// Change title
$panel->setTitle('Updated Title');

// Change content
$panel->setContent($anotherComponent);

// Focus (highlights border)
$panel->setFocused(true);

// Render
$panel->render($renderer, 0, 0, 40, 20);
```

**Features:**

- Automatic border drawing (box-drawing characters)
- Title rendering in border
- Focus state (bright border when focused)
- Propagates focus to content component

### Modal (Overlay Dialog)

```php
use Butschster\Commander\UI\Component\Layout\Modal;

// Info modal
$modal = Modal::info('Information', 'This is an info message.');

// Error modal
$modal = Modal::error('Error', 'Something went wrong!');

// Warning modal
$modal = Modal::warning('Warning', 'Are you sure?');

// Confirmation modal
$modal = Modal::confirm('Confirm', 'Do you want to proceed?');
$modal->onClose(function ($confirmed) {
    if ($confirmed) {
        echo "User confirmed\n";
    } else {
        echo "User cancelled\n";
    }
});

// Custom modal with buttons
$modal = new Modal('Custom', 'Choose an option', Modal::TYPE_INFO);
$modal->setButtons([
    'Option 1' => function () { echo "Option 1 selected\n"; },
    'Option 2' => function () { echo "Option 2 selected\n"; },
    'Cancel' => function () { echo "Cancelled\n"; },
]);

// Set size
$modal->setSize(60, 15);

// Render (must be rendered AFTER screen content)
$modal->render($renderer, 0, 0, $screenWidth, $screenHeight);
```

**Features:**

- Darkened overlay background
- Shadow effect for depth
- Centered positioning
- Icon based on type (✓, ✗, ⚠, ℹ)
- Word-wrapped content
- Keyboard: `←→` / `Tab` - Navigate buttons, `Enter` - Confirm, `Escape` - Cancel

---

## 🎨 Color Scheme (ColorScheme.php)

### Predefined Color Combinations

```php
use Butschster\Commander\UI\Theme\ColorScheme;

// Normal text (blue background, white text)
ColorScheme::NORMAL_TEXT

// Selected item (cyan background, black text)
ColorScheme::SELECTED_TEXT

// Menu bar (cyan background, black text)
ColorScheme::MENU_TEXT
ColorScheme::MENU_HOTKEY // Yellow hotkeys

// Status bar (cyan background, black text)
ColorScheme::STATUS_TEXT
ColorScheme::STATUS_KEY  // Bold white keys

// Borders
ColorScheme::ACTIVE_BORDER    // Bright white (focused)
ColorScheme::INACTIVE_BORDER  // Gray (unfocused)

// Input fields
ColorScheme::INPUT_TEXT       // Black background, yellow text

// Errors/Warnings
ColorScheme::ERROR_TEXT
ColorScheme::WARNING_TEXT
```

### Custom Color Combinations

```php
use Butschster\Commander\UI\Theme\ColorScheme;

// Combine multiple codes
$color = ColorScheme::combine(
    ColorScheme::BG_BLUE,
    ColorScheme::FG_YELLOW,
    ColorScheme::BOLD
);

// Use in rendering
$renderer->writeAt(10, 5, 'Bold Yellow on Blue', $color);
```

### Available ANSI Codes

**Foreground:**
`FG_BLACK`, `FG_RED`, `FG_GREEN`, `FG_YELLOW`, `FG_BLUE`, `FG_MAGENTA`, `FG_CYAN`, `FG_WHITE`, `FG_GRAY`,
`FG_BRIGHT_WHITE`

**Background:**
`BG_BLACK`, `BG_RED`, `BG_GREEN`, `BG_YELLOW`, `BG_BLUE`, `BG_MAGENTA`, `BG_CYAN`, `BG_WHITE`

**Styles:**
`BOLD`, `DIM`, `ITALIC`, `UNDERLINE`, `BLINK`, `REVERSE`

---

## 🔧 Screen Navigation

### ScreenManager API

```php
use Butschster\Commander\UI\Screen\ScreenManager;

$screenManager = new ScreenManager();

// Push new screen (navigate forward)
$screenManager->pushScreen($newScreen);

// Pop current screen (go back)
$poppedScreen = $screenManager->popScreen();

// Replace current screen (no back navigation)
$screenManager->replaceScreen($replacementScreen);

// Get current screen
$current = $screenManager->getCurrentScreen();

// Get stack depth
$depth = $screenManager->getDepth(); // Number of screens in stack

// Check if screens exist
if ($screenManager->hasScreens()) {
    // ...
}

// Clear all screens
$screenManager->clear();

// Pop until condition met
$screenManager->popUntil(function (ScreenInterface $screen) {
    return $screen instanceof WelcomeScreen;
});
```

### Navigation Patterns

**Modal Flow (with back navigation):**

```php
// User navigates: Screen A → Screen B → Screen C
$screenManager->pushScreen($screenB); // Can go back to A
$screenManager->pushScreen($screenC); // Can go back to B

// User presses Escape or Back
$screenManager->popScreen(); // Returns to B
$screenManager->popScreen(); // Returns to A
```

**Replace Flow (no back navigation):**

```php
// User navigates: Screen A → Screen B (no back to A)
$screenManager->replaceScreen($screenB);

// Escape will exit application, not go back
```

---

## 📊 Example: Two-Panel Screen

```php
final class TwoPanelScreen implements ScreenInterface
{
    private Panel $leftPanel;
    private Panel $rightPanel;
    private int $focusedPanelIndex = 0;
    
    public function __construct()
    {
        $leftList = new ListComponent(['Item 1', 'Item 2']);
        $this->leftPanel = new Panel('Left', $leftList);
        $this->leftPanel->setFocused(true);
        
        $rightText = new TextDisplay('Details appear here');
        $this->rightPanel = new Panel('Right', $rightText);
    }
    
    public function render(Renderer $renderer): void
    {
        $size = $renderer->getSize();
        $width = $size['width'];
        $height = $size['height'];
        
        // 40% left, 60% right split
        $leftWidth = (int) ($width * 0.4);
        $rightWidth = $width - $leftWidth;
        
        $this->leftPanel->render($renderer, 0, 0, $leftWidth, $height);
        $this->rightPanel->render($renderer, $leftWidth, 0, $rightWidth, $height);
    }
    
    public function handleInput(string $key): bool
    {
        if ($key === 'TAB') {
            // Switch focus between panels
            $this->focusedPanelIndex = ($this->focusedPanelIndex + 1) % 2;
            $this->leftPanel->setFocused($this->focusedPanelIndex === 0);
            $this->rightPanel->setFocused($this->focusedPanelIndex === 1);
            return true;
        }
        
        // Delegate to focused panel
        if ($this->focusedPanelIndex === 0) {
            return $this->leftPanel->handleInput($key);
        } else {
            return $this->rightPanel->handleInput($key);
        }
    }
    
    // ... other methods
}
```

---

## 🔍 Finding What You Need

| Need                        | Look In                                                 |
|-----------------------------|---------------------------------------------------------|
| **Create new screen**       | Implement `ScreenInterface`, see `CommandsScreen.php`   |
| **Create custom component** | Extend `AbstractComponent`, see `FileListComponent.php` |
| **Handle keyboard input**   | Check `KeyboardHandler.php` for key codes               |
| **Change colors**           | Modify `ColorScheme.php` constants                      |
| **Add menu items**          | Pass array to `MenuBar` constructor                     |
| **Show modal dialog**       | Use `Modal::info/error/warning/confirm()`               |
| **Execute Symfony command** | Use `CommandExecutor::execute()`                        |
| **List files**              | Use `FileSystemService::listDirectory()`                |
| **Debug rendering**         | Check `Renderer::endFrame()` for cell updates           |

---

## 🚨 Common Pitfalls

1. **Forgetting to call `setBounds()`** in `render()` - Always store component position
2. **Not handling `setFocused()` propagation** - Unfocus children when parent loses focus
3. **Infinite loops in event handlers** - Ensure callbacks eventually return
4. **Hardcoding ANSI codes** - Always use `ColorScheme` constants
5. **Blocking operations in `render()`** - Keep rendering fast (<16ms for 60 FPS)
6. **Not checking focus state** - Only focused components should handle input
7. **Forgetting `handleResize()`** - Terminal size can change at runtime

---

This guide covers the essential patterns for building screens and components. Refer to existing implementations (
`CommandsScreen`, `FileBrowserScreen`) for complete working examples.