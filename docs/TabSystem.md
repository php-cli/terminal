# Tab System Documentation

## Overview

The Tab System provides a reusable, component-based approach to creating tabbed interfaces in the terminal UI. It allows you to organize content into multiple tabs with keyboard-driven navigation (Ctrl+Left/Right arrows), similar to modern IDE or browser tabs.

## Architecture

### Core Components

1. **TabInterface** - Contract for all tab implementations
2. **AbstractTab** - Base class providing common tab functionality
3. **TabContainer** - Container that manages multiple tabs and handles navigation

### Component Hierarchy

```
Screen
  └─ TabContainer
      ├─ Tab Header Bar (rendered automatically)
      ├─ Active Tab Content
      └─ Status Bar (optional)
```

## Key Features

- **Automatic Tab Header Rendering** - Visual tab headers with active state highlighting
- **Keyboard Navigation** - Ctrl+Left/Right to switch tabs
- **Lifecycle Management** - onActivate/onDeactivate hooks for resource management
- **Lazy Loading** - Tabs can defer data loading until first activation
- **Dynamic Status Bar** - Automatically updates based on active tab's shortcuts
- **Focus Propagation** - Focus state automatically passed to active tab

---

## Quick Start

### 1. Creating a Simple Tab

```php
use Butschster\Commander\UI\Component\Container\AbstractTab;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Theme\ColorScheme;

final class MyCustomTab extends AbstractTab
{
    private TextDisplay $content;
    
    public function __construct()
    {
        $this->content = new TextDisplay();
    }

    public function getTitle(): string
    {
        return 'My Tab';
    }

    public function getShortcuts(): array
    {
        return [
            'Enter' => 'Select',
            'Ctrl+R' => 'Refresh',
        ];
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);
        
        // Render your content
        $this->content->render($renderer, $x, $y, $width, $height);
    }

    public function handleInput(string $key): bool
    {
        if ($key === 'CTRL_R') {
            $this->refresh();
            return true;
        }
        
        return $this->content->handleInput($key);
    }

    protected function onTabActivated(): void
    {
        // Called when tab becomes active
        // Load data, initialize state, etc.
        $this->loadData();
    }

    protected function onTabDeactivated(): void
    {
        // Called when tab is deactivated
        // Save state, cleanup resources, etc.
    }
    
    private function loadData(): void
    {
        $this->content->setText("Tab content loaded!");
    }
    
    private function refresh(): void
    {
        $this->content->setText("Data refreshed!");
    }
}
```

### 2. Using TabContainer in a Screen

```php
use Butschster\Commander\UI\Component\Container\TabContainer;
use Butschster\Commander\UI\Component\Layout\StatusBar;
use Butschster\Commander\UI\Screen\ScreenInterface;

final class MyScreen implements ScreenInterface
{
    private TabContainer $tabContainer;

    public function __construct()
    {
        $this->initializeComponents();
    }

    private function initializeComponents(): void
    {
        // Create tabs
        $tab1 = new FirstTab();
        $tab2 = new SecondTab();
        $tab3 = new ThirdTab();

        // Create tab container
        $this->tabContainer = new TabContainer([
            $tab1,
            $tab2,
            $tab3,
        ]);

        // Optional: Set status bar
        $statusBar = new StatusBar([
            'Ctrl+←/→' => 'Switch Tab',
        ]);
        $this->tabContainer->setStatusBar($statusBar, 1);
    }

    public function render(Renderer $renderer): void
    {
        $size = $renderer->getSize();
        
        // TabContainer handles everything: headers, content, status bar
        $this->tabContainer->render(
            $renderer, 
            0, 1,  // x, y (after global menu)
            $size['width'], 
            $size['height'] - 1
        );
    }

    public function handleInput(string $key): bool
    {
        // TabContainer handles Ctrl+Left/Right and delegates to active tab
        return $this->tabContainer->handleInput($key);
    }

    public function onActivate(): void
    {
        $this->tabContainer->setFocused(true);
    }

    public function onDeactivate(): void
    {
        $this->tabContainer->setFocused(false);
    }

    public function update(): void
    {
        $activeTab = $this->tabContainer->getActiveTab();
        if ($activeTab !== null) {
            $activeTab->update();
        }
    }

    public function getTitle(): string
    {
        return 'My Tabbed Screen';
    }
}
```

---

## TabInterface API

### Required Methods

#### `getTitle(): string`
Returns the title displayed in the tab header.

```php
public function getTitle(): string
{
    return "Users (42)";  // Can include dynamic info like counts
}
```

#### `getShortcuts(): array`
Returns tab-specific keyboard shortcuts for the status bar.

```php
public function getShortcuts(): array
{
    return [
        'Enter' => 'View Details',
        'Delete' => 'Remove',
        'Ctrl+R' => 'Refresh',
    ];
}
```

**Note:** TabContainer automatically adds `Ctrl+←/→ => 'Switch Tab'` to the shortcuts.

#### `onActivate(): void`
Called when the tab becomes active (visible to user).

```php
protected function onTabActivated(): void
{
    // Good place for:
    // - Loading data
    // - Starting timers
    // - Subscribing to events
    // - Updating focus
}
```

#### `onDeactivate(): void`
Called when the tab is hidden (another tab becomes active).

```php
protected function onTabDeactivated(): void
{
    // Good place for:
    // - Saving state
    // - Stopping timers
    // - Unsubscribing from events
    // - Cleanup
}
```

#### `update(): void`
Called every frame for the active tab.

```php
public function update(): void
{
    // Optional: Update animations, timers, etc.
    // Keep this fast (<16ms for 60fps)
}
```

#### `render(...): void`
Renders the tab content.

```php
public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
{
    $this->setBounds($x, $y, $width, $height);
    
    // Render your components
    $this->layout->render($renderer, $x, $y, $width, $height);
}
```

#### `handleInput(string $key): bool`
Handles keyboard input for the tab.

```php
public function handleInput(string $key): bool
{
    // Return true if input was handled
    if ($key === 'CTRL_R') {
        $this->refresh();
        return true;
    }
    
    // Delegate to child components
    return $this->content->handleInput($key);
}
```

---

## TabContainer API

### Constructor

```php
/**
 * @param array<TabInterface> $tabs Initial tabs
 */
public function __construct(array $tabs = [])
```

### Methods

#### `addTab(TabInterface $tab): void`
Add a tab dynamically.

```php
$tabContainer->addTab(new MyNewTab());
```

#### `getActiveTab(): ?TabInterface`
Get the currently active tab.

```php
$activeTab = $tabContainer->getActiveTab();
if ($activeTab instanceof MyTab) {
    // Do something specific
}
```

#### `getActiveTabIndex(): int`
Get the index of the active tab (0-based).

```php
$index = $tabContainer->getActiveTabIndex();
```

#### `switchToTab(int $index): void`
Switch to a specific tab by index.

```php
$tabContainer->switchToTab(0);  // Switch to first tab
```

#### `nextTab(): void`
Switch to the next tab (wraps around).

```php
$tabContainer->nextTab();
```

#### `previousTab(): void`
Switch to the previous tab (wraps around).

```php
$tabContainer->previousTab();
```

#### `setStatusBar(?StatusBar $statusBar, int $height = 1): void`
Set an optional status bar.

```php
$statusBar = new StatusBar(['Ctrl+←/→' => 'Switch Tab']);
$tabContainer->setStatusBar($statusBar, 1);
```

---

## Patterns & Best Practices

### Pattern 1: Lazy Loading Data

Load data only when tab is first activated to improve performance.

```php
final class LazyTab extends AbstractTab
{
    private bool $dataLoaded = false;
    private array $data = [];

    protected function onTabActivated(): void
    {
        if (!$this->dataLoaded) {
            $this->loadData();
            $this->dataLoaded = true;
        }
    }

    private function loadData(): void
    {
        // Expensive operation - only runs once
        $this->data = $this->service->fetchLargeDataset();
        $this->table->setRows($this->data);
    }
}
```

### Pattern 2: Two-Panel Layout

Common pattern for list + details view.

```php
final class TwoPanelTab extends AbstractTab
{
    private GridLayout $layout;
    private Panel $leftPanel;
    private Panel $rightPanel;
    private TableComponent $table;
    private TextDisplay $details;
    private int $focusedPanel = 0;

    public function __construct()
    {
        $this->table = new TableComponent([/* ... */]);
        $this->details = new TextDisplay();

        $this->leftPanel = new Panel('Items', $this->table);
        $this->rightPanel = new Panel('Details', $this->details);

        $this->layout = new GridLayout(columns: ['50%', '50%']);
        $this->layout->setColumn(0, $this->leftPanel);
        $this->layout->setColumn(1, $this->rightPanel);

        $this->table->onChange(function ($row, $index) {
            $this->showDetails($row);
        });
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);
        $this->layout->render($renderer, $x, $y, $width, $height);
    }

    public function handleInput(string $key): bool
    {
        if ($key === 'TAB') {
            $this->focusedPanel = ($this->focusedPanel + 1) % 2;
            $this->updateFocus();
            return true;
        }

        return $this->focusedPanel === 0
            ? $this->leftPanel->handleInput($key)
            : $this->rightPanel->handleInput($key);
    }

    private function updateFocus(): void
    {
        $this->leftPanel->setFocused($this->focusedPanel === 0);
        $this->rightPanel->setFocused($this->focusedPanel === 1);
        $this->table->setFocused($this->focusedPanel === 0);
    }

    private function showDetails(array $row): void
    {
        $this->details->setText("Details for: {$row['name']}");
    }
}
```

### Pattern 3: Stateful Tab with Refresh

Tab that maintains state and can refresh data.

```php
final class RefreshableTab extends AbstractTab
{
    private TableComponent $table;
    private array $data = [];
    private bool $isLoading = false;

    public function getTitle(): string
    {
        $count = count($this->data);
        return $this->isLoading ? "Loading..." : "Items ($count)";
    }

    public function getShortcuts(): array
    {
        return [
            'Ctrl+R' => 'Refresh',
            'Enter' => 'View',
        ];
    }

    public function handleInput(string $key): bool
    {
        if ($key === 'CTRL_R') {
            $this->refresh();
            return true;
        }

        return $this->table->handleInput($key);
    }

    protected function onTabActivated(): void
    {
        if (empty($this->data)) {
            $this->loadData();
        }
    }

    private function loadData(): void
    {
        $this->isLoading = true;
        $this->data = $this->service->fetchData();
        $this->table->setRows($this->data);
        $this->isLoading = false;
    }

    private function refresh(): void
    {
        $this->service->clearCache();
        $this->loadData();
    }
}
```

### Pattern 4: Tab with Screen Navigation

Tab that can open detail screens.

```php
final class NavigableTab extends AbstractTab
{
    private TableComponent $table;
    private ?ScreenManager $screenManager = null;

    public function __construct(
        private readonly MyService $service,
        ?ScreenManager $screenManager = null
    ) {
        $this->screenManager = $screenManager;
        $this->initializeComponents();
    }

    private function initializeComponents(): void
    {
        $this->table = new TableComponent([/* ... */]);

        $this->table->onSelect(function (array $row, int $index): void {
            $this->openDetailsScreen($row['id']);
        });
    }

    private function openDetailsScreen(string $id): void
    {
        if ($this->screenManager === null) {
            return;
        }

        $detailsScreen = new DetailsScreen($this->service, $id);
        $this->screenManager->pushScreen($detailsScreen);
    }
}
```

---

## Advanced Usage

### Dynamic Tab Titles

Update tab title based on state or data:

```php
public function getTitle(): string
{
    $count = count($this->items);
    $selected = count($this->selectedItems);
    
    if ($selected > 0) {
        return "Items ($count) - $selected selected";
    }
    
    return "Items ($count)";
}
```

### Conditional Shortcuts

Show different shortcuts based on state:

```php
public function getShortcuts(): array
{
    if ($this->hasSelection()) {
        return [
            'Enter' => 'Edit',
            'Delete' => 'Remove',
            'Ctrl+A' => 'Select All',
            'Escape' => 'Clear Selection',
        ];
    }
    
    return [
        'Enter' => 'View',
        'Ctrl+N' => 'New',
        'Ctrl+R' => 'Refresh',
    ];
}
```

### Programmatic Tab Switching

Switch tabs based on user actions:

```php
// In screen code
if ($someCondition) {
    $this->tabContainer->switchToTab(1);  // Switch to second tab
}

// Or cycle through tabs
$this->tabContainer->nextTab();
```

### Adding Tabs Dynamically

```php
// Start with base tabs
$tabContainer = new TabContainer([
    new DashboardTab(),
    new SettingsTab(),
]);

// Add tabs based on runtime conditions
if ($user->hasPermission('admin')) {
    $tabContainer->addTab(new AdminTab());
}

if ($featureFlags->isEnabled('beta')) {
    $tabContainer->addTab(new BetaFeatureTab());
}
```

---

## Keyboard Navigation

### Built-in Navigation

- **Ctrl+Left Arrow** - Previous tab
- **Ctrl+Right Arrow** - Next tab

### Tab-Specific Keys

Each tab defines its own shortcuts via `getShortcuts()`:

```php
public function getShortcuts(): array
{
    return [
        'Tab' => 'Switch Panel',     // Within tab
        'Enter' => 'Select',
        'Ctrl+R' => 'Refresh',
    ];
}
```

---

## Visual Appearance

### Tab Headers

Active tab:
```
[ Active Tab ]  Inactive Tab   Another Tab
```

- Active tab: Cyan background, black text, bold, with brackets
- Inactive tabs: Blue background, white text, normal weight, with spaces

### Example Layout

```
┌────────────────────────────────────────────────────────────┐
│ [ Installed (42) ]  Outdated (5)   Security (0)            │
├────────────────────────────────────────────────────────────┤
│ ┌──────────────────────┬─────────────────────────────────┐ │
│ │ Package List         │ Details                         │ │
│ │                      │                                 │ │
│ │ * vendor/package1    │ Package: vendor/package1        │ │
│ │   vendor/package2    │ Version: 1.2.3                  │ │
│ │   vendor/package3    │                                 │ │
│ │                      │ Description:                    │ │
│ │                      │   A useful package...           │ │
│ └──────────────────────┴─────────────────────────────────┘ │
│ Ctrl+←/→: Switch Tab  Tab: Switch Panel  Enter: Details   │
└────────────────────────────────────────────────────────────┘
```

---

## Performance Considerations

### Lazy Loading

- Load data in `onTabActivated()` only when needed
- Cache loaded data to avoid reloading on tab switch
- Use `dataLoaded` flag to track loading state

```php
protected function onTabActivated(): void
{
    if (!$this->dataLoaded) {
        $this->loadData();
        $this->dataLoaded = true;
    }
}
```

### Resource Cleanup

- Clean up resources in `onTabDeactivated()`
- Stop timers, close connections, unsubscribe from events
- Keep inactive tabs lightweight

```php
protected function onTabDeactivated(): void
{
    $this->stopPolling();
    $this->clearLargeBuffers();
}
```

### Update Loop

- Keep `update()` fast (<16ms for 60fps)
- Only update active tab (TabContainer handles this)
- Avoid heavy computations in update loop

---

## Common Patterns

### Pattern: Tab with Loading State

```php
final class LoadingTab extends AbstractTab
{
    private bool $isLoading = false;
    private array $data = [];

    public function getTitle(): string
    {
        if ($this->isLoading) {
            return 'Loading...';
        }
        
        return 'Data (' . count($this->data) . ')';
    }

    protected function onTabActivated(): void
    {
        $this->loadData();
    }

    private function loadData(): void
    {
        $this->isLoading = true;
        
        // Simulate async loading
        $this->data = $this->service->fetchData();
        
        $this->isLoading = false;
    }
}
```

### Pattern: Tab with Error Handling

```php
final class RobustTab extends AbstractTab
{
    private ?string $error = null;

    protected function onTabActivated(): void
    {
        try {
            $this->loadData();
            $this->error = null;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        if ($this->error !== null) {
            $renderer->writeAt(
                $x, 
                $y, 
                "Error: {$this->error}", 
                ColorScheme::ERROR_TEXT
            );
            return;
        }

        $this->content->render($renderer, $x, $y, $width, $height);
    }
}
```

---

## Troubleshooting

### Issue: Tab content not rendering

**Solution:** Ensure you call `setBounds()` in `render()`:

```php
public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
{
    $this->setBounds($x, $y, $width, $height);  // Important!
    
    // Now render content
    $this->content->render($renderer, $x, $y, $width, $height);
}
```

### Issue: Tab shortcuts not showing in status bar

**Solution:** Implement `getShortcuts()` properly:

```php
public function getShortcuts(): array
{
    return [
        'Enter' => 'Select',
        'Ctrl+R' => 'Refresh',
    ];
}
```

### Issue: Data loads multiple times

**Solution:** Use lazy loading pattern with flag:

```php
private bool $dataLoaded = false;

protected function onTabActivated(): void
{
    if (!$this->dataLoaded) {
        $this->loadData();
        $this->dataLoaded = true;
    }
}
```

### Issue: Focus not working correctly

**Solution:** Implement `setFocused()` and propagate to children:

```php
public function setFocused(bool $focused): void
{
    parent::setFocused($focused);
    
    // Propagate to child components
    $this->table->setFocused($focused);
}
```

---

## Comparison: Old vs New Approach

### Old Approach (Manual Tab Management)

```php
final class OldScreen implements ScreenInterface
{
    private int $currentTab = 0;
    private TableComponent $table1;
    private TableComponent $table2;
    private TableComponent $table3;

    public function handleInput(string $key): bool
    {
        // Manual tab switching
        if ($key === 'F1') {
            $this->currentTab = 0;
            $this->switchToTab0();
            return true;
        }
        if ($key === 'F2') {
            $this->currentTab = 1;
            $this->switchToTab1();
            return true;
        }
        // ... lots of boilerplate
        
        // Delegate to current table
        match ($this->currentTab) {
            0 => $this->table1->handleInput($key),
            1 => $this->table2->handleInput($key),
            2 => $this->table3->handleInput($key),
        };
    }

    public function render(Renderer $renderer): void
    {
        // Manually render tab headers
        // Manually switch content
        // Manually update status bar
        // ... lots of duplication
    }
}
```

### New Approach (TabContainer)

```php
final class NewScreen implements ScreenInterface
{
    private TabContainer $tabContainer;

    public function __construct()
    {
        $this->tabContainer = new TabContainer([
            new FirstTab(),
            new SecondTab(),
            new ThirdTab(),
        ]);
    }

    public function handleInput(string $key): bool
    {
        return $this->tabContainer->handleInput($key);
    }

    public function render(Renderer $renderer): void
    {
        $size = $renderer->getSize();
        $this->tabContainer->render($renderer, 0, 1, $size['width'], $size['height'] - 1);
    }
}
```

**Benefits:**
- Less boilerplate (50-70% code reduction)
- Better separation of concerns
- Reusable tab logic
- Consistent navigation (Ctrl+Arrow keys)
- Automatic header rendering
- Dynamic status bar updates

---

## Real-World Example: Composer Manager

See `ComposerManagerScreen` for a complete real-world implementation:

- **InstalledPackagesTab** - Shows all packages with outdated status
- **OutdatedPackagesTab** - Shows only outdated packages (lazy loaded)
- **SecurityAuditTab** - Shows security vulnerabilities (lazy loaded)

Each tab:
- Has its own two-panel layout (list + details)
- Handles its own data loading
- Defines its own keyboard shortcuts
- Manages its own state independently

---

## Summary

The Tab System provides:

✅ **Modular Architecture** - Each tab is self-contained  
✅ **Lazy Loading** - Load data only when needed  
✅ **Lifecycle Hooks** - onActivate/onDeactivate for resource management  
✅ **Keyboard Navigation** - Ctrl+Left/Right to switch tabs  
✅ **Dynamic Status Bar** - Updates based on active tab  
✅ **Focus Management** - Automatic focus propagation  
✅ **Visual Consistency** - Automatic tab header rendering  

Use tabs whenever you have:
- Multiple views of related data
- Features that should be grouped but kept separate
- Complex screens that benefit from organization
- Content that should be lazy-loaded for performance
