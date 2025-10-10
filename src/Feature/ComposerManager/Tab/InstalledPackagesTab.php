<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Tab;

use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;
use Butschster\Commander\Feature\ComposerManager\Service\PackageInfo;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Container\AbstractTab;
use Butschster\Commander\UI\Component\Container\GridLayout;
use Butschster\Commander\UI\Component\Decorator\Padding;
use Butschster\Commander\UI\Component\Display\TableColumn;
use Butschster\Commander\UI\Component\Display\TableComponent;
use Butschster\Commander\UI\Component\Display\TextDisplay;
use Butschster\Commander\UI\Component\Layout\Panel;
use Butschster\Commander\UI\Screen\ScreenManager;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Installed Packages Tab
 *
 * Shows all installed packages with:
 * - Direct vs transitive dependencies
 * - Outdated status with severity
 * - Abandoned packages
 * - Package details on selection
 */
final class InstalledPackagesTab extends AbstractTab
{
    private GridLayout $layout;
    private Panel $leftPanel;
    private Panel $rightPanel;
    private TableComponent $table;
    private TextDisplay $detailsDisplay;
    private array $packages = [];
    private ?string $selectedPackageName = null;
    private int $focusedPanelIndex = 0;

    public function __construct(
        private readonly ComposerService $composerService,
        private readonly ?ScreenManager $screenManager = null,
    ) {
        $this->initializeComponents();
    }

    public function getTitle(): string
    {
        return 'Installed';
    }

    #[\Override]
    public function getShortcuts(): array
    {
        return [
            'Tab' => 'Switch Panel',
            'Enter' => 'Details',
            'Ctrl+R' => 'Refresh',
        ];
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);
        $this->layout->render($renderer, $x, $y, $width, $height);
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        // Refresh data
        if ($key === 'CTRL_R') {
            $this->composerService->clearCache();
            $this->loadData();
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

    #[\Override]
    protected function onTabActivated(): void
    {
        $this->loadData();
        $this->updateFocus();
    }

    private function initializeComponents(): void
    {
        // Create table
        $this->table = $this->createTable();
        $this->detailsDisplay = new TextDisplay();

        // Create panels
        $this->leftPanel = new Panel('Packages', $this->table);
        $this->leftPanel->setFocused(true);

        $paddedDetails = Padding::symmetric($this->detailsDisplay, horizontal: 2, vertical: 1);
        $this->rightPanel = new Panel('Details', $paddedDetails);

        // Create grid layout
        $this->layout = new GridLayout(columns: ['55%', '45%']);
        $this->layout->setColumn(0, $this->leftPanel);
        $this->layout->setColumn(1, $this->rightPanel);
    }

    private function createTable(): TableComponent
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
                    return match ($row['updateType'] ?? null) {
                        'major' => '↑ ' . $value . ' [!!]',
                        'minor' => '↑ ' . $value . ' [>]',
                        'patch' => '↑ ' . $value . ' [+]',
                        default => '↑ ' . $value,
                    };
                },
                colorizer: function ($value, $row, $selected) {
                    if (!$value) {
                        return $selected && $this->leftPanel->isFocused()
                            ? ColorScheme::SELECTED_TEXT
                            : ColorScheme::NORMAL_TEXT;
                    }

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
            $this->openPackageDetailsScreen($row['name']);
        });

        return $table;
    }

    private function loadData(): void
    {
        // Load installed packages
        $this->packages = \array_map(static fn(PackageInfo $pkg)
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

        // Load outdated information and merge
        $outdatedPackages = $this->composerService->getOutdatedPackages();
        $outdatedMap = [];
        foreach ($outdatedPackages as $outdated) {
            $outdatedMap[$outdated->name] = [
                'latestVersion' => $outdated->latestVersion,
                'updateType' => $outdated->isMajorUpdate() ? 'major' : ($outdated->isMinorUpdate() ? 'minor' : 'patch'),
            ];
        }

        // Merge outdated info
        foreach ($this->packages as &$package) {
            if (isset($outdatedMap[$package['name']])) {
                $package['outdated'] = $outdatedMap[$package['name']]['latestVersion'];
                $package['updateType'] = $outdatedMap[$package['name']]['updateType'];
            }
        }
        unset($package);

        $this->table->setRows($this->packages);

        // Show first package details
        if (!empty($this->packages)) {
            $this->selectedPackageName = $this->packages[0]['name'];
            $this->showPackageDetails($this->packages[0]['name']);
        }
    }

    private function showPackageDetails(string $packageName): void
    {
        $package = null;
        foreach ($this->packages as $p) {
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

    private function openPackageDetailsScreen(string $packageName): void
    {
        if ($this->screenManager === null) {
            $this->detailsDisplay->setText("ERROR: Screen manager not available!\nPackage: $packageName");
            return;
        }

        try {
            $detailsScreen = new \Butschster\Commander\Feature\ComposerManager\Screen\PackageDetailsScreen(
                $this->composerService,
                $packageName,
            );
            $this->screenManager->pushScreen($detailsScreen);
        } catch (\Throwable $e) {
            $this->detailsDisplay->setText(
                "ERROR: Failed to open package details!\n\n" .
                "Package: $packageName\n\n" .
                "Error: {$e->getMessage()}\n\n" .
                $e->getTraceAsString(),
            );
        }
    }

    private function updateFocus(): void
    {
        $leftFocused = $this->focusedPanelIndex === 0;
        $rightFocused = $this->focusedPanelIndex === 1;

        $this->leftPanel->setFocused($leftFocused);
        $this->rightPanel->setFocused($rightFocused);
        $this->table->setFocused($leftFocused);
    }
}
