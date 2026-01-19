<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\Git\Tab;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\Module\Git\Service\BranchInfo;
use Butschster\Commander\Module\Git\Service\GitService;
use Butschster\Commander\UI\Component\Container\AbstractTab;
use Butschster\Commander\UI\Component\Container\GridLayout;
use Butschster\Commander\UI\Component\Display\TableColumn;
use Butschster\Commander\UI\Component\Display\TableComponent;
use Butschster\Commander\UI\Component\Display\TextDisplay;
use Butschster\Commander\UI\Component\Decorator\Padding;
use Butschster\Commander\UI\Component\Layout\Panel;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Branches Tab
 *
 * Shows all git branches (local and remote) with:
 * - Current branch indicator
 * - Tracking status (ahead/behind)
 * - Last commit info
 *
 * Features:
 * - Checkout branches
 * - View branch details
 */
final class BranchesTab extends AbstractTab
{
    private GridLayout $layout;
    private Panel $leftPanel;
    private Panel $rightPanel;
    private TableComponent $table;
    private TextDisplay $detailsDisplay;

    /** @var BranchInfo[] */
    private array $branches = [];

    private int $focusedPanelIndex = 0;

    public function __construct(
        private readonly GitService $gitService,
    ) {
        $this->initializeComponents();
    }

    #[\Override]
    public function getTitle(): string
    {
        return 'Branches';
    }

    #[\Override]
    public function getShortcuts(): array
    {
        return [
            'Tab' => 'Switch Panel',
            'Enter' => 'Checkout',
            'Ctrl+R' => 'Refresh',
        ];
    }

    #[\Override]
    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);
        $this->layout->render($renderer, $x, $y, $width, $height);
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        $input = KeyInput::from($key);

        // Refresh data (Ctrl+R)
        if ($input->isCtrl(Key::R)) {
            $this->gitService->clearCache();
            $this->loadData();
            return true;
        }

        // Checkout branch (Enter)
        if ($input->is(Key::ENTER) && $this->focusedPanelIndex === 0) {
            return $this->checkoutSelectedBranch();
        }

        // Switch panel focus (Tab)
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
    protected function onTabActivated(): void
    {
        $this->loadData();
        $this->updateFocus();
    }

    private function initializeComponents(): void
    {
        $this->table = $this->createTable();
        $this->detailsDisplay = new TextDisplay();

        $this->leftPanel = new Panel('Branches', $this->table);
        $this->leftPanel->setFocused(true);

        $paddedDetails = Padding::symmetric($this->detailsDisplay, horizontal: 2, vertical: 1);
        $this->rightPanel = new Panel('Details', $paddedDetails);

        $this->layout = new GridLayout(columns: ['55%', '45%']);
        $this->layout->setColumn(0, $this->leftPanel);
        $this->layout->setColumn(1, $this->rightPanel);
    }

    private function createTable(): TableComponent
    {
        $table = new TableComponent([
            new TableColumn(
                'name',
                'Branch',
                '45%',
                TableColumn::ALIGN_LEFT,
                formatter: static fn($value, $row) => $row['isCurrent'] ? "* {$value}" : "  {$value}",
                colorizer: function ($value, $row, $selected) {
                    if ($selected && $this->leftPanel->isFocused()) {
                        return ColorScheme::$SELECTED_TEXT;
                    }
                    if ($row['isCurrent']) {
                        return ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_GREEN, ColorScheme::BOLD);
                    }
                    if ($row['isRemote']) {
                        return ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_RED);
                    }
                    return ColorScheme::$NORMAL_TEXT;
                },
            ),
            new TableColumn(
                'tracking',
                'Tracking',
                '15%',
                TableColumn::ALIGN_CENTER,
                colorizer: function ($value, $row, $selected) {
                    if ($selected && $this->leftPanel->isFocused()) {
                        return ColorScheme::$SELECTED_TEXT;
                    }
                    if (\str_contains($value, '↑') && \str_contains($value, '↓')) {
                        return ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_YELLOW);
                    }
                    if (\str_contains($value, '↑')) {
                        return ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_GREEN);
                    }
                    if (\str_contains($value, '↓')) {
                        return ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_RED);
                    }
                    return ColorScheme::$MUTED_TEXT;
                },
            ),
            new TableColumn(
                'commit',
                'Commit',
                '12%',
                TableColumn::ALIGN_LEFT,
                colorizer: fn($value, $row, $selected) => $selected && $this->leftPanel->isFocused()
                    ? ColorScheme::$SELECTED_TEXT
                    : ColorScheme::$MUTED_TEXT,
            ),
            new TableColumn(
                'message',
                'Last Commit',
                '*',
                TableColumn::ALIGN_LEFT,
                colorizer: fn($value, $row, $selected) => $selected && $this->leftPanel->isFocused()
                    ? ColorScheme::$SELECTED_TEXT
                    : ColorScheme::$NORMAL_TEXT,
            ),
        ], showHeader: true);

        $table->setFocused(true);

        $table->onChange(function (array $row, int $index): void {
            $this->showBranchDetails($index);
        });

        return $table;
    }

    private function loadData(): void
    {
        $this->branches = $this->gitService->getBranches();

        $rows = [];
        foreach ($this->branches as $branch) {
            $rows[] = [
                'name' => $branch->name,
                'isCurrent' => $branch->isCurrent,
                'isRemote' => $branch->isRemote,
                'tracking' => $branch->getTrackingStatus(),
                'commit' => $branch->getShortCommitHash(),
                'message' => $branch->lastCommitMessage ?? '',
            ];
        }

        $this->table->setRows($rows);

        // Count local vs remote
        $localCount = \count(\array_filter($this->branches, static fn($b) => !$b->isRemote));
        $remoteCount = \count(\array_filter($this->branches, static fn($b) => $b->isRemote));
        $this->leftPanel->setTitle("Branches ({$localCount} local, {$remoteCount} remote)");

        // Show first branch details
        if (!empty($this->branches)) {
            $this->showBranchDetails(0);
        }
    }

    private function showBranchDetails(int $index): void
    {
        if (!isset($this->branches[$index])) {
            return;
        }

        $branch = $this->branches[$index];

        $lines = [];

        // Branch name with type
        $type = $branch->isRemote ? 'Remote' : 'Local';
        $current = $branch->isCurrent ? ' (current)' : '';
        $lines[] = "Branch: {$branch->name}";
        $lines[] = "Type: {$type}{$current}";
        $lines[] = '';

        // Tracking info
        if ($branch->hasUpstream()) {
            $lines[] = "Upstream: {$branch->upstream}";
            if ($branch->aheadCount !== null || $branch->behindCount !== null) {
                $ahead = $branch->aheadCount ?? 0;
                $behind = $branch->behindCount ?? 0;
                $lines[] = "Status: {$ahead} ahead, {$behind} behind";
            }
            $lines[] = '';
        }

        // Last commit
        if ($branch->lastCommitHash !== null) {
            $lines[] = "Last Commit:";
            $lines[] = "  Hash: {$branch->getShortCommitHash()}";
            if ($branch->lastCommitMessage !== null) {
                $lines[] = "  Message: {$branch->lastCommitMessage}";
            }
        }

        $this->detailsDisplay->setText(\implode("\n", $lines));
        $this->rightPanel->setTitle("Details: {$branch->name}");
    }

    private function checkoutSelectedBranch(): bool
    {
        $selectedIndex = $this->table->getSelectedIndex();
        if (!isset($this->branches[$selectedIndex])) {
            return false;
        }

        $branch = $this->branches[$selectedIndex];

        if ($branch->isCurrent) {
            return false; // Already on this branch
        }

        // For remote branches, checkout without the remote prefix
        $branchName = $branch->name;
        if ($branch->isRemote && \str_contains($branchName, '/')) {
            // Extract local branch name from remote (e.g., "origin/feature" -> "feature")
            $parts = \explode('/', $branchName, 2);
            $branchName = $parts[1] ?? $branchName;
        }

        $this->gitService->checkout($branchName);
        $this->loadData();
        return true;
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
