<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Tab;

use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;
use Butschster\Commander\Feature\ComposerManager\Service\SecurityAdvisory;
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
 * Security Audit Tab
 *
 * Shows security advisories for installed packages
 * with severity indicators
 */
final class SecurityAuditTab extends AbstractTab
{
    private GridLayout $layout;
    private Panel $leftPanel;
    private Panel $rightPanel;
    private TableComponent $table;
    private TextDisplay $detailsDisplay;
    private array $advisories = [];
    private array $auditSummary = [];
    private int $focusedPanelIndex = 0;
    private bool $dataLoaded = false;

    public function __construct(
        private readonly ComposerService $composerService,
    ) {
        $this->initializeComponents();
    }

    public function getTitle(): string
    {
        return 'Security';
    }

    #[\Override]
    public function getShortcuts(): array
    {
        return [
            'Tab' => 'Switch Panel',
            'Enter' => 'View Details',
            'Ctrl+R' => 'Re-audit',
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
        $this->leftPanel = new Panel('Security Advisories', $this->table);
        $this->leftPanel->setFocused(true);

        $paddedDetails = Padding::symmetric($this->detailsDisplay, horizontal: 2, vertical: 1);
        $this->rightPanel = new Panel('Advisory Details', $paddedDetails);

        // Create grid layout
        $this->layout = new GridLayout(columns: ['55%', '45%']);
        $this->layout->setColumn(0, $this->leftPanel);
        $this->layout->setColumn(1, $this->rightPanel);
    }

    private function createTable(): TableComponent
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
                        return ColorScheme::$SELECTED_TEXT;
                    }
                    return match ($value) {
                        'critical', 'high' => ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_RED, ColorScheme::BOLD),
                        'medium' => ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_YELLOW, ColorScheme::BOLD),
                        default => ColorScheme::$NORMAL_TEXT,
                    };
                },
            ),
            new TableColumn('package', 'Package', '30%', TableColumn::ALIGN_LEFT),
            new TableColumn(
                'title',
                'Vulnerability',
                '*',
                TableColumn::ALIGN_LEFT,
                formatter: static fn($value) => \mb_substr((string) $value, 0, 60) . (\mb_strlen((string) $value) > 60 ? '...' : ''),
            ),
            new TableColumn('cve', 'CVE', '15%', TableColumn::ALIGN_CENTER),
        ], showHeader: true);

        $table->setFocused(true);

        $table->onChange(function (array $row, int $index): void {
            $this->showAdvisoryDetails($row);
        });

        $table->onSelect(function (array $row, int $index): void {
            $this->showAdvisoryDetails($row);
        });

        return $table;
    }

    private function loadData(): void
    {
        $auditResult = $this->composerService->runAudit();
        $this->auditSummary = $auditResult['summary'];

        $this->advisories = \array_map(static fn(SecurityAdvisory $advisory)
            => [
                'severity' => $advisory->severity,
                'package' => $advisory->packageName,
                'title' => $advisory->title,
                'cve' => $advisory->cve ?: 'N/A',
                'affectedVersions' => $advisory->affectedVersions,
                'link' => $advisory->link,
            ], $auditResult['advisories']);

        $this->table->setRows($this->advisories);

        // Update panel title
        $count = \count($this->advisories);
        $critical = $this->auditSummary['high'] ?? 0;
        $title = "Security Audit ($count)";
        if ($critical > 0) {
            $title .= " - [!!] $critical Critical/High";
        }
        $this->leftPanel->setTitle($title);

        // Show first advisory or empty state
        if (!empty($this->advisories)) {
            $this->showAdvisoryDetails($this->advisories[0]);
        } else {
            $this->detailsDisplay->setText("[OK] No security vulnerabilities found!");
        }
    }

    private function showAdvisoryDetails(array $advisory): void
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

    private function updateFocus(): void
    {
        $leftFocused = $this->focusedPanelIndex === 0;
        $rightFocused = $this->focusedPanelIndex === 1;

        $this->leftPanel->setFocused($leftFocused);
        $this->rightPanel->setFocused($rightFocused);
        $this->table->setFocused($leftFocused);
    }
}
