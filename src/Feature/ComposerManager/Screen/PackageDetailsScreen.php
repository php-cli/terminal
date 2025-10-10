<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Screen;

use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;
use Butschster\Commander\Feature\ComposerManager\Service\PackageInfo;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Container\Direction;
use Butschster\Commander\UI\Component\Container\StackLayout;
use Butschster\Commander\UI\Component\Decorator\Padding;
use Butschster\Commander\UI\Component\Display\TableColumn;
use Butschster\Commander\UI\Component\Display\TableComponent;
use Butschster\Commander\UI\Component\Display\TextDisplay;
use Butschster\Commander\UI\Component\Layout\Panel;
use Butschster\Commander\UI\Component\Layout\StatusBar;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Package Details Screen
 * 
 * Shows comprehensive information about a package:
 * - Basic info (name, version, description, license)
 * - Authors
 * - Dependencies (requires + dev-requires)
 * - Reverse dependencies (who depends on this)
 * - Autoload configuration (namespaces, PSR-4, classmap)
 * - Binaries
 * - Support links
 * - Suggestions
 */
final class PackageDetailsScreen implements ScreenInterface
{
    private const TAB_INFO = 0;
    private const TAB_DEPENDENCIES = 1;
    private const TAB_REVERSE_DEPS = 2;
    private const TAB_AUTOLOAD = 3;

    private StackLayout $rootLayout;
    private Panel $mainPanel;
    private StatusBar $statusBar;

    // Tab content
    private TextDisplay $infoDisplay;
    private TableComponent $depsTable;
    private TableComponent $reverseDepsTable;
    private TextDisplay $autoloadDisplay;

    private int $currentTab = self::TAB_INFO;
    private ?PackageInfo $package = null;

    public function __construct(
        private readonly ComposerService $composerService,
        private readonly string $packageName,
    ) {
        $this->initializeComponents();
        $this->loadData();
    }

    private function initializeComponents(): void
    {
        // Create tab content
        $this->infoDisplay = new TextDisplay();
        $this->depsTable = $this->createDependenciesTable();
        $this->reverseDepsTable = $this->createReverseDepsTable();
        $this->autoloadDisplay = new TextDisplay();

        // Create main panel
        $this->mainPanel = new Panel('Package Details', $this->infoDisplay);
        $this->mainPanel->setFocused(true);

        // Create status bar
        $this->statusBar = new StatusBar([]);
        $this->updateStatusBar();

        // Build root layout
        $this->rootLayout = new StackLayout(Direction::VERTICAL);
        $this->rootLayout->addChild($this->mainPanel);
        $this->rootLayout->addChild($this->statusBar, size: 1);
    }

    private function createDependenciesTable(): TableComponent
    {
        $table = new TableComponent([
            new TableColumn('package', 'Package', '50%', TableColumn::ALIGN_LEFT),
            new TableColumn('version', 'Version Constraint', '30%', TableColumn::ALIGN_LEFT),
            new TableColumn('type', 'Type', '*', TableColumn::ALIGN_CENTER,
                formatter: fn($value) => $value === 'dev' ? '[DEV]' : '[PROD]',
                colorizer: function($value, $row, $selected) {
                    if ($selected) {
                        return ColorScheme::SELECTED_TEXT;
                    }
                    return $value === 'dev' 
                        ? ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW)
                        : ColorScheme::NORMAL_TEXT;
                }
            ),
        ], showHeader: true);

        $table->setFocused(true);

        return $table;
    }

    private function createReverseDepsTable(): TableComponent
    {
        $table = new TableComponent([
            new TableColumn('package', 'Package', '*', TableColumn::ALIGN_LEFT,
                colorizer: function($value, $row, $selected) {
                    if ($selected) {
                        return ColorScheme::SELECTED_TEXT;
                    }
                    return $row['isDirect'] 
                        ? ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_BRIGHT_WHITE)
                        : ColorScheme::NORMAL_TEXT;
                }
            ),
        ], showHeader: true);

        $table->setFocused(true);

        return $table;
    }

    private function loadData(): void
    {
        $this->package = $this->composerService->getPackageDetails($this->packageName);

        if ($this->package === null) {
            $this->infoDisplay->setText("Package not found: {$this->packageName}");
            return;
        }

        $this->showInfo();
    }

    private function showInfo(): void
    {
        if ($this->package === null) {
            return;
        }

        $lines = [
            "╔══════════════════════════════════════════════════════════╗",
            "║  PACKAGE INFORMATION                                     ║",
            "╚══════════════════════════════════════════════════════════╝",
            "",
            "Name: {$this->package->name}",
            "Version: {$this->package->version}",
            "Type: {$this->package->type}",
            "License: {$this->package->getLicenseString()}",
        ];

        if ($this->package->abandoned) {
            $lines[] = "";
            $lines[] = "⚠️  WARNING: This package is ABANDONED!";
            $lines[] = "   Consider migrating to an alternative.";
        }

        $lines[] = "";
        $lines[] = "Status: " . ($this->package->isDirect ? "Direct dependency (in composer.json)" : "Transitive dependency");

        $lines[] = "";
        $lines[] = "─────────────────────────────────────────────────────────";
        $lines[] = "Description:";
        $lines[] = "";
        $lines[] = $this->wordWrap($this->package->description, 58);

        // Authors
        if (!empty($this->package->authors)) {
            $lines[] = "";
            $lines[] = "─────────────────────────────────────────────────────────";
            $lines[] = "Authors:";
            $lines[] = "";
            foreach ($this->package->authors as $author) {
                $authorLine = "  • " . ($author['name'] ?? 'Unknown');
                if ($author['email'] ?? null) {
                    $authorLine .= " <{$author['email']}>";
                }
                if ($author['role'] ?? null) {
                    $authorLine .= " ({$author['role']})";
                }
                $lines[] = $authorLine;
            }
        }

        // Links
        $lines[] = "";
        $lines[] = "─────────────────────────────────────────────────────────";
        $lines[] = "Links:";
        $lines[] = "";

        if ($this->package->source) {
            $lines[] = "  📦 Source: {$this->package->source}";
        }

        if ($this->package->homepage) {
            $lines[] = "  🌐 Homepage: {$this->package->homepage}";
        }

        if (!empty($this->package->support)) {
            foreach ($this->package->support as $type => $url) {
                $icon = match($type) {
                    'issues' => '🐛',
                    'docs' => '📚',
                    'forum' => '💬',
                    'source' => '📦',
                    default => '🔗',
                };
                $lines[] = "  {$icon} " . ucfirst($type) . ": {$url}";
            }
        }

        // Keywords
        if (!empty($this->package->keywords)) {
            $lines[] = "";
            $lines[] = "─────────────────────────────────────────────────────────";
            $lines[] = "Keywords: " . implode(', ', $this->package->keywords);
        }

        // Suggestions
        if ($this->package->hasSuggestions()) {
            $lines[] = "";
            $lines[] = "─────────────────────────────────────────────────────────";
            $lines[] = "Suggestions:";
            $lines[] = "";
            foreach ($this->package->suggests as $pkg => $reason) {
                $lines[] = "  • {$pkg}";
                if ($reason) {
                    $lines[] = "    " . $this->wordWrap($reason, 54);
                }
            }
        }

        // Binaries
        if ($this->package->hasBinaries()) {
            $lines[] = "";
            $lines[] = "─────────────────────────────────────────────────────────";
            $lines[] = "Binaries:";
            $lines[] = "";
            foreach ($this->package->binaries as $binary) {
                $lines[] = "  • {$binary}";
            }
        }

        // Stats
        $lines[] = "";
        $lines[] = "─────────────────────────────────────────────────────────";
        $lines[] = "Statistics:";
        $lines[] = "";
        $lines[] = "  Dependencies: {$this->package->getTotalDependencies()} total";
        $lines[] = "    ├─ Production: " . count($this->package->requires);
        $lines[] = "    └─ Development: " . count($this->package->devRequires);

        $reverseDeps = $this->composerService->getReverseDependencies($this->packageName);
        $lines[] = "  Reverse Dependencies: " . count($reverseDeps) . " packages depend on this";

        if ($this->package->hasAutoload()) {
            $namespaces = $this->package->getNamespaces();
            $lines[] = "  Namespaces: " . count($namespaces);
        }

        $this->infoDisplay->setText(implode("\n", $lines));
        $this->mainPanel->setTitle("Package: {$this->package->name}");
    }

    private function showDependencies(): void
    {
        if ($this->package === null) {
            return;
        }

        $rows = [];

        // Production dependencies
        foreach ($this->package->requires as $pkg => $version) {
            $rows[] = [
                'package' => $pkg,
                'version' => $version,
                'type' => 'prod',
            ];
        }

        // Dev dependencies
        foreach ($this->package->devRequires as $pkg => $version) {
            $rows[] = [
                'package' => $pkg,
                'version' => $version,
                'type' => 'dev',
            ];
        }

        $this->depsTable->setRows($rows);
        $count = count($rows);
        $this->mainPanel->setTitle("Dependencies ({$count})");
    }

    private function showReverseDependencies(): void
    {
        $reverseDeps = $this->composerService->getReverseDependencies($this->packageName);

        $rows = array_map(fn($pkg) => [
            'package' => $pkg,
            'isDirect' => false, // TODO: determine if direct
        ], $reverseDeps);

        $this->reverseDepsTable->setRows($rows);
        $count = count($rows);
        $this->mainPanel->setTitle("Reverse Dependencies ({$count} packages depend on this)");
    }

    private function showAutoload(): void
    {
        if ($this->package === null) {
            return;
        }

        $lines = [
            "╔══════════════════════════════════════════════════════════╗",
            "║  AUTOLOAD CONFIGURATION                                  ║",
            "╚══════════════════════════════════════════════════════════╝",
            "",
        ];

        $hasAny = false;

        // PSR-4
        if (!empty($this->package->autoload['psr4'])) {
            $hasAny = true;
            $lines[] = "PSR-4 Namespaces:";
            $lines[] = "";
            foreach ($this->package->autoload['psr4'] as $namespace => $paths) {
                $pathsList = is_array($paths) ? implode(', ', $paths) : $paths;
                $lines[] = "  {$namespace}";
                $lines[] = "    → {$pathsList}";
                $lines[] = "";
            }
        }

        // PSR-0
        if (!empty($this->package->autoload['psr0'])) {
            $hasAny = true;
            if (!empty($this->package->autoload['psr4'])) {
                $lines[] = "─────────────────────────────────────────────────────────";
                $lines[] = "";
            }
            $lines[] = "PSR-0 Namespaces:";
            $lines[] = "";
            foreach ($this->package->autoload['psr0'] as $namespace => $paths) {
                $pathsList = is_array($paths) ? implode(', ', $paths) : $paths;
                $lines[] = "  {$namespace}";
                $lines[] = "    → {$pathsList}";
                $lines[] = "";
            }
        }

        // Classmap
        if (!empty($this->package->autoload['classmap'])) {
            $hasAny = true;
            if (!empty($this->package->autoload['psr4']) || !empty($this->package->autoload['psr0'])) {
                $lines[] = "─────────────────────────────────────────────────────────";
                $lines[] = "";
            }
            $lines[] = "Classmap Directories:";
            $lines[] = "";
            foreach ($this->package->autoload['classmap'] as $path) {
                $lines[] = "  • {$path}";
            }
            $lines[] = "";
        }

        // Files
        if (!empty($this->package->autoload['files'])) {
            $hasAny = true;
            if (!empty($this->package->autoload['psr4']) || !empty($this->package->autoload['psr0']) || !empty($this->package->autoload['classmap'])) {
                $lines[] = "─────────────────────────────────────────────────────────";
                $lines[] = "";
            }
            $lines[] = "Files (always loaded):";
            $lines[] = "";
            foreach ($this->package->autoload['files'] as $file) {
                $lines[] = "  • {$file}";
            }
            $lines[] = "";
        }

        if (!$hasAny) {
            $lines[] = "No autoload configuration defined.";
        }

        $this->autoloadDisplay->setText(implode("\n", $lines));
        $this->mainPanel->setTitle("Autoload: {$this->package->name}");
    }

    private function switchTab(int $tab): void
    {
        if ($tab === $this->currentTab) {
            return;
        }

        $this->currentTab = $tab;

        match ($tab) {
            self::TAB_INFO => $this->switchToInfoTab(),
            self::TAB_DEPENDENCIES => $this->switchToDependenciesTab(),
            self::TAB_REVERSE_DEPS => $this->switchToReverseDepsTab(),
            self::TAB_AUTOLOAD => $this->switchToAutoloadTab(),
        };

        $this->updateStatusBar();
    }

    private function switchToInfoTab(): void
    {
        $paddedInfo = Padding::symmetric($this->infoDisplay, horizontal: 2, vertical: 1);
        $this->mainPanel->setContent($paddedInfo);
        $this->showInfo();
    }

    private function switchToDependenciesTab(): void
    {
        $this->mainPanel->setContent($this->depsTable);
        $this->depsTable->setFocused(true);
        $this->showDependencies();
    }

    private function switchToReverseDepsTab(): void
    {
        $this->mainPanel->setContent($this->reverseDepsTable);
        $this->reverseDepsTable->setFocused(true);
        $this->showReverseDependencies();
    }

    private function switchToAutoloadTab(): void
    {
        $paddedAutoload = Padding::symmetric($this->autoloadDisplay, horizontal: 2, vertical: 1);
        $this->mainPanel->setContent($paddedAutoload);
        $this->showAutoload();
    }

    private function updateStatusBar(): void
    {
        $this->statusBar = new StatusBar([
            'F1' => 'Info',
            'F2' => 'Deps',
            'F3' => 'Rev Deps',
            'F4' => 'Autoload',
            'ESC' => 'Back',
        ]);
    }

    private function wordWrap(string $text, int $width): string
    {
        $lines = explode("\n", wordwrap($text, $width, "\n", false));
        return implode("\n", array_map(fn($line) => "  " . $line, $lines));
    }

    // ScreenInterface implementation

    public function render(Renderer $renderer): void
    {
        $size = $renderer->getSize();
        $this->rootLayout->render($renderer, 0, 1, $size['width'], $size['height'] - 1);
    }

    public function handleInput(string $key): bool
    {
        // Tab shortcuts
        if ($key === 'F1') {
            $this->switchTab(self::TAB_INFO);
            return true;
        }

        if ($key === 'F2') {
            $this->switchTab(self::TAB_DEPENDENCIES);
            return true;
        }

        if ($key === 'F3') {
            $this->switchTab(self::TAB_REVERSE_DEPS);
            return true;
        }

        if ($key === 'F4') {
            $this->switchTab(self::TAB_AUTOLOAD);
            return true;
        }

        // Delegate to active content
        return $this->mainPanel->handleInput($key);
    }

    public function onActivate(): void
    {
        // Nothing to do
    }

    public function onDeactivate(): void
    {
        // Nothing to do
    }

    public function update(): void
    {
        // Nothing to do
    }

    public function getTitle(): string
    {
        return "Package Details: {$this->packageName}";
    }
}
