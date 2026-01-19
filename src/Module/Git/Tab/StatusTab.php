<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\Git\Tab;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\Module\Git\Component\DiffViewer;
use Butschster\Commander\Module\Git\Service\FileStatus;
use Butschster\Commander\Module\Git\Service\GitService;
use Butschster\Commander\UI\Component\Container\AbstractTab;
use Butschster\Commander\UI\Component\Container\GridLayout;
use Butschster\Commander\UI\Component\Display\TableColumn;
use Butschster\Commander\UI\Component\Display\TableComponent;
use Butschster\Commander\UI\Component\Layout\Panel;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Status Tab
 *
 * Shows git status with:
 * - Staged files (ready for commit)
 * - Unstaged files (modified but not staged)
 * - Untracked files (new files)
 * - Conflict files (merge conflicts)
 *
 * Features:
 * - Stage/unstage individual files
 * - View diff preview
 * - Stage/unstage all files
 */
final class StatusTab extends AbstractTab
{
    private GridLayout $layout;
    private Panel $leftPanel;
    private Panel $rightPanel;
    private TableComponent $table;
    private DiffViewer $diffViewer;

    /** @var array<array{path: string, status: string, type: string, file: FileStatus}> */
    private array $files = [];

    private int $focusedPanelIndex = 0;

    public function __construct(
        private readonly GitService $gitService,
    ) {
        $this->initializeComponents();
    }

    #[\Override]
    public function getTitle(): string
    {
        $count = $this->gitService->getChangedFilesCount();
        return $count > 0 ? "Status ({$count})" : 'Status';
    }

    #[\Override]
    public function getShortcuts(): array
    {
        return [
            'Tab' => 'Switch Panel',
            's' => 'Stage',
            'u' => 'Unstage',
            'a' => 'Stage All',
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

        // Stage file (s)
        if ($input->is(Key::S) && $this->focusedPanelIndex === 0) {
            return $this->stageSelectedFile();
        }

        // Unstage file (u)
        if ($input->is(Key::U) && $this->focusedPanelIndex === 0) {
            return $this->unstageSelectedFile();
        }

        // Stage all (a)
        if ($input->is(Key::A)) {
            $this->gitService->stageAll();
            $this->loadData();
            return true;
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
        $this->diffViewer = new DiffViewer();

        $this->leftPanel = new Panel('Changes', $this->table);
        $this->leftPanel->setFocused(true);

        $this->rightPanel = new Panel('Diff Preview', $this->diffViewer);

        $this->layout = new GridLayout(columns: ['50%', '50%']);
        $this->layout->setColumn(0, $this->leftPanel);
        $this->layout->setColumn(1, $this->rightPanel);
    }

    private function createTable(): TableComponent
    {
        $table = new TableComponent([
            new TableColumn(
                'type',
                'Type',
                '12%',
                TableColumn::ALIGN_CENTER,
                formatter: static fn($value) => match ($value) {
                    FileStatus::STAGED => '[S]',
                    FileStatus::UNSTAGED => '[M]',
                    FileStatus::UNTRACKED => '[?]',
                    FileStatus::CONFLICT => '[!]',
                    default => '[ ]',
                },
                colorizer: function ($value, $row, $selected) {
                    if ($selected && $this->leftPanel->isFocused()) {
                        return ColorScheme::$SELECTED_TEXT;
                    }
                    return match ($value) {
                        FileStatus::STAGED => ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_GREEN, ColorScheme::BOLD),
                        FileStatus::UNSTAGED => ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_YELLOW, ColorScheme::BOLD),
                        FileStatus::UNTRACKED => ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_CYAN),
                        FileStatus::CONFLICT => ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_RED, ColorScheme::BOLD),
                        default => ColorScheme::$NORMAL_TEXT,
                    };
                },
            ),
            new TableColumn(
                'status',
                'Status',
                '18%',
                TableColumn::ALIGN_LEFT,
                colorizer: fn($value, $row, $selected) => $selected && $this->leftPanel->isFocused()
                    ? ColorScheme::$SELECTED_TEXT
                    : ColorScheme::$MUTED_TEXT,
            ),
            new TableColumn(
                'path',
                'File',
                '*',
                TableColumn::ALIGN_LEFT,
                colorizer: function ($value, $row, $selected) {
                    if ($selected && $this->leftPanel->isFocused()) {
                        return ColorScheme::$SELECTED_TEXT;
                    }
                    return match ($row['type']) {
                        FileStatus::STAGED => ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_GREEN),
                        FileStatus::UNSTAGED => ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_YELLOW),
                        FileStatus::CONFLICT => ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_RED),
                        default => ColorScheme::$NORMAL_TEXT,
                    };
                },
            ),
        ], showHeader: true);

        $table->setFocused(true);

        $table->onChange(function (array $row, int $index): void {
            $this->showFileDiff($row);
        });

        return $table;
    }

    private function loadData(): void
    {
        $status = $this->gitService->getStatus();

        $this->files = [];

        // Add staged files
        foreach ($status['staged'] as $file) {
            $this->files[] = [
                'path' => $file->getDisplayPath(),
                'status' => $file->getStatusLabel(),
                'type' => FileStatus::STAGED,
                'file' => $file,
            ];
        }

        // Add unstaged files
        foreach ($status['unstaged'] as $file) {
            $this->files[] = [
                'path' => $file->getDisplayPath(),
                'status' => $file->getStatusLabel(),
                'type' => FileStatus::UNSTAGED,
                'file' => $file,
            ];
        }

        // Add untracked files
        foreach ($status['untracked'] as $file) {
            $this->files[] = [
                'path' => $file->getDisplayPath(),
                'status' => $file->getStatusLabel(),
                'type' => FileStatus::UNTRACKED,
                'file' => $file,
            ];
        }

        // Add conflict files
        foreach ($status['conflicts'] as $file) {
            $this->files[] = [
                'path' => $file->getDisplayPath(),
                'status' => $file->getStatusLabel(),
                'type' => FileStatus::CONFLICT,
                'file' => $file,
            ];
        }

        $this->table->setRows($this->files);

        // Update panel title with counts
        $stagedCount = \count($status['staged']);
        $unstagedCount = \count($status['unstaged']);
        $untrackedCount = \count($status['untracked']);
        $conflictCount = \count($status['conflicts']);

        $title = 'Changes';
        $parts = [];
        if ($stagedCount > 0) {
            $parts[] = "{$stagedCount} staged";
        }
        if ($unstagedCount > 0) {
            $parts[] = "{$unstagedCount} modified";
        }
        if ($untrackedCount > 0) {
            $parts[] = "{$untrackedCount} untracked";
        }
        if ($conflictCount > 0) {
            $parts[] = "{$conflictCount} conflicts";
        }
        if (!empty($parts)) {
            $title .= ' (' . \implode(', ', $parts) . ')';
        }
        $this->leftPanel->setTitle($title);

        // Show first file diff
        if (!empty($this->files)) {
            $this->showFileDiff($this->files[0]);
        } else {
            $this->diffViewer->setContent('No changes');
            $this->rightPanel->setTitle('Diff Preview');
        }
    }

    private function showFileDiff(array $row): void
    {
        /** @var FileStatus $file */
        $file = $row['file'];

        if ($file->isUntracked()) {
            // Show file content for untracked files
            $filePath = $this->gitService->getRepositoryPath() . '/' . $file->path;
            if (\is_file($filePath) && \is_readable($filePath)) {
                $content = @\file_get_contents($filePath);
                if ($content !== false) {
                    // Limit preview size
                    $lines = \explode("\n", $content);
                    if (\count($lines) > 100) {
                        $lines = \array_slice($lines, 0, 100);
                        $lines[] = '... (truncated)';
                    }
                    $this->diffViewer->setContent(\implode("\n", $lines), false);
                } else {
                    $this->diffViewer->setContent('Cannot read file');
                }
            } else {
                $this->diffViewer->setContent('Cannot read file');
            }
        } else {
            // Show diff for tracked files
            $diff = $this->gitService->getFileDiff($file->path, staged: $file->isStaged());
            $this->diffViewer->setContent($diff ?? 'No diff available', true);
        }

        $this->rightPanel->setTitle("Diff: {$file->path}");
    }

    private function stageSelectedFile(): bool
    {
        $selectedRow = $this->table->getSelectedRow();
        if ($selectedRow === null) {
            return false;
        }

        /** @var FileStatus $file */
        $file = $selectedRow['file'];

        if ($file->isStaged()) {
            return false; // Already staged
        }

        $this->gitService->stageFile($file->path);
        $this->loadData();
        return true;
    }

    private function unstageSelectedFile(): bool
    {
        $selectedRow = $this->table->getSelectedRow();
        if ($selectedRow === null) {
            return false;
        }

        /** @var FileStatus $file */
        $file = $selectedRow['file'];

        if (!$file->isStaged()) {
            return false; // Not staged
        }

        $this->gitService->unstageFile($file->path);
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
        $this->diffViewer->setFocused($rightFocused);
    }
}
