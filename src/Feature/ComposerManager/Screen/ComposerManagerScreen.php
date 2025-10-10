<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Screen;

use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;
use Butschster\Commander\Feature\ComposerManager\Service\OutdatedPackageInfo;
use Butschster\Commander\Feature\ComposerManager\Service\PackageInfo;
use Butschster\Commander\Feature\ComposerManager\Service\SecurityAdvisory;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Container\Direction;
use Butschster\Commander\UI\Component\Container\GridLayout;
use Butschster\Commander\UI\Component\Container\StackLayout;
use Butschster\Commander\UI\Component\Decorator\Padding;
use Butschster\Commander\UI\Component\Display\TableColumn;
use Butschster\Commander\UI\Component\Display\TableComponent;
use Butschster\Commander\UI\Component\Display\TextDisplay;
use Butschster\Commander\UI\Component\Layout\Panel;
use Butschster\Commander\UI\Component\Layout\StatusBar;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\UI\Screen\ScreenManager;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Composer Manager Screen
 *
 * Features:
 * - View installed packages (composer show)
 * - Check for outdated packages (composer outdated)
 * - Security audit (composer audit)
 * - Update packages
 * - Add/Remove packages
 *
 * Layout:
 * ┌────────────────────────────────────────────┐
 * │ Tabs: [Installed] [Outdated] [Security]   │
 * │ ┌────────────┬─────────────────────────┐  │
 * │ │ Package    │ Details/Actions         │  │
 * │ │ List       │                         │  │
 * │ │ (Table)    │                         │  │
 * │ └────────────┴─────────────────────────┘  │
 * │ Status Bar                                 │
 * └────────────────────────────────────────────┘
 */
final class ComposerManagerScreen implements ScreenInterface
{
    // Tabs
    private const int TAB_INSTALLED = 0;
    private const int TAB_OUTDATED = 1;
    private const int TAB_SECURITY = 2;

    // Layout
    private StackLayout $rootLayout;
    private GridLayout $mainArea;
    private Panel $leftPanel;
    private Panel $rightPanel;
    private StatusBar $statusBar;

    // Tab content
    private TableComponent $installedTable;
    private TableComponent $outdatedTable;
    private TableComponent $securityTable;
    private TextDisplay $detailsDisplay;

    // State
    private int $currentTab = self::TAB_INSTALLED;
    private int $focusedPanelIndex = 0;
    private bool $isLoading = false;
    private ?string $selectedPackageName = null;
    private ?ScreenManager $screenManager = null;

    // Data
    private array $installedPackages = [];
    private array $outdatedPackages = [];
    private array $securityAdvisories = [];
    private array $auditSummary = [];

    public function __construct(
        private readonly ComposerService $composerService,
    ) {
        $this->initializeComponents();
        $this->loadData();
    }

    public function setScreenManager(ScreenManager $screenManager): void
    {
        $this->screenManager = $screenManager;
    }

    // ScreenInterface implementation

    public function render(Renderer $renderer): void
    {
        $size = $renderer->getSize();

        // Render main layout
        $this->rootLayout->render($renderer, 0, 1, $size['width'], $size['height'] - 1);
    }

    public function handleInput(string $key): bool
    {
        if ($key === 'F5') {
            $this->switchTab(self::TAB_INSTALLED);
            return true;
        }

        if ($key === 'CTRL_O') {
            $this->switchTab(self::TAB_OUTDATED);
            return true;
        }

        if ($key === 'CTRL_U') {
            $this->switchTab(self::TAB_SECURITY);
            return true;
        }

        if ($key === 'CTRL_R') {
            $this->composerService->clearCache();
            match ($this->currentTab) {
                self::TAB_INSTALLED => $this->loadData(),
                self::TAB_OUTDATED => $this->loadOutdatedPackages(),
                self::TAB_SECURITY => $this->loadSecurityAudit(),
            };
            return true;
        }

        // Switch panel focus
        if ($key === 'TAB') {
            $this->focusedPanelIndex = ($this->focusedPanelIndex + 1) % 2;
            $this->updateFocus();
            return true;
        }

        // Delegate to focused panel
        if ($this->focusedPanelIndex === 0) {
            return $this->leftPanel->handleInput($key);
        }

        return $this->rightPanel->handleInput($key);
    }

    public function onActivate(): void
    {
        $this->updateFocus();
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
        return 'Composer Manager';
    }

    private function initializeComponents(): void
    {
        // Create tables
        $this->installedTable = $this->createInstalledTable();
        $this->outdatedTable = $this->createOutdatedTable();
        $this->securityTable = $this->createSecurityTable();
        $this->detailsDisplay = new TextDisplay();

        // Create panels
        $this->leftPanel = new Panel('Packages', $this->installedTable);
        $this->leftPanel->setFocused(true);

        $paddedDetails = Padding::symmetric($this->detailsDisplay, horizontal: 2, vertical: 1);
        $this->rightPanel = new Panel('Details', $paddedDetails);

        // Create grid layout
        $this->mainArea = new GridLayout(columns: ['55%', '45%']);
        $this->mainArea->setColumn(0, $this->leftPanel);
        $this->mainArea->setColumn(1, $this->rightPanel);

        // Create status bar
        $this->statusBar = new StatusBar([]);
        $this->updateStatusBar();

        // Build root layout
        $this->rootLayout = new StackLayout(Direction::VERTICAL);
        $this->rootLayout->addChild($this->mainArea);
        $this->rootLayout->addChild($this->statusBar, size: 1);
    }

    private function createInstalledTable(): TableComponent
    {
        $table = new TableComponent([
            new TableColumn(
                'name',
                'Package',
                '50%',
                TableColumn::ALIGN_LEFT,
                formatter: static function ($value, $row) {
                    $prefix = $row['isDirect'] ? '* ' : '  ';
                    return $prefix . $value;
                },
                colorizer: function ($value, $row, $selected) {
                    if ($selected && $this->leftPanel->isFocused()) {
                        return ColorScheme::SELECTED_TEXT;
                    }
                    if ($row['abandoned']) {
                        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_RED);
                    }
                    if ($row['isDirect']) {
                        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_BRIGHT_WHITE);
                    }
                    return ColorScheme::NORMAL_TEXT;
                },
            ),
            new TableColumn('version', 'Version', '15%', TableColumn::ALIGN_LEFT),
            new TableColumn(
                'outdated',
                'Outdated',
                '15%',
                TableColumn::ALIGN_CENTER,
                formatter: static function ($value, $row) {
                    if (!$value) {
                        return '';
                    }
                    // Use different symbols to indicate severity visually
                    return match ($row['updateType'] ?? null) {
                        'major' => '→ ' . $value . ' [!!]',  // Double exclamation for major
                        'minor' => '→ ' . $value . ' [>]',   // Arrow for minor
                        'patch' => '→ ' . $value . ' [+]',   // Plus for patch
                        default => '→ ' . $value,
                    };
                },
                colorizer: function ($value, $row, $selected) {
                    if (!$value) {
                        // Empty cell - use default color (selection or normal)
                        return $selected && $this->leftPanel->isFocused()
                            ? ColorScheme::SELECTED_TEXT
                            : ColorScheme::NORMAL_TEXT;
                    }

                    // Apply severity-based color with strong backgrounds, even for selected rows
                    return match ($row['updateType'] ?? null) {
                        'major' => ColorScheme::combine(ColorScheme::BG_RED, ColorScheme::FG_WHITE, ColorScheme::BOLD),
                        'minor' => ColorScheme::combine(
                            ColorScheme::BG_YELLOW,
                            ColorScheme::FG_BLACK,
                            ColorScheme::BOLD,
                        ),
                        'patch' => ColorScheme::combine(
                            ColorScheme::BG_GREEN,
                            ColorScheme::FG_BLACK,
                            ColorScheme::BOLD,
                        ),
                        default => $selected && $this->leftPanel->isFocused()
                            ? ColorScheme::SELECTED_TEXT
                            : ColorScheme::NORMAL_TEXT,
                    };
                },
            ),
            new TableColumn(
                'abandoned',
                'Abandoned',
                '*',
                TableColumn::ALIGN_CENTER,
                formatter: static fn($value) => $value ? '[ABANDONED]' : '',
                colorizer: function ($value, $row, $selected) {
                    if ($selected && $this->leftPanel->isFocused()) {
                        return ColorScheme::SELECTED_TEXT;
                    }
                    if ($value) {
                        return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_RED, ColorScheme::BOLD);
                    }
                    return ColorScheme::NORMAL_TEXT;
                },
            ),
        ], showHeader: true);

        $table->setFocused(true);

        $table->onChange(function (array $row, int $index): void {
            $this->selectedPackageName = $row['name'];
            $this->showPackageDetails($row['name']);
        });

        $table->onSelect(function (array $row, int $index): void {
            // Open detailed package screen on Enter
            $this->openPackageDetailsScreen($row['name']);
        });

        return $table;
    }

    private function createOutdatedTable(): TableComponent
    {
        $table = new TableComponent([
            new TableColumn('name', 'Package', '35%', TableColumn::ALIGN_LEFT),
            new TableColumn('current', 'Current', '15%', TableColumn::ALIGN_CENTER),
            new TableColumn('latest', 'Latest', '15%', TableColumn::ALIGN_CENTER),
            new TableColumn(
                'type',
                'Update',
                '10%',
                TableColumn::ALIGN_CENTER,
                formatter: static fn($value) => match ($value) {
                    'major' => '[!!] Major',
                    'minor' => '[>] Minor',
                    'patch' => '[+] Patch',
                    default => '?',
                },
                colorizer: function ($value, $row, $selected) {
                    if ($selected && $this->leftPanel->isFocused()) {
                        return ColorScheme::SELECTED_TEXT;
                    }
                    return match ($value) {
                        'major' => ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_RED),
                        'minor' => ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW),
                        'patch' => ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_GREEN),
                        default => ColorScheme::NORMAL_TEXT,
                    };
                },
            ),
            new TableColumn(
                'description',
                'Description',
                '*',
                TableColumn::ALIGN_LEFT,
                formatter: static fn($value) => \mb_substr((string) $value, 0, 50) . (\mb_strlen(
                        (string) $value,
                    ) > 50 ? '...' : ''),
            ),
        ], showHeader: true);

        $table->onChange(function (array $row, int $index): void {
            $this->selectedPackageName = $row['name'];
            $this->showOutdatedPackageDetails($row);
        });

        return $table;
    }

    private function createSecurityTable(): TableComponent
    {
        $table = new TableComponent([
            new TableColumn(
                'severity',
                'Sev',
                '8%',
                TableColumn::ALIGN_CENTER,
                formatter: static fn($value) => match ($value) {
                    'critical', 'high' => '[!!]',
                    'medium' => '[!]',
                    default => '[ ]',
                },
                colorizer: function ($value, $row, $selected) {
                    if ($selected && $this->leftPanel->isFocused()) {
                        return ColorScheme::SELECTED_TEXT;
                    }
                    return match ($value) {
                        'critical', 'high' => ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_RED),
                        'medium' => ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW),
                        default => ColorScheme::NORMAL_TEXT,
                    };
                },
            ),
            new TableColumn('package', 'Package', '30%', TableColumn::ALIGN_LEFT),
            new TableColumn(
                'title',
                'Vulnerability',
                '*',
                TableColumn::ALIGN_LEFT,
                formatter: static fn($value) => \mb_substr((string) $value, 0, 60) . (\mb_strlen(
                        (string) $value,
                    ) > 60 ? '...' : ''),
            ),
            new TableColumn('cve', 'CVE', '15%', TableColumn::ALIGN_CENTER),
        ], showHeader: true);

        $table->onChange(function (array $row, int $index): void {
            $this->showSecurityAdvisoryDetails($row);
        });

        $table->onSelect(function (array $row, int $index): void {
            $this->showSecurityAdvisoryDetails($row);
        });

        return $table;
    }

    private function loadData(): void
    {
        // Load installed packages
        $this->installedPackages = \array_map(static fn(PackageInfo $pkg)
            => [
            'name' => $pkg->name,
            'version' => $pkg->version,
            'source' => $pkg->source,
            'description' => $pkg->description,
            'homepage' => $pkg->homepage,
            'keywords' => $pkg->keywords,
            'isDirect' => $pkg->isDirect,
            'abandoned' => $pkg->abandoned,
            'authors' => $pkg->authors,
            'license' => $pkg->license,
            'requires' => $pkg->requires,
            'devRequires' => $pkg->devRequires,
            'autoload' => $pkg->autoload,
            'outdated' => null,
            'updateType' => null,
        ], $this->composerService->getInstalledPackages());

        // Load outdated information and merge with installed packages
        $outdatedPackages = $this->composerService->getOutdatedPackages();
        $outdatedMap = [];
        foreach ($outdatedPackages as $outdated) {
            $outdatedMap[$outdated->name] = [
                'latestVersion' => $outdated->latestVersion,
                'updateType' => $outdated->isMajorUpdate() ? 'major' : ($outdated->isMinorUpdate() ? 'minor' : 'patch'),
            ];
        }

        // Merge outdated info into installed packages
        foreach ($this->installedPackages as &$package) {
            if (isset($outdatedMap[$package['name']])) {
                $package['outdated'] = $outdatedMap[$package['name']]['latestVersion'];
                $package['updateType'] = $outdatedMap[$package['name']]['updateType'];
            }
        }
        unset($package);

        $this->installedTable->setRows($this->installedPackages);

        // Update panel title
        $count = \count($this->installedPackages);
        $outdatedCount = \count($outdatedMap);
        $title = "Installed Packages ($count)";
        if ($outdatedCount > 0) {
            $title .= " - $outdatedCount outdated";
        }
        $this->leftPanel->setTitle($title);

        // Show first package details
        if (!empty($this->installedPackages)) {
            $this->selectedPackageName = $this->installedPackages[0]['name'];
            $this->showPackageDetails($this->installedPackages[0]['name']);
        }
    }

    private function loadOutdatedPackages(): void
    {
        $this->isLoading = true;
        $this->updateStatusBar();

        $this->outdatedPackages = \array_map(static fn(OutdatedPackageInfo $pkg)
            => [
            'name' => $pkg->name,
            'current' => $pkg->currentVersion,
            'latest' => $pkg->latestVersion,
            'type' => $pkg->isMajorUpdate() ? 'major' : ($pkg->isMinorUpdate() ? 'minor' : 'patch'),
            'description' => $pkg->description,
            'warning' => $pkg->warning,
        ], $this->composerService->getOutdatedPackages());

        $this->outdatedTable->setRows($this->outdatedPackages);

        $this->isLoading = false;
        $this->updateStatusBar();

        // Update panel title
        $count = \count($this->outdatedPackages);
        $this->leftPanel->setTitle("Outdated Packages ($count)");

        // Show first package if available
        if (!empty($this->outdatedPackages)) {
            $this->selectedPackageName = $this->outdatedPackages[0]['name'];
            $this->showOutdatedPackageDetails($this->outdatedPackages[0]);
        } else {
            $this->detailsDisplay->setText("[OK] All packages are up to date!");
        }
    }

    private function loadSecurityAudit(): void
    {
        $this->isLoading = true;
        $this->updateStatusBar();

        $auditResult = $this->composerService->runAudit();
        $this->auditSummary = $auditResult['summary'];

        $this->securityAdvisories = \array_map(static fn(SecurityAdvisory $advisory)
            => [
            'severity' => $advisory->severity,
            'package' => $advisory->packageName,
            'title' => $advisory->title,
            'cve' => $advisory->cve ?: 'N/A',
            'affectedVersions' => $advisory->affectedVersions,
            'link' => $advisory->link,
        ], $auditResult['advisories']);

        $this->securityTable->setRows($this->securityAdvisories);

        $this->isLoading = false;
        $this->updateStatusBar();

        // Update panel title
        $count = \count($this->securityAdvisories);
        $critical = $this->auditSummary['high'] ?? 0;
        $title = "Security Audit ($count)";
        if ($critical > 0) {
            $title .= " - [!!] $critical Critical/High";
        }
        $this->leftPanel->setTitle($title);

        // Show first advisory if available
        if (!empty($this->securityAdvisories)) {
            $this->showSecurityAdvisoryDetails($this->securityAdvisories[0]);
        } else {
            $this->detailsDisplay->setText("[OK] No security vulnerabilities found!");
        }
    }

    private function showPackageDetails(string $packageName): void
    {
        $package = null;
        foreach ($this->installedPackages as $p) {
            if ($p['name'] === $packageName) {
                $package = $p;
                break;
            }
        }

        if (!$package) {
            $this->detailsDisplay->setText("Package not found");
            return;
        }

        $lines = [
            "Package: {$package['name']}",
            "Version: {$package['version']}",
        ];

        if ($package['abandoned']) {
            $lines[] = "";
            $lines[] = "[!] WARNING: This package is ABANDONED!";
            $lines[] = "    Consider migrating to an alternative package.";
        }

        $lines[] = "";
        if ($package['isDirect']) {
            $lines[] = "* Direct dependency (required in composer.json)";
        } else {
            $lines[] = "  Transitive dependency (required by another package)";
        }

        $lines[] = "";
        $lines[] = "Description:";
        $lines[] = "  " . ($package['description'] ?: 'N/A');

        if ($package['source']) {
            $lines[] = "";
            $lines[] = "Source:";
            $lines[] = "  {$package['source']}";
        }

        if ($package['homepage']) {
            $lines[] = "";
            $lines[] = "Homepage:";
            $lines[] = "  {$package['homepage']}";
        }

        if (!empty($package['keywords'])) {
            $lines[] = "";
            $lines[] = "Keywords: " . \implode(', ', $package['keywords']);
        }

        // Show additional rich info
        if (!empty($package['authors'])) {
            $lines[] = "";
            $lines[] = "Authors: ";
            foreach (\array_slice($package['authors'], 0, 3) as $author) {
                $lines[] = "  • " . ($author['name'] ?? 'Unknown');
            }
            if (\count($package['authors']) > 3) {
                $lines[] = "  ... and " . (\count($package['authors']) - 3) . " more";
            }
        }

        if (!empty($package['license'])) {
            $lines[] = "";
            $lines[] = "License: " . \implode(', ', $package['license']);
        }

        // Show dependencies count
        $totalDeps = \count($package['requires'] ?? []) + \count($package['devRequires'] ?? []);
        if ($totalDeps > 0) {
            $lines[] = "";
            $lines[] = "Dependencies: {$totalDeps} total";
            if (!empty($package['requires'])) {
                $lines[] = "  Production: " . \count($package['requires']);
            }
            if (!empty($package['devRequires'])) {
                $lines[] = "  Development: " . \count($package['devRequires']);
            }
        }

        // Show autoload info
        if (!empty($package['autoload'])) {
            $namespaces = \array_merge(
                \array_keys($package['autoload']['psr4'] ?? []),
                \array_keys($package['autoload']['psr0'] ?? []),
            );
            if (!empty($namespaces)) {
                $lines[] = "";
                $lines[] = "Namespaces: " . \count($namespaces);
                foreach (\array_slice($namespaces, 0, 3) as $ns) {
                    $lines[] = "  • " . \rtrim((string) $ns, '\\');
                }
                if (\count($namespaces) > 3) {
                    $lines[] = "  ... and " . (\count($namespaces) - 3) . " more";
                }
            }
        }

        $lines[] = "";
        $lines[] = "Press Enter to view full details";

        $this->detailsDisplay->setText(\implode("\n", $lines));
        $this->rightPanel->setTitle("Details: {$package['name']}");
    }

    /**
     * Open detailed package screen
     */
    private function openPackageDetailsScreen(string $packageName): void
    {
        if ($this->screenManager === null) {
            // Fallback: if screen manager not set, show in details panel
            $this->detailsDisplay->setText("ERROR: Screen manager not available!\nPackage: $packageName");
            return;
        }

        try {
            $detailsScreen = new PackageDetailsScreen($this->composerService, $packageName);
            $this->screenManager->pushScreen($detailsScreen);
        } catch (\Throwable $e) {
            // Show error in details panel
            $this->detailsDisplay->setText(
                "ERROR: Failed to open package details!\n\n" .
                "Package: $packageName\n\n" .
                "Error: {$e->getMessage()}\n\n" .
                $e->getTraceAsString(),
            );
        }
    }

    private function showOutdatedPackageDetails(array $package): void
    {
        $lines = [
            "Package: {$package['name']}",
            "Current Version: {$package['current']}",
            "Latest Version: {$package['latest']}",
            "Update Type: " . match ($package['type']) {
                'major' => '[!!] Major Update (breaking changes possible)',
                'minor' => '[>] Minor Update (new features)',
                'patch' => '[+] Patch Update (bug fixes)',
                default => 'Unknown',
            },
            "",
            "Description:",
            "  " . ($package['description'] ?: 'N/A'),
        ];

        if ($package['warning']) {
            $lines[] = "";
            $lines[] = "[!] Warning:";
            $lines[] = "  {$package['warning']}";
        }

        $lines[] = "";
        $lines[] = "Press Enter to update this package";

        $this->detailsDisplay->setText(\implode("\n", $lines));
        $this->rightPanel->setTitle("Outdated: {$package['name']}");
    }

    private function showSecurityAdvisoryDetails(array $advisory): void
    {
        $severityIcon = match ($advisory['severity']) {
            'critical', 'high' => '[!!]',
            'medium' => '[!]',
            default => '[ ]',
        };

        $lines = [
            "$severityIcon " . \strtoupper((string) $advisory['severity']) . " Severity",
            "",
            "Package: {$advisory['package']}",
            "CVE: {$advisory['cve']}",
            "",
            "Title:",
            "  {$advisory['title']}",
            "",
            "Affected Versions:",
            "  {$advisory['affectedVersions']}",
        ];

        if ($advisory['link']) {
            $lines[] = "";
            $lines[] = "More Info: {$advisory['link']}";
        }

        $this->detailsDisplay->setText(\implode("\n", $lines));
        $this->rightPanel->setTitle("Security Advisory");
    }

    private function switchTab(int $tab): void
    {
        if ($tab === $this->currentTab) {
            return;
        }

        $this->currentTab = $tab;

        // Update left panel content
        match ($tab) {
            self::TAB_INSTALLED => $this->switchToInstalledTab(),
            self::TAB_OUTDATED => $this->switchToOutdatedTab(),
            self::TAB_SECURITY => $this->switchToSecurityTab(),
        };

        $this->updateStatusBar();
    }

    private function switchToInstalledTab(): void
    {
        $this->leftPanel->setContent($this->installedTable);
        $this->installedTable->setFocused(true);

        // Update title with current state
        $count = \count($this->installedPackages);
        $outdatedCount = 0;
        foreach ($this->installedPackages as $package) {
            if ($package['outdated'] !== null) {
                $outdatedCount++;
            }
        }

        $title = "Installed Packages ($count)";
        if ($outdatedCount > 0) {
            $title .= " - $outdatedCount outdated";
        }
        $this->leftPanel->setTitle($title);

        if (!empty($this->installedPackages)) {
            $this->showPackageDetails($this->installedPackages[0]['name']);
        }
    }

    private function switchToOutdatedTab(): void
    {
        if (empty($this->outdatedPackages)) {
            $this->loadOutdatedPackages();
        } else {
            // Update title even if data already loaded
            $count = \count($this->outdatedPackages);
            $this->leftPanel->setTitle("Outdated Packages ($count)");
        }

        $this->leftPanel->setContent($this->outdatedTable);
        $this->outdatedTable->setFocused(true);

        // Show first package or empty state
        if (!empty($this->outdatedPackages)) {
            $this->selectedPackageName = $this->outdatedPackages[0]['name'];
            $this->showOutdatedPackageDetails($this->outdatedPackages[0]);
        } else {
            $this->detailsDisplay->setText("[OK] All packages are up to date!");
        }
    }

    private function switchToSecurityTab(): void
    {
        if (empty($this->securityAdvisories) && empty($this->auditSummary)) {
            $this->loadSecurityAudit();
        } else {
            // Update title even if data already loaded
            $count = \count($this->securityAdvisories);
            $critical = $this->auditSummary['high'] ?? 0;
            $title = "Security Audit ($count)";
            if ($critical > 0) {
                $title .= " - [!!] $critical Critical/High";
            }
            $this->leftPanel->setTitle($title);
        }

        $this->leftPanel->setContent($this->securityTable);
        $this->securityTable->setFocused(true);

        // Show first advisory or empty state
        if (!empty($this->securityAdvisories)) {
            $this->showSecurityAdvisoryDetails($this->securityAdvisories[0]);
        } else {
            $this->detailsDisplay->setText("[OK] No security vulnerabilities found!");
        }
    }

    private function updateFocus(): void
    {
        $leftFocused = $this->focusedPanelIndex === 0;
        $rightFocused = $this->focusedPanelIndex === 1;

        $this->leftPanel->setFocused($leftFocused);
        $this->rightPanel->setFocused($rightFocused);

        // Update table focus
        match ($this->currentTab) {
            self::TAB_INSTALLED => $this->installedTable->setFocused($leftFocused),
            self::TAB_OUTDATED => $this->outdatedTable->setFocused($leftFocused),
            self::TAB_SECURITY => $this->securityTable->setFocused($leftFocused),
        };
    }

    private function updateStatusBar(): void
    {
        // F1-F12 are reserved for global screen switching
        // Use Ctrl combinations for screen-specific operations
        $keys = [
            'F5' => 'Installed',
            'Ctrl+O' => 'Outdated',
            'Ctrl+U' => 'Security',
            'Ctrl+R' => 'Refresh',
            'Tab' => 'Switch',
            'Enter' => 'Details',
        ];

        if ($this->isLoading) {
            $keys[''] = 'Loading...';
        }

        $this->statusBar = new StatusBar($keys);
    }
}
