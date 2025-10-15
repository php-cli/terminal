# Component System Architecture

## Overview

The Commander framework uses a **two-tier component system**:

1. **System Components** - Low-level, reusable UI primitives (tables, lists, panels, forms, etc.)
2. **Feature Components** - High-level, domain-specific blocks that compose system components to display business logic

This architecture promotes:

- **Separation of Concerns** - UI logic separate from business logic
- **Reusability** - System components work across all features
- **Composability** - Feature components combine system components into domain-specific views
- **Maintainability** - Changes to system components automatically propagate to all features

---

## Component Hierarchy

```
Screen (ScreenInterface)
  └── Layout Components (Grid, Panels)
      ├── Feature Components (domain-specific)
      │   └── System Components (tables, lists, text)
      └── System Components (direct usage)
```

### Example Flow

```
ComposerManagerScreen
  └── TabContainer
      └── InstalledPackagesTab (Feature Component)
          ├── GridLayout (System)
          │   ├── Panel "Packages" (System)
          │   │   └── TableComponent (System)
          │   └── Panel "Details" (System)
          │       └── TextDisplay (System)
          │           └── PackageInfoSection (Feature Component)
          │               ├── TextBlock (System)
          │               ├── LinkList (Feature Component)
          │               └── AuthorsList (Feature Component)
```

---

## System Components

System components are located in `src/UI/Component/` and provide generic, reusable UI building blocks.

### Display Components (`src/UI/Component/Display/`)

#### TableComponent

**Purpose:** Display tabular data with configurable columns, sorting, and formatting.

**When to use:**

- Displaying lists of structured data (packages, files, processes)
- Need column-based formatting
- Require per-column alignment and styling

**Example:**

```php
$table = new TableComponent([
    new TableColumn('name', 'Package', '*', TableColumn::ALIGN_LEFT,
        formatter: fn($v, $row) => ($row['isDirect'] ? '* ' : '  ') . $v,
        colorizer: fn($v, $row, $selected) => 
            $row['abandoned'] ? ColorScheme::ERROR_TEXT : ColorScheme::$NORMAL_TEXT
    ),
    new TableColumn('version', 'Version', '15%', TableColumn::ALIGN_LEFT),
    new TableColumn('status', 'Status', 15, TableColumn::ALIGN_CENTER,
        formatter: fn($v) => match($v) {
            'active' => '✓ Active',
            'error' => '✗ Error',
            default => $v
        }
    ),
]);

$table->setRows($data);
$table->onChange(fn($row, $index) => $this->updateDetails($row));
```

**Key Features:**

- Flexible column widths: fixed (`15`), percentage (`'30%'`), flex (`'*'`)
- Per-column formatters for data transformation
- Per-column colorizers for conditional styling
- Keyboard navigation with auto-scrolling
- Optional header row
- Scrollbar when content exceeds height

#### ListComponent

**Purpose:** Simple scrollable list of strings.

**When to use:**

- Simple, single-column lists
- No complex formatting needed
- Quick prototyping

**Example:**

```php
$list = new ListComponent(['Item 1', 'Item 2', 'Item 3']);
$list->setFocused(true);
$list->onSelect(fn($item, $index) => $this->handleSelection($item));
```

#### TextDisplay

**Purpose:** Scrollable text viewer with auto-scroll and word wrapping.

**When to use:**

- Displaying logs, descriptions, or multi-line text
- Read-only text content
- Need word wrapping

**Example:**

```php
$display = new TextDisplay();
$display->setText("Multi-line\ntext\ncontent");
$display->setAutoScroll(true); // Auto-scroll to bottom on new content
```

### Layout Components (`src/UI/Component/Layout/`)

#### Panel

**Purpose:** Container with border and title.

**When to use:**

- Grouping related content
- Visual separation between sections
- Need border highlighting on focus

**Example:**

```php
$panel = new Panel('Package Details', $textDisplay);
$panel->setFocused(true); // Highlights border
```

#### GridLayout

**Purpose:** Multi-column layout with flexible sizing.

**When to use:**

- Two-panel or three-panel layouts
- Need responsive column sizing
- Side-by-side content

**Example:**

```php
$grid = new GridLayout(columns: ['50%', '50%']);
$grid->setColumn(0, $leftPanel);
$grid->setColumn(1, $rightPanel);
```

#### Modal

**Purpose:** Overlay dialog for confirmations, errors, info.

**When to use:**

- User confirmation needed
- Displaying errors or warnings
- Blocking user interaction

**Example:**

```php
$modal = Modal::confirm('Delete Package', 'Are you sure?');
$modal->onClose(fn($confirmed) => $confirmed ? $this->delete() : null);
```

#### MenuBar / StatusBar

**Purpose:** Top menu and bottom status bars.

**When to use:**

- Global navigation shortcuts
- Screen-level keyboard hints
- Status information

**Example:**

```php
$menu = new MenuBar(['F1' => 'Help', 'F10' => 'Quit']);
$status = new StatusBar(['Enter' => 'Select', 'Tab' => 'Switch Panel']);
```

### Input Components (`src/UI/Component/Input/`)

#### FormComponent

**Purpose:** Multi-field input form with validation.

**When to use:**

- Collecting user input
- Need field validation
- Multiple related inputs

**Example:**

```php
$form = new FormComponent();
$form->addTextField('name', 'Name', required: true);
$form->addCheckboxField('enabled', 'Enabled', default: true);
$form->onSubmit(fn($values) => $this->save($values));
```

### Text Components (`src/UI/Component/Display/Text/`)

#### Container

**Purpose:** Compose multiple text blocks with configurable spacing.

**When to use:**

- Building complex text layouts
- Need vertical spacing control
- Conditional rendering of sections

**Example:**

```php
$content = Container::create([
    TextBlock::create("Title"),
    TextBlock::newLine(),
    Section::create("Details", TextBlock::create("Content")),
])->spacing(1); // 1 line between each block
```

#### Section

**Purpose:** Titled section with optional conditional rendering.

**When to use:**

- Grouping related information under a heading
- Need conditional display
- Semantic text structure

**Example:**

```php
Section::create(
    'Dependencies',
    TextBlock::create("Total: 42")
)->displayWhen($package->hasDependencies())
```

#### TextBlock

**Purpose:** Single text element with optional formatting.

**When to use:**

- Individual lines or paragraphs
- Need text styling
- Conditional rendering

**Example:**

```php
TextBlock::create("Error occurred")
    ->color(ColorScheme::ERROR_TEXT)
    ->displayWhen($hasError)
```

---

## Feature Components

Feature components are located in `src/Feature/{FeatureName}/Component/` and encapsulate domain-specific logic.

### When to Create Feature Components

Create a feature component when:

1. **Complex Domain Logic** - The component needs business rules or data transformations
2. **Reusability Within Feature** - Used in multiple tabs/screens within the same feature
3. **Encapsulation** - Hides implementation details from parent screens
4. **Testability** - Isolates logic for easier unit testing

### Examples from ComposerManager Feature

#### PackageInfoSection

**Purpose:** Display package metadata (name, version, license).

**Location:** `src/Feature/ComposerManager/Component/PackageInfoSection.php`

**Why it's a Feature Component:**

- Understands `PackageInfo` domain model
- Applies Composer-specific formatting rules
- Reusable across multiple tabs (Installed, Outdated, Security)

**Example:**

```php
final class PackageInfoSection
{
    public static function create(PackageInfo $package): Container
    {
        return Container::create([
            TextBlock::create($package->name)->bold(),
            TextBlock::create("Version: {$package->version}"),
            TextBlock::create("License: " . implode(', ', $package->license))
                ->displayWhen(!empty($package->license)),
        ])->spacing(0);
    }
}
```

**Usage:**

```php
// In InstalledPackagesTab
$details = Container::create([
    PackageInfoSection::create($packageInfo),
    Section::create('Description', TextBlock::create($packageInfo->description)),
]);
```

#### AuthorsList

**Purpose:** Format package authors with emails.

**Why it's a Feature Component:**

- Composer-specific author data structure
- Custom formatting rules
- Reusable presentation logic

**Example:**

```php
final class AuthorsList
{
    public static function create(array $authors): Container
    {
        return Container::create(
            array_map(
                fn($author) => TextBlock::create(
                    "  • {$author['name']}" . 
                    (isset($author['email']) ? " <{$author['email']}>" : '')
                ),
                $authors
            )
        )->spacing(0);
    }
}
```

#### LinkList

**Purpose:** Display clickable links (homepage, repository).

**Why it's a Feature Component:**

- Domain-specific link types
- Composer package link conventions
- Reusable across package details

---

## Creating New Screens: Best Practices

### 1. Identify Your Data Structure

Before building UI, understand your data:

```php
// Example: Process Monitor
$processData = [
    ['pid' => 1234, 'name' => 'nginx', 'cpu' => 12.5, 'memory' => 1048576],
    ['pid' => 5678, 'name' => 'php-fpm', 'cpu' => 85.2, 'memory' => 2097152],
];
```

### 2. Choose Appropriate System Components

**Use TableComponent when:**

- Data has multiple columns
- Need column-specific formatting
- Different alignments per column

**Use ListComponent when:**

- Simple string list
- Single column display
- Minimal formatting

**Use TextDisplay when:**

- Multi-line text content
- Logs or descriptions
- Read-only information

### 3. Structure Your Screen

Use **GridLayout** for multi-panel layouts:

```php
final class MyScreen implements ScreenInterface
{
    private GridLayout $layout;
    private Panel $leftPanel;
    private Panel $rightPanel;

    public function __construct()
    {
        $this->initializeComponents();
    }

    private function initializeComponents(): void
    {
        // Left: Data table
        $this->table = new TableComponent([...]);
        $this->leftPanel = new Panel('Data', $this->table);

        // Right: Details
        $this->details = new TextDisplay();
        $this->rightPanel = new Panel('Details', $this->details);

        // Layout
        $this->layout = new GridLayout(columns: ['60%', '40%']);
        $this->layout->setColumn(0, $this->leftPanel);
        $this->layout->setColumn(1, $this->rightPanel);
    }
}
```

### 4. Create Feature Components for Complex Data

When displaying complex data in TextDisplay, **always** create Feature Components:

```php
// ❌ BAD: Logic in Screen
$detailsText = "Name: {$process['name']}\n";
$detailsText .= "CPU: {$process['cpu']}%\n";
$detailsText .= "Memory: " . formatMemory($process['memory']);
$this->details->setText($detailsText);

// ✅ GOOD: Feature Component
final class ProcessDetails
{
    public static function create(array $process): Container
    {
        return Container::create([
            TextBlock::create("Name: {$process['name']}")->bold(),
            TextBlock::create("CPU: {$process['cpu']}%")
                ->color(self::getCpuColor($process['cpu'])),
            TextBlock::create("Memory: " . self::formatMemory($process['memory'])),
        ])->spacing(0);
    }

    private static function getCpuColor(float $cpu): string
    {
        return match(true) {
            $cpu > 80 => ColorScheme::ERROR_TEXT,
            $cpu > 50 => ColorScheme::WARNING_TEXT,
            default => ColorScheme::$NORMAL_TEXT,
        };
    }

    private static function formatMemory(int $bytes): string
    {
        return round($bytes / 1048576, 2) . ' MB';
    }
}

// Usage in Screen
$this->details->setText(ProcessDetails::create($process));
```

### 5. Pattern: Two-Panel Screen with Details

**Standard pattern for list + details:**

```php
final class MyFeatureScreen implements ScreenInterface
{
    private GridLayout $layout;
    private TableComponent $table;
    private TextDisplay $details;
    private int $focusedPanel = 0;

    public function __construct(private readonly MyService $service)
    {
        $this->initializeComponents();
    }

    private function initializeComponents(): void
    {
        // Create table
        $this->table = $this->createTable();
        
        // Create details display
        $this->details = new TextDisplay();

        // Wrap in panels
        $leftPanel = new Panel('Items', $this->table);
        $rightPanel = new Panel('Details', Padding::symmetric($this->details, 2, 1));

        // Layout
        $this->layout = new GridLayout(columns: ['55%', '45%']);
        $this->layout->setColumn(0, $leftPanel);
        $this->layout->setColumn(1, $rightPanel);
    }

    private function createTable(): TableComponent
    {
        $table = new TableComponent([
            new TableColumn('id', 'ID', 10, TableColumn::ALIGN_RIGHT),
            new TableColumn('name', 'Name', '*', TableColumn::ALIGN_LEFT,
                formatter: fn($v, $row) => $this->formatName($v, $row),
                colorizer: fn($v, $row, $sel) => $this->getNameColor($row, $sel)
            ),
            new TableColumn('status', 'Status', 15, TableColumn::ALIGN_CENTER,
                formatter: fn($v) => $this->formatStatus($v),
                colorizer: fn($v, $row, $sel) => $this->getStatusColor($v, $sel)
            ),
        ]);

        $table->onChange(fn($row, $idx) => $this->showDetails($row));
        $table->onSelect(fn($row, $idx) => $this->openDetailsScreen($row));

        return $table;
    }

    private function showDetails(array $row): void
    {
        // ✅ Use Feature Component
        $this->details->setText(MyItemDetails::create($row));
    }

    public function handleInput(string $key): bool
    {
        if ($key === 'TAB') {
            $this->focusedPanel = ($this->focusedPanel + 1) % 2;
            $this->updateFocus();
            return true;
        }

        return $this->focusedPanel === 0
            ? $this->layout->handleInput($key)
            : $this->details->handleInput($key);
    }
}
```

---

## Component Creation Checklist

### Creating a System Component

Use when the component is **generic** and **framework-level**:

- [ ] Component is reusable across multiple features
- [ ] No domain-specific logic
- [ ] Pure UI presentation
- [ ] Extends `AbstractComponent`
- [ ] Implements `ComponentInterface`
- [ ] Located in `src/UI/Component/`
- [ ] Well-documented with examples
- [ ] Unit tested

### Creating a Feature Component

Use when the component is **domain-specific**:

- [ ] Contains business logic or domain knowledge
- [ ] Used within a single feature
- [ ] Composes system components
- [ ] Static factory method (e.g., `create()`)
- [ ] Located in `src/Feature/{FeatureName}/Component/`
- [ ] Returns system components (Container, TextBlock, etc.)
- [ ] Documented with usage examples

---

## Anti-Patterns to Avoid

### ❌ Don't: Inline Complex Logic in Screens

```php
// BAD
private function showDetails(array $package): void
{
    $text = "Package: {$package['name']}\n";
    $text .= "Version: {$package['version']}\n";
    if (!empty($package['authors'])) {
        $text .= "Authors:\n";
        foreach ($package['authors'] as $author) {
            $text .= "  • {$author['name']}";
            if (isset($author['email'])) {
                $text .= " <{$author['email']}>";
            }
            $text .= "\n";
        }
    }
    // ... 50+ more lines
    $this->details->setText($text);
}
```

**Why it's bad:**

- Mixes presentation logic with screen logic
- Hard to test
- Not reusable
- Difficult to maintain

### ✅ Do: Extract to Feature Component

```php
// GOOD
private function showDetails(array $package): void
{
    $this->details->setText(
        Container::create([
            PackageInfoSection::create($package),
            Section::create('Authors', AuthorsList::create($package['authors']))
                ->displayWhen(!empty($package['authors'])),
        ])
    );
}
```

### ❌ Don't: Create Feature Components for Simple Formatting

```php
// BAD: Overkill for simple formatting
final class TimestampFormatter
{
    public static function create(int $timestamp): TextBlock
    {
        return TextBlock::create(date('Y-m-d H:i:s', $timestamp));
    }
}
```

**Why it's bad:**

- Too granular
- No domain logic
- Better as inline formatter

### ✅ Do: Use Inline Formatters for Simple Cases

```php
// GOOD
new TableColumn('created', 'Created', 20, TableColumn::ALIGN_RIGHT,
    formatter: fn($ts) => date('Y-m-d H:i:s', $ts)
)
```

### ❌ Don't: Duplicate System Components

```php
// BAD: Creating a custom table for a specific feature
final class PackageTable extends AbstractComponent
{
    // ... reimplementing TableComponent logic
}
```

**Why it's bad:**

- Code duplication
- Maintenance burden
- Breaks consistency

### ✅ Do: Configure System Components

```php
// GOOD: Use TableComponent with feature-specific configuration
$table = new TableComponent([
    new TableColumn('name', 'Package', '*', 
        formatter: fn($v, $row) => PackageFormatter::formatName($v, $row),
        colorizer: fn($v, $row, $sel) => PackageFormatter::getColor($row, $sel)
    ),
]);
```

---

## Real-World Example: Building a Git Branch Manager Screen

### 1. Plan Your Data Structure

```php
$branches = [
    ['name' => 'main', 'commit' => 'abc123', 'date' => 1704067200, 'isCurrent' => true],
    ['name' => 'feature/auth', 'commit' => 'def456', 'date' => 1704153600, 'isCurrent' => false],
];
```

### 2. Create Feature Components

```php
// src/Feature/Git/Component/BranchDetails.php
final class BranchDetails
{
    public static function create(array $branch, array $commits): Container
    {
        return Container::create([
            TextBlock::create("Branch: {$branch['name']}")->bold(),
            TextBlock::create("Last Commit: {$branch['commit']}"),
            TextBlock::create("Date: " . date('Y-m-d H:i:s', $branch['date']))->dim(),
            TextBlock::newLine(),
            Section::create('Recent Commits', CommitList::create($commits)),
        ])->spacing(1);
    }
}

// src/Feature/Git/Component/CommitList.php
final class CommitList
{
    public static function create(array $commits): Container
    {
        return Container::create(
            array_map(
                fn($c) => TextBlock::create("  {$c['hash']} - {$c['message']}")->dim(),
                array_slice($commits, 0, 10)
            )
        )->spacing(0);
    }
}
```

### 3. Create Screen with System Components

```php
// src/Feature/Git/Screen/BranchManagerScreen.php
final class BranchManagerScreen implements ScreenInterface
{
    private GridLayout $layout;
    private TableComponent $table;
    private TextDisplay $details;

    public function __construct(private readonly GitService $git)
    {
        $this->initializeComponents();
    }

    private function initializeComponents(): void
    {
        // Create table with custom formatters
        $this->table = new TableComponent([
            new TableColumn('name', 'Branch', '50%', TableColumn::ALIGN_LEFT,
                formatter: fn($v, $row) => ($row['isCurrent'] ? '* ' : '  ') . $v,
                colorizer: fn($v, $row, $sel) => $row['isCurrent'] 
                    ? ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_GREEN, ColorScheme::BOLD)
                    : ($sel ? ColorScheme::$SELECTED_TEXT : ColorScheme::$NORMAL_TEXT)
            ),
            new TableColumn('commit', 'Commit', '15%', TableColumn::ALIGN_LEFT),
            new TableColumn('date', 'Last Updated', '*', TableColumn::ALIGN_RIGHT,
                formatter: fn($ts) => date('Y-m-d H:i', $ts)
            ),
        ]);

        $this->table->onChange(fn($row, $idx) => $this->showBranchDetails($row));
        $this->table->onSelect(fn($row, $idx) => $this->checkoutBranch($row['name']));

        // Create details display
        $this->details = new TextDisplay();

        // Layout
        $leftPanel = new Panel('Branches', $this->table);
        $rightPanel = new Panel('Details', Padding::symmetric($this->details, 2, 1));

        $this->layout = new GridLayout(columns: ['60%', '40%']);
        $this->layout->setColumn(0, $leftPanel);
        $this->layout->setColumn(1, $rightPanel);
    }

    private function showBranchDetails(array $branch): void
    {
        $commits = $this->git->getCommitsForBranch($branch['name']);
        
        // ✅ Use Feature Component
        $this->details->setText(
            BranchDetails::create($branch, $commits)
        );
    }

    private function checkoutBranch(string $branchName): void
    {
        $modal = Modal::confirm(
            'Checkout Branch',
            "Switch to branch '{$branchName}'?"
        );
        $modal->onClose(fn($confirmed) => 
            $confirmed ? $this->git->checkout($branchName) : null
        );
        // Show modal...
    }

    public function render(Renderer $renderer): void
    {
        $size = $renderer->getSize();
        $this->layout->render($renderer, 0, 1, $size['width'], $size['height'] - 1);
    }

    // ... rest of ScreenInterface implementation
}
```

---

## Guidelines Summary

### System Components (src/UI/Component/)

- **Purpose:** Generic, reusable UI primitives
- **Examples:** TableComponent, ListComponent, Panel, GridLayout
- **When to create:** Component is framework-level and feature-agnostic
- **Testing:** Unit tests for all functionality
- **Documentation:** Comprehensive with examples

### Feature Components (src/Feature/{Name}/Component/)

- **Purpose:** Domain-specific presentation logic
- **Examples:** PackageInfoSection, AuthorsList, BranchDetails
- **When to create:** Contains business rules or domain knowledge
- **Pattern:** Static factory methods returning system components
- **Composition:** Use Container, Section, TextBlock from system components

### Screen Organization

1. **Identify data structure** - Understand what you're displaying
2. **Choose system components** - TableComponent vs ListComponent vs TextDisplay
3. **Extract complex presentation** - Create Feature Components for details
4. **Use GridLayout** - For multi-panel layouts (list + details pattern)
5. **Wire up callbacks** - `onChange` for selection, `onSelect` for actions

### Decision Tree

```
Need to display data?
  ├─ Tabular with multiple columns?
  │  └─ Use TableComponent
  ├─ Simple list of strings?
  │  └─ Use ListComponent
  └─ Multi-line text/details?
     └─ Use TextDisplay
        ├─ Complex domain data?
        │  └─ Create Feature Component
        └─ Simple text?
           └─ Use TextBlock directly

Need layout?
  ├─ Multiple panels side-by-side?
  │  └─ Use GridLayout
  ├─ Single content with border?
  │  └─ Use Panel
  └─ Overlay/dialog?
     └─ Use Modal

Need input?
  ├─ Multiple fields?
  │  └─ Use FormComponent
  └─ Single field?
     └─ Use TextField/CheckboxField
```

---

## Conclusion

The two-tier component system enables:

1. **Rapid Development** - Compose screens from existing system components
2. **Consistency** - All features use the same UI primitives
3. **Maintainability** - Business logic isolated in Feature Components
4. **Testability** - Pure functions for presentation logic
5. **Scalability** - Easy to add new features without modifying framework

**Golden Rule:** If it's generic UI → System Component. If it knows about your domain → Feature Component.

When creating a new screen:

1. Use system components for structure (Table, List, Grid, Panel)
2. Extract domain-specific presentation into Feature Components
3. Compose Feature Components using Container, Section, TextBlock
4. Keep screens thin - delegate to services and components

This architecture ensures your application remains maintainable and scalable as it grows.
