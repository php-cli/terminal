<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Tab;

use Butschster\Commander\Feature\ComposerManager\Component\LoadingState;
use Butschster\Commander\Feature\ComposerManager\Component\SearchFilter;
use Butschster\Commander\Feature\ComposerManager\Component\UpdateProgressModal;
use Butschster\Commander\Feature\ComposerManager\Service\ComposerBinaryLocator;
use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;
use Butschster\Commander\Feature\ComposerManager\Service\OutdatedPackageInfo;
use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Container\AbstractTab;
use Butschster\Commander\UI\Component\Container\GridLayout;
use Butschster\Commander\UI\Component\Decorator\Padding;
use Butschster\Commander\UI\Component\Display\TableColumn;
use Butschster\Commander\UI\Component\Display\TableComponent;
use Butschster\Commander\UI\Component\Display\TextDisplay;
use Butschster\Commander\UI\Component\Layout\Modal;
use Butschster\Commander\UI\Component\Layout\Panel;
use Butschster\Commander\UI\Theme\ColorScheme;
use Symfony\Component\Process\Process;

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
    private array $allPackages = [];
    private array $filteredPackages = [];
    private ?string $selectedPackageName = null;
    private int $focusedPanelIndex = 0;
    private bool $dataLoaded = false;
    private LoadingState $loadingState;
    private SearchFilter $searchFilter;
    private ?Modal $confirmModal = null;
    private ?UpdateProgressModal $updateModal = null;

    public function __construct(
        private readonly ComposerService $composerService,
    ) {
        $this->loadingState = new LoadingState();
        $this->searchFilter = new SearchFilter($this->applyFilter(...));
        $this->initializeComponents();
    }

    #[\Override]
    public function getTitle(): string
    {
        return 'Outdated';
    }

    #[\Override]
    public function getShortcuts(): array
    {
        if ($this->updateModal !== null) {
            return [
                'Ctrl+C' => 'Cancel',
                'Enter' => 'Close',
            ];
        }

        if ($this->searchFilter->isActive()) {
            return [
                'Enter' => 'Confirm',
                'Esc' => 'Cancel',
                'Ctrl+U' => 'Clear',
            ];
        }

        $shortcuts = [
            '/' => 'Search',
            'Tab' => 'Switch Panel',
            'Enter' => 'Details',
            'U' => 'Update Package',
            'F5' => 'Update All',
            'Ctrl+R' => 'Refresh',
        ];

        if ($this->searchFilter->hasFilter()) {
            $shortcuts['Esc'] = 'Clear Filter';
        }

        return $shortcuts;
    }

    #[\Override]
    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);

        // Reserve space for search filter
        $searchHeight = 1;
        $contentHeight = $height - $searchHeight;

        // Render search filter at top
        $this->searchFilter->render($renderer, $x, $y, $width, 1);

        // Render layout below
        $this->layout->render($renderer, $x, $y + $searchHeight, $width, $contentHeight);

        // Render loading overlay if active
        if ($this->loadingState->isLoading()) {
            $this->loadingState->render($renderer, $x, $y + $searchHeight, $width, $contentHeight);
        }

        // Render confirm modal if active
        if ($this->confirmModal !== null) {
            $this->confirmModal->render($renderer, $x, $y, $width, $height);
        }

        // Render update progress modal if active
        if ($this->updateModal !== null) {
            $this->updateModal->render($renderer, $x, $y, $width, $height);
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        // Handle update modal input first
        if ($this->updateModal !== null) {
            return $this->updateModal->handleInput($key);
        }

        // Handle confirm modal input
        if ($this->confirmModal !== null) {
            return $this->confirmModal->handleInput($key);
        }

        // Handle search filter input first if active
        if ($this->searchFilter->isActive()) {
            return $this->searchFilter->handleInput($key);
        }

        // Block input during loading
        if ($this->loadingState->isLoading()) {
            return true;
        }

        $input = KeyInput::from($key);

        // Activate search with / or Ctrl+F
        if ($key === '/' || $input->isCtrl(Key::F)) {
            $this->searchFilter->activate();
            return true;
        }

        // Clear filter with Escape when filter is active but not in input mode
        if ($input->is(Key::ESCAPE) && $this->searchFilter->hasFilter()) {
            $this->searchFilter->clear();
            return true;
        }

        // Refresh data (Ctrl+R)
        if ($input->isCtrl(Key::R)) {
            $this->composerService->clearCache();
            $this->searchFilter->clear();
            $this->dataLoaded = false;
            $this->loadData();
            $this->dataLoaded = true;
            return true;
        }

        // Update selected package (U)
        if ($input->isKey('u') || $input->isKey('U')) {
            $this->promptUpdateSelectedPackage();
            return true;
        }

        // Update all packages (F5)
        if ($input->is(Key::F5)) {
            $this->promptUpdateAllPackages();
            return true;
        }

        // Switch panel focus
        if ($input->is(Key::TAB)) {
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
    public function update(): void
    {
        parent::update();
        $this->loadingState->update();

        // Update the update modal if active
        if ($this->updateModal !== null) {
            $this->updateModal->update();
        }
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

    private function applyFilter(string $query): void
    {
        if ($query === '') {
            $this->filteredPackages = $this->allPackages;
        } else {
            $query = \mb_strtolower($query);
            $this->filteredPackages = \array_filter(
                $this->allPackages,
                static fn(array $pkg) => \str_contains(\mb_strtolower($pkg['name']), $query)
                    || \str_contains(\mb_strtolower($pkg['description'] ?? ''), $query),
            );
            $this->filteredPackages = \array_values($this->filteredPackages);
        }

        $this->packages = $this->filteredPackages;
        $this->table->setRows($this->filteredPackages);
        $this->updatePanelTitle();
    }

    private function updatePanelTitle(): void
    {
        $total = \count($this->allPackages);
        $filtered = \count($this->filteredPackages);

        if ($this->searchFilter->hasFilter()) {
            $this->leftPanel->setTitle("Outdated Packages ({$filtered}/{$total} shown)");
        } else {
            $this->leftPanel->setTitle("Outdated Packages ({$total})");
        }
    }

    private function loadData(): void
    {
        $this->loadingState->start('Checking for outdated packages...');

        try {
            $this->allPackages = \array_map(static fn(OutdatedPackageInfo $pkg)
                => [
                    'name' => $pkg->name,
                    'current' => $pkg->currentVersion,
                    'latest' => $pkg->latestVersion,
                    'type' => $pkg->isMajorUpdate() ? 'major' : ($pkg->isMinorUpdate() ? 'minor' : 'patch'),
                    'description' => $pkg->description,
                    'warning' => $pkg->warning,
                ], $this->composerService->getOutdatedPackages());

            $this->filteredPackages = $this->allPackages;
            $this->packages = $this->filteredPackages;
            $this->table->setRows($this->filteredPackages);
            $this->updatePanelTitle();

            // Show first package or empty state
            if (!empty($this->packages)) {
                $this->selectedPackageName = $this->packages[0]['name'];
                $this->showPackageDetails($this->packages[0]);
            } else {
                $this->detailsDisplay->setText("[OK] All packages are up to date!");
            }
        } finally {
            $this->loadingState->stop();
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
        $lines[] = "Press U to update this package";

        $this->detailsDisplay->setText(\implode("\n", $lines));
        $this->rightPanel->setTitle("Outdated: {$package['name']}");
    }

    private function promptUpdateSelectedPackage(): void
    {
        if ($this->selectedPackageName === null || empty($this->packages)) {
            return;
        }

        $packageName = $this->selectedPackageName;
        $this->confirmModal = Modal::confirm(
            'Update Package',
            "Are you sure you want to update '{$packageName}'?\n\n" .
            "This will run 'composer update {$packageName} --with-dependencies'.",
        );

        $this->confirmModal->onClose(function ($confirmed) use ($packageName): void {
            $this->confirmModal = null;
            if ($confirmed) {
                $this->startPackageUpdate($packageName);
            }
        });
    }

    private function promptUpdateAllPackages(): void
    {
        if (empty($this->packages)) {
            return;
        }

        $count = \count($this->packages);
        $this->confirmModal = Modal::confirm(
            'Update All Packages',
            "Are you sure you want to update all {$count} outdated packages?\n\n" .
            "This will run 'composer update'.",
        );

        $this->confirmModal->onClose(function ($confirmed): void {
            $this->confirmModal = null;
            if ($confirmed) {
                $this->startUpdateAll();
            }
        });
    }

    private function startPackageUpdate(string $packageName): void
    {
        $composerBinary = ComposerBinaryLocator::find();
        if ($composerBinary === null) {
            return;
        }

        $this->updateModal = new UpdateProgressModal($packageName);
        $this->updateModal->onClose(function (): void {
            $exitCode = $this->updateModal?->getExitCode();
            $this->updateModal = null;

            // Refresh data after successful update
            if ($exitCode === 0) {
                $this->composerService->clearCache();
                $this->dataLoaded = false;
                $this->loadData();
                $this->dataLoaded = true;
            }
        });

        $process = new Process(
            [$composerBinary, 'update', $packageName, '--with-dependencies'],
            \getcwd(),
            null,
            null,
            null, // No timeout
        );

        $this->updateModal->startProcess($process);
    }

    private function startUpdateAll(): void
    {
        $composerBinary = ComposerBinaryLocator::find();
        if ($composerBinary === null) {
            return;
        }

        $this->updateModal = new UpdateProgressModal();
        $this->updateModal->onClose(function (): void {
            $exitCode = $this->updateModal?->getExitCode();
            $this->updateModal = null;

            // Refresh data after successful update
            if ($exitCode === 0) {
                $this->composerService->clearCache();
                $this->dataLoaded = false;
                $this->loadData();
                $this->dataLoaded = true;
            }
        });

        $process = new Process(
            [$composerBinary, 'update'],
            \getcwd(),
            null,
            null,
            null, // No timeout
        );

        $this->updateModal->startProcess($process);
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
