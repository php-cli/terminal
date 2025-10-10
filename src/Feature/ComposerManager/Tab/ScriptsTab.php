<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Tab;

use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Container\AbstractTab;
use Butschster\Commander\UI\Component\Container\GridLayout;
use Butschster\Commander\UI\Component\Decorator\Padding;
use Butschster\Commander\UI\Component\Display\Alert;
use Butschster\Commander\UI\Component\Display\TableColumn;
use Butschster\Commander\UI\Component\Display\TableComponent;
use Butschster\Commander\UI\Component\Display\TextDisplay;
use Butschster\Commander\UI\Component\Layout\Modal;
use Butschster\Commander\UI\Component\Layout\Panel;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Scripts Tab
 *
 * Shows all available composer scripts with:
 * - Script name
 * - Script commands
 * - Ability to execute scripts
 */
final class ScriptsTab extends AbstractTab
{
    private GridLayout $layout;
    private Panel $leftPanel;
    private Panel $rightPanel;
    private TableComponent $table;
    private TextDisplay $detailsDisplay;
    private array $scripts = [];
    private ?string $selectedScript = null;
    private int $focusedPanelIndex = 0;
    private bool $isExecuting = false;
    private ?Modal $activeModal = null;
    private ?Alert $statusAlert = null;

    public function __construct(
        private readonly ComposerService $composerService,
    ) {
        $this->initializeComponents();
    }

    public function getTitle(): string
    {
        return 'Scripts';
    }

    #[\Override]
    public function getShortcuts(): array
    {
        return [
            'Tab' => 'Switch Panel',
            'Enter' => 'Run Script',
            'Ctrl+R' => 'Refresh',
        ];
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);
        $this->layout->render($renderer, $x, $y, $width, $height);

        // Overlay: Status alert at top of viewport (error, success, or executing)
        if ($this->statusAlert !== null) {
            $leftWidth = (int) ($width * 0.4);
            $rightWidth = $width - $leftWidth;
            // Render at top of viewport (y position), positioned on right side
            $this->statusAlert->render($renderer, $x + $leftWidth, $y, $rightWidth, 1);
        }

        // Overlay: Modal dialog
        if ($this->activeModal !== null) {
            $this->activeModal->setFocused(true);
            $this->activeModal->render($renderer, 0, 0, $width, $height);
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        // Priority 1: Modal (if active)
        if ($this->activeModal !== null) {
            return $this->activeModal->handleInput($key);
        }

        // Priority 2: Block input during script execution
        if ($this->isExecuting) {
            return true;
        }

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

        // Escape from right panel
        if ($key === 'ESCAPE' && $this->focusedPanelIndex === 1) {
            $this->focusedPanelIndex = 0;
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

        // Auto-hide alert if expired
        if ($this->statusAlert !== null && $this->statusAlert->isExpired()) {
            $this->statusAlert = null;
        }
    }

    #[\Override]
    protected function onTabActivated(): void
    {
        $this->loadData();
        $this->updateFocus();
    }

    private static function truncateCommand(string $command): string
    {
        $maxLength = 60;
        if (\mb_strlen($command) <= $maxLength) {
            return $command;
        }
        return \mb_substr($command, 0, $maxLength - 3) . '...';
    }

    private function initializeComponents(): void
    {
        // Create table
        $this->table = $this->createTable();
        $this->detailsDisplay = new TextDisplay();

        // Create panels
        $this->leftPanel = new Panel('Scripts', $this->table);
        $this->leftPanel->setFocused(true);

        $paddedDetails = Padding::symmetric($this->detailsDisplay, horizontal: 2, vertical: 1);
        $this->rightPanel = new Panel('Details', $paddedDetails);

        // Create grid layout
        $this->layout = new GridLayout(columns: ['40%', '60%']);
        $this->layout->setColumn(0, $this->leftPanel);
        $this->layout->setColumn(1, $this->rightPanel);
    }

    private function createTable(): TableComponent
    {
        $table = new TableComponent([
            new TableColumn(
                'name',
                'Script',
                '40%',
                TableColumn::ALIGN_LEFT,
                colorizer: function ($value, $row, $selected) {
                    if ($selected && $this->leftPanel->isFocused()) {
                        return ColorScheme::$SELECTED_TEXT;
                    }
                    return ColorScheme::$HIGHLIGHT_TEXT;
                },
            ),
            new TableColumn(
                'command',
                'Command',
                '*',
                TableColumn::ALIGN_LEFT,
                formatter: static function ($value, $row) {
                    if (\is_array($value)) {
                        // Multiple commands
                        return \implode(' && ', \array_map(static fn($cmd) => self::truncateCommand($cmd), $value));
                    }
                    return self::truncateCommand((string) $value);
                },
                colorizer: function ($value, $row, $selected) {
                    if ($selected && $this->leftPanel->isFocused()) {
                        return ColorScheme::$SELECTED_TEXT;
                    }
                    return ColorScheme::$NORMAL_TEXT;
                },
            ),
        ], showHeader: true);

        $table->setFocused(true);

        $table->onChange(function (array $row, int $index): void {
            $this->selectedScript = $row['name'];
            $this->showScriptDetails($row['name']);
        });

        $table->onSelect(function (array $row, int $index): void {
            $this->runScript($row['name']);
        });

        return $table;
    }

    private function loadData(): void
    {
        // Load scripts
        $rawScripts = $this->composerService->getRootScripts();

        $this->scripts = [];
        foreach ($rawScripts as $name => $command) {
            $this->scripts[] = [
                'name' => $name,
                'command' => $command,
            ];
        }

        // Sort by name
        \usort($this->scripts, static fn($a, $b) => \strcasecmp($a['name'], $b['name']));

        $this->table->setRows($this->scripts);

        // Show first script details
        if (!empty($this->scripts)) {
            $this->selectedScript = $this->scripts[0]['name'];
            $this->showScriptDetails($this->scripts[0]['name']);
        } else {
            $this->detailsDisplay->setText(
                "No scripts found in composer.json\n\nAdd scripts to your composer.json file to see them here.",
            );
        }
    }

    private function showScriptDetails(string $scriptName): void
    {
        $script = null;
        foreach ($this->scripts as $s) {
            if ($s['name'] === $scriptName) {
                $script = $s;
                break;
            }
        }

        if (!$script) {
            $this->detailsDisplay->setText("Script not found");
            return;
        }

        $lines = [
            "Script: {$script['name']}",
            "",
            "Command(s):",
        ];

        if (\is_array($script['command'])) {
            foreach ($script['command'] as $cmd) {
                $lines[] = "  • " . $cmd;
            }
        } else {
            $lines[] = "  " . $script['command'];
        }

        $lines[] = "";
        $lines[] = "Press Enter to run this script";

        $this->detailsDisplay->setText(\implode("\n", $lines));
        $this->rightPanel->setTitle("Details: {$script['name']}");
    }

    private function runScript(string $scriptName): void
    {
        if ($this->isExecuting) {
            return;
        }

        $this->performScriptExecution($scriptName);
    }

    private function performScriptExecution(string $scriptName): void
    {
        $this->isExecuting = true;

        // Show executing alert
        $this->showAlert(Alert::info('EXECUTING...'));

        // Switch focus to right panel to show output
        $this->focusedPanelIndex = 1;
        $this->updateFocus();

        // Create output display
        $this->detailsDisplay->setText("Executing script '$scriptName'...\n\n");
        $this->rightPanel->setTitle("Running: $scriptName");

        try {
            $hasOutput = false;

            // Execute script
            $result = $this->composerService->runScript($scriptName, function (string $line) use (&$hasOutput): void {
                // Strip ANSI escape codes and normalize line endings
                $cleaned = $this->cleanOutput($line);
                if ($cleaned !== '') {
                    $this->detailsDisplay->appendText($cleaned);
                    $hasOutput = true;
                }
            });

            // If no output was produced, show a message
            if (!$hasOutput) {
                $this->detailsDisplay->appendText("(No output from script)\n");
            }

            // Display result
            $this->detailsDisplay->appendText("\n" . \str_repeat('─', 50) . "\n");

            if ($result['exitCode'] === 0) {
                $this->detailsDisplay->appendText("✅ Success (exit code: 0)\n");
                $this->showAlert(Alert::success('SUCCESS'));
            } else {
                $this->detailsDisplay->appendText("❌ Failed (exit code: {$result['exitCode']})\n");
                $this->showAlert(Alert::error('FAILED'));
            }

            $this->detailsDisplay->appendText("\nPress Enter to run again, Tab to select another script.");
        } catch (\Throwable $e) {
            $this->detailsDisplay->appendText("\n❌ EXCEPTION\n" . \str_repeat('─', 50) . "\n");
            $this->detailsDisplay->appendText($e->getMessage() . "\n");
            $this->detailsDisplay->appendText("\nPress Enter to run again, Tab to select another script.");
            $this->showAlert(Alert::error('EXCEPTION'));
        } finally {
            $this->isExecuting = false;
        }
    }

    private function updateFocus(): void
    {
        $leftFocused = $this->focusedPanelIndex === 0;
        $rightFocused = $this->focusedPanelIndex === 1;

        $this->leftPanel->setFocused($leftFocused);
        $this->rightPanel->setFocused($rightFocused);
        $this->table->setFocused($leftFocused);
        $this->detailsDisplay->setFocused($rightFocused);
    }

    private function showAlert(Alert $alert): void
    {
        $this->statusAlert = $alert;
    }

    /**
     * Clean output by removing ANSI escape codes and normalizing line endings
     */
    private function cleanOutput(string $output): string
    {
        // Normalize line endings (convert \r\n and \r to \n)
        return \str_replace(["\r\n", "\r"], ["\n", "\n"], $output);
    }
}
