# Stage 6: Advanced Features (Optional)

## Overview

Advanced features that further enhance the Composer Manager but are not critical for MVP.

## Prerequisites

- Stages 1-5 completed

## Tasks

### 6.1 Create PackageActionMenu Component

A context menu that appears when pressing F5 or a designated key on a package, offering actions like Update, Remove, and Info.

**Create file**: `src/Feature/ComposerManager/Component/PackageActionMenu.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Component;

use Butschster\Commander\Feature\ComposerManager\Service\PackageInfo;
use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\ComponentInterface;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Context menu for package actions.
 */
final class PackageActionMenu implements ComponentInterface
{
    private bool $visible = false;
    private ?PackageInfo $package = null;
    private int $selectedIndex = 0;

    /** @var array<array{label: string, action: string, enabled: bool}> */
    private array $items = [];

    /** @var callable|null */
    private $onAction = null;

    public function show(PackageInfo $package, bool $hasUpdate = false): void
    {
        $this->package = $package;
        $this->visible = true;
        $this->selectedIndex = 0;

        // Build menu items based on package state
        $this->items = [
            [
                'label' => 'View Details',
                'action' => 'details',
                'enabled' => true,
            ],
            [
                'label' => 'Update Package',
                'action' => 'update',
                'enabled' => $hasUpdate,
            ],
            [
                'label' => 'Remove Package',
                'action' => 'remove',
                'enabled' => $package->isDirect, // Only direct deps can be removed
            ],
            [
                'label' => 'Open Repository',
                'action' => 'open_repo',
                'enabled' => $package->source !== null,
            ],
        ];
    }

    public function hide(): void
    {
        $this->visible = false;
        $this->package = null;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function onAction(callable $callback): self
    {
        $this->onAction = $callback;
        return $this;
    }

    #[\Override]
    public function render(Renderer $renderer, int $x, int $y, ?int $width, ?int $height): void
    {
        if (!$this->visible || $width === null || $height === null) {
            return;
        }

        $menuWidth = 25;
        $menuHeight = \count($this->items) + 2; // Items + border

        // Position menu near center
        $menuX = $x + (int) (($width - $menuWidth) / 2);
        $menuY = $y + (int) (($height - $menuHeight) / 2);

        // Draw overlay
        $dimColor = ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_BRIGHT_BLACK);
        for ($row = $y; $row < $y + $height; $row++) {
            $renderer->writeAt($x, $row, \str_repeat(' ', $width), $dimColor);
        }

        // Draw menu box
        $borderColor = ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::FG_WHITE);
        $contentColor = ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::FG_WHITE);
        $selectedColor = ColorScheme::combine(ColorScheme::BG_WHITE, ColorScheme::FG_BLACK, ColorScheme::BOLD);
        $disabledColor = ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::FG_BRIGHT_BLACK);

        // Top border
        $renderer->writeAt($menuX, $menuY, '┌' . \str_repeat('─', $menuWidth - 2) . '┐', $borderColor);

        // Menu items
        foreach ($this->items as $i => $item) {
            $rowY = $menuY + 1 + $i;
            $isSelected = $i === $this->selectedIndex;

            $label = $item['label'];
            $label = \str_pad($label, $menuWidth - 4);

            if (!$item['enabled']) {
                $color = $disabledColor;
                $prefix = '  ';
            } elseif ($isSelected) {
                $color = $selectedColor;
                $prefix = '> ';
            } else {
                $color = $contentColor;
                $prefix = '  ';
            }

            $renderer->writeAt($menuX, $rowY, '│', $borderColor);
            $renderer->writeAt($menuX + 1, $rowY, $prefix . $label, $color);
            $renderer->writeAt($menuX + $menuWidth - 1, $rowY, '│', $borderColor);
        }

        // Bottom border
        $renderer->writeAt($menuX, $menuY + $menuHeight - 1, '└' . \str_repeat('─', $menuWidth - 2) . '┘', $borderColor);
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        if (!$this->visible) {
            return false;
        }

        $input = KeyInput::from($key);

        // Navigation
        if ($input->is(Key::UP)) {
            $this->selectedIndex = ($this->selectedIndex - 1 + \count($this->items)) % \count($this->items);
            // Skip disabled items
            while (!$this->items[$this->selectedIndex]['enabled']) {
                $this->selectedIndex = ($this->selectedIndex - 1 + \count($this->items)) % \count($this->items);
            }
            return true;
        }

        if ($input->is(Key::DOWN)) {
            $this->selectedIndex = ($this->selectedIndex + 1) % \count($this->items);
            // Skip disabled items
            while (!$this->items[$this->selectedIndex]['enabled']) {
                $this->selectedIndex = ($this->selectedIndex + 1) % \count($this->items);
            }
            return true;
        }

        // Select action
        if ($input->is(Key::ENTER)) {
            $item = $this->items[$this->selectedIndex];
            if ($item['enabled'] && $this->onAction !== null && $this->package !== null) {
                ($this->onAction)($item['action'], $this->package);
            }
            $this->hide();
            return true;
        }

        // Cancel
        if ($input->is(Key::ESCAPE)) {
            $this->hide();
            return true;
        }

        return true; // Consume all input when visible
    }

    #[\Override]
    public function setFocused(bool $focused): void {}

    #[\Override]
    public function isFocused(): bool
    {
        return $this->visible;
    }

    #[\Override]
    public function update(): void {}

    #[\Override]
    public function getMinSize(): array
    {
        return ['width' => 25, 'height' => 8];
    }
}
```

---

### 6.2 Implement Package Removal with Confirmation

**Add to InstalledPackagesTab**:

```php
private PackageActionMenu $actionMenu;

// In constructor:
$this->actionMenu = new PackageActionMenu();
$this->actionMenu->onAction($this->handlePackageAction(...));

// Add handlePackageAction method:
private function handlePackageAction(string $action, PackageInfo $package): void
{
    match ($action) {
        'details' => $this->showPackageDetails($package->name),
        'update' => $this->promptUpdatePackage($package->name),
        'remove' => $this->promptRemovePackage($package),
        'open_repo' => $this->openRepository($package),
        default => null,
    };
}

private function promptRemovePackage(PackageInfo $package): void
{
    if (!$package->isDirect) {
        // Show error: can't remove transitive dependency
        return;
    }

    $this->confirmModal
        ->onConfirm(function () use ($package): void {
            $this->performPackageRemoval($package->name);
        })
        ->onCancel(fn() => null);

    $this->confirmModal->show(
        'Remove Package',
        "Remove {$package->name} from your project?"
    );
}

private function performPackageRemoval(string $packageName): void
{
    $this->isOperating = true;

    // Switch focus to right panel for output
    $this->focusedPanelIndex = 1;
    $this->updateFocus();

    $this->detailsDisplay->setText("Removing {$packageName}...\n\n");
    $this->rightPanel->setTitle("Removing: {$packageName}");

    try {
        $composerBinary = ComposerBinaryLocator::find();
        if ($composerBinary === null) {
            throw new \RuntimeException('Composer binary not found');
        }

        $this->runningProcess = new Process(
            [$composerBinary, 'remove', $packageName],
            \getcwd(),
            null,
            null,
            null,
        );

        $this->runningProcess->start();
    } catch (\Throwable $e) {
        $this->handleOperationError($e);
    }
}
```

---

### 6.3 Optimize Reverse Dependency Lookup with Graph Caching

**Add to ComposerService**:

```php
private ?array $dependencyGraph = null;

/**
 * Build dependency graph once for efficient reverse lookups.
 *
 * @return array<string, array<string>> Package name => array of packages that depend on it
 */
public function buildDependencyGraph(): array
{
    if ($this->dependencyGraph !== null) {
        return $this->dependencyGraph;
    }

    $graph = [];
    $installedRepo = $this->getInstalledRepository();

    if ($installedRepo === null) {
        return [];
    }

    foreach ($installedRepo->getPackages() as $package) {
        foreach ($package->getRequires() as $link) {
            $target = $link->getTarget();
            if (!isset($graph[$target])) {
                $graph[$target] = [];
            }
            $graph[$target][] = $package->getName();
        }
    }

    $this->dependencyGraph = $graph;
    return $graph;
}

/**
 * Get reverse dependencies using pre-built graph.
 * O(1) lookup instead of O(n).
 */
public function getReverseDependenciesFast(string $packageName): array
{
    $graph = $this->buildDependencyGraph();
    return $graph[$packageName] ?? [];
}

/**
 * Clear all caches including dependency graph.
 */
public function clearCache(): void
{
    $this->installedPackagesCache = null;
    $this->outdatedPackagesCache = null;
    $this->dependencyGraph = null;  // ← Add this
    $this->composer = null;
    $this->installedRepo = null;
}
```

**Update `getReverseDependencies()` to use the optimized version**:

```php
public function getReverseDependencies(string $packageName): array
{
    $dependents = $this->getReverseDependenciesFast($packageName);
    \sort($dependents);
    return $dependents;
}
```

---

### 6.4 Add Script Execution Confirmation for Destructive Scripts

**Identify potentially destructive scripts**:

```php
// In ScriptsTab

private const DESTRUCTIVE_SCRIPT_PATTERNS = [
    'post-',      // Post-install/update hooks
    'pre-',       // Pre-install/update hooks
    'deploy',
    'migrate',
    'db:',
    'database',
    'drop',
    'delete',
    'remove',
    'clean',
    'purge',
];

private function isDestructiveScript(string $scriptName): bool
{
    $lowerName = \strtolower($scriptName);

    foreach (self::DESTRUCTIVE_SCRIPT_PATTERNS as $pattern) {
        if (\str_contains($lowerName, $pattern)) {
            return true;
        }
    }

    return false;
}

private function runScript(string $scriptName): void
{
    if ($this->isExecuting) {
        return;
    }

    if ($this->isDestructiveScript($scriptName)) {
        $this->confirmModal
            ->onConfirm(function () use ($scriptName): void {
                $this->performScriptExecution($scriptName);
            })
            ->onCancel(fn() => null);

        $this->confirmModal->show(
            'Run Script',
            "This script may make changes. Continue?"
        );
    } else {
        $this->performScriptExecution($scriptName);
    }
}
```

---

### 6.5 Consider Packagist Search Integration

This would allow users to search for and install new packages directly from the UI.

**Add to ComposerService**:

```php
/**
 * Search Packagist for packages.
 *
 * @return array<SearchResult>
 */
public function searchPackagist(string $query, int $limit = 20): array
{
    $url = 'https://packagist.org/search.json?' . \http_build_query([
        'q' => $query,
        'per_page' => $limit,
    ]);

    $context = \stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Commander Terminal UI',
        ],
    ]);

    $response = @\file_get_contents($url, false, $context);

    if ($response === false) {
        return [];
    }

    $data = \json_decode($response, true);

    if (!\is_array($data) || !isset($data['results'])) {
        return [];
    }

    return \array_map(
        static fn(array $pkg) => new SearchResult(
            name: $pkg['name'],
            description: $pkg['description'] ?? '',
            url: $pkg['url'] ?? '',
            downloads: $pkg['downloads'] ?? 0,
        ),
        \array_slice($data['results'], 0, $limit)
    );
}
```

**Create SearchPackageTab** (new tab):

This would be a new tab that allows:
1. Type search query
2. See results from Packagist
3. Select package
4. Choose version (latest, specific, dev)
5. Install as prod or dev dependency

This is a significant feature and may warrant its own feature request.

---

## Implementation Notes

### PackageActionMenu Integration

Add to InstalledPackagesTab shortcuts:
```php
'F5' => 'Actions',
```

Handle F5 key:
```php
if ($input->is(Key::F5)) {
    $selectedPackage = $this->getSelectedPackage();
    if ($selectedPackage !== null) {
        $hasUpdate = isset($this->outdatedMap[$selectedPackage->name]);
        $this->actionMenu->show($selectedPackage, $hasUpdate);
    }
    return true;
}
```

### Graph Cache Invalidation

The dependency graph cache is invalidated when:
- `clearCache()` is called (manual refresh)
- After package install/remove operations

### Packagist Rate Limiting

Packagist has rate limits. Consider:
- Debouncing search queries (300ms delay)
- Caching recent searches
- Showing rate limit errors gracefully

---

## Verification Steps

1. **Test action menu**:
   - Press F5 on a package
   - Navigate with arrows
   - Verify disabled items are skipped
   - Select actions and verify they work

2. **Test package removal**:
   - Select a direct dependency
   - Choose Remove from action menu
   - Confirm and verify removal

3. **Test optimized graph**:
   - Profile getReverseDependencies before/after
   - Verify same results with faster execution

4. **Test script confirmation**:
   - Run a script starting with "post-"
   - Verify confirmation appears
   - Run a regular script
   - Verify no confirmation

---

## Acceptance Criteria

- [ ] PackageActionMenu shows relevant actions
- [ ] Package removal works with confirmation
- [ ] Reverse dependency lookup is O(1)
- [ ] Destructive scripts show confirmation
- [ ] Packagist search returns results (if implemented)
- [ ] All new features have tests
