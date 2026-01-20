<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\Git\Tab;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\Module\Git\Service\GitService;
use Butschster\Commander\Module\Git\Service\TagInfo;
use Butschster\Commander\UI\Component\Container\AbstractTab;
use Butschster\Commander\UI\Component\Container\GridLayout;
use Butschster\Commander\UI\Component\Display\TableColumn;
use Butschster\Commander\UI\Component\Display\TableComponent;
use Butschster\Commander\UI\Component\Display\TextDisplay;
use Butschster\Commander\UI\Component\Decorator\Padding;
use Butschster\Commander\UI\Component\Layout\Panel;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Tags Tab
 *
 * Shows all git tags with:
 * - Tag name and type (annotated/lightweight)
 * - Commit hash
 * - Tag message (for annotated tags)
 * - Tagger info
 */
final class TagsTab extends AbstractTab
{
    private GridLayout $layout;
    private Panel $leftPanel;
    private Panel $rightPanel;
    private TableComponent $table;
    private TextDisplay $detailsDisplay;

    /** @var TagInfo[] */
    private array $tags = [];

    private int $focusedPanelIndex = 0;

    public function __construct(
        private readonly GitService $gitService,
    ) {
        $this->initializeComponents();
    }

    #[\Override]
    public function getTitle(): string
    {
        $count = \count($this->tags);
        return $count > 0 ? "Tags ({$count})" : 'Tags';
    }

    #[\Override]
    public function getShortcuts(): array
    {
        return [
            'Tab' => 'Switch Panel',
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

        $this->leftPanel = new Panel('Tags', $this->table);
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
                'Tag',
                '35%',
                TableColumn::ALIGN_LEFT,
                colorizer: function ($value, $row, $selected) {
                    if ($selected && $this->leftPanel->isFocused()) {
                        return ColorScheme::$SELECTED_TEXT;
                    }
                    if ($row['isAnnotated']) {
                        return ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_YELLOW, ColorScheme::BOLD);
                    }
                    return ColorScheme::$NORMAL_TEXT;
                },
            ),
            new TableColumn(
                'type',
                'Type',
                '15%',
                TableColumn::ALIGN_CENTER,
                formatter: static fn($value) => $value ? 'annotated' : 'light',
                colorizer: function ($value, $row, $selected) {
                    if ($selected && $this->leftPanel->isFocused()) {
                        return ColorScheme::$SELECTED_TEXT;
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
                'Message',
                '*',
                TableColumn::ALIGN_LEFT,
                colorizer: fn($value, $row, $selected) => $selected && $this->leftPanel->isFocused()
                    ? ColorScheme::$SELECTED_TEXT
                    : ColorScheme::$NORMAL_TEXT,
            ),
        ], showHeader: true);

        $table->setFocused(true);

        $table->onChange(function (array $row, int $index): void {
            $this->showTagDetails($index);
        });

        return $table;
    }

    private function loadData(): void
    {
        $this->tags = $this->gitService->getTags();

        $rows = [];
        foreach ($this->tags as $tag) {
            $rows[] = [
                'name' => $tag->name,
                'type' => $tag->isAnnotated,
                'isAnnotated' => $tag->isAnnotated,
                'commit' => $tag->getShortCommitHash(),
                'message' => $tag->message ?? '',
            ];
        }

        $this->table->setRows($rows);

        // Count annotated vs lightweight
        $annotatedCount = \count(\array_filter($this->tags, static fn($t) => $t->isAnnotated));
        $lightweightCount = \count($this->tags) - $annotatedCount;
        $this->leftPanel->setTitle("Tags ({$annotatedCount} annotated, {$lightweightCount} lightweight)");

        // Show first tag details
        if (!empty($this->tags)) {
            $this->showTagDetails(0);
        } else {
            $this->detailsDisplay->setText('No tags found');
            $this->rightPanel->setTitle('Details');
        }
    }

    private function showTagDetails(int $index): void
    {
        if (!isset($this->tags[$index])) {
            return;
        }

        $tag = $this->tags[$index];

        $lines = [];

        // Tag name and type
        $type = $tag->isAnnotated ? 'Annotated' : 'Lightweight';
        $lines[] = "Tag: {$tag->name}";
        $lines[] = "Type: {$type}";
        $lines[] = '';

        // Commit info
        $lines[] = "Commit: {$tag->commitHash}";
        $lines[] = '';

        // Tagger info (for annotated tags)
        if ($tag->isAnnotated) {
            if ($tag->taggerName !== null) {
                $lines[] = "Tagger: {$tag->taggerName}";
            }
            if ($tag->taggerDate !== null) {
                $lines[] = "Date: {$tag->taggerDate}";
            }
            $lines[] = '';
        }

        // Message
        if ($tag->hasMessage()) {
            $lines[] = 'Message:';
            $lines[] = $tag->message;
        }

        $this->detailsDisplay->setText(\implode("\n", $lines));
        $this->rightPanel->setTitle("Details: {$tag->name}");
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
