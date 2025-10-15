<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Tab;

use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;
use Butschster\Commander\Feature\ComposerManager\Service\OutdatedPackageInfo;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Container\AbstractTab;
use Butschster\Commander\UI\Component\Container\GridLayout;
use Butschster\Commander\UI\Component\Decorator\Padding;
use Butschster\Commander\UI\Component\Display\TableColumn;
use Butschster\Commander\UI\Component\Display\TableComponent;
use Butschster\Commander\UI\Component\Display\TextDisplay;
use Butschster\Commander\UI\Component\Layout\Panel;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Outdated Packages Tab
 *
 * Shows packages that have newer versions available
 * with update type indication (major/minor/patch)
 */
final class OutdatedPackagesTab extends AbstractTab
{
    private GridLayout $layout;
    private Panel $leftPanel;
    private Panel $rightPanel;
    private TableComponent $table;
    private TextDisplay $detailsDisplay;
    private array $packages = [];
    private ?string $selectedPackageName = null;
    private int $focusedPanelIndex = 0;
    private bool $dataLoaded = false;

    public function __construct(
        private readonly ComposerService $composerService,
    ) {
        $this->initializeComponents();
    }

    public function getTitle(): string
    {
        return 'Outdated';
    }

    #[\Override]
    public function getShortcuts(): array
    {
        return [
            'Tab' => 'Switch Panel',
            'Enter' => 'Update',
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
        // Lazy load data only when tab is first activated
        if (!$this->dataLoaded) {
            $this->loadData();
            $this->dataLoaded = true;
        }
        $this->updateFocus();
    }

    private function initializeComponents(): void
    {
        // Create table
        $this->table = $this->createTable();
        $this->detailsDisplay = new TextDisplay();

        // Create panels
        $this->leftPanel = new Panel('Outdated Packages', $this->table);
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
                        return ColorScheme::$SELECTED_TEXT;
                    }
                    return match ($value) {
                        'major' => ColorScheme::combine(
                            ColorScheme::$NORMAL_BG,
                            ColorScheme::FG_RED,
                            ColorScheme::BOLD,
                        ),
                        'minor' => ColorScheme::combine(
                            ColorScheme::$NORMAL_BG,
                            ColorScheme::FG_YELLOW,
                            ColorScheme::BOLD,
                        ),
                        'patch' => ColorScheme::combine(
                            ColorScheme::$NORMAL_BG,
                            ColorScheme::FG_GREEN,
                            ColorScheme::BOLD,
                        ),
                        default => ColorScheme::$NORMAL_TEXT,
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

        $table->setFocused(true);

        $table->onChange(function (array $row, int $index): void {
            $this->selectedPackageName = $row['name'];
            $this->showPackageDetails($row);
        });

        $table->onSelect(function (array $row, int $index): void {
            // TODO: Implement update package functionality
            $this->showPackageDetails($row);
        });

        return $table;
    }

    private function loadData(): void
    {
        $this->packages = \array_map(static fn(OutdatedPackageInfo $pkg)
            => [
                'name' => $pkg->name,
                'current' => $pkg->currentVersion,
                'latest' => $pkg->latestVersion,
                'type' => $pkg->isMajorUpdate() ? 'major' : ($pkg->isMinorUpdate() ? 'minor' : 'patch'),
                'description' => $pkg->description,
                'warning' => $pkg->warning,
            ], $this->composerService->getOutdatedPackages());

        $this->table->setRows($this->packages);

        // Update panel title
        $count = \count($this->packages);
        $this->leftPanel->setTitle("Outdated Packages ($count)");

        // Show first package or empty state
        if (!empty($this->packages)) {
            $this->selectedPackageName = $this->packages[0]['name'];
            $this->showPackageDetails($this->packages[0]);
        } else {
            $this->detailsDisplay->setText("[OK] All packages are up to date!");
        }
    }

    private function showPackageDetails(array $package): void
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

    private function updateFocus(): void
    {
        $leftFocused = $this->focusedPanelIndex === 0;
        $rightFocused = $this->focusedPanelIndex === 1;

        $this->leftPanel->setFocused($leftFocused);
        $this->rightPanel->setFocused($rightFocused);
        $this->table->setFocused($leftFocused);
    }
}
