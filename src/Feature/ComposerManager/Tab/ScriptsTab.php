<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Tab;

use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;
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

        // Overlay: Executing indicator
        if ($this->isExecuting) {
            $this->renderExecutingIndicator($renderer, $x, $y, $width);
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
                        return ColorScheme::SELECTED_TEXT;
                    }
                    return ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_BRIGHT_WHITE);
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
                        return ColorScheme::SELECTED_TEXT;
                    }
                    return ColorScheme::NORMAL_TEXT;
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

        // Show confirmation for potentially destructive scripts
        if ($this->isDangerousScript($scriptName)) {
            $this->showConfirmationModal(
                'Confirm Execution',
                "You are about to run the script '$scriptName'.\n\n" .
                "This script may perform destructive operations.\n\n" .
                "Are you sure you want to continue?",
                fn() => $this->performScriptExecution($scriptName),
            );
            return;
        }

        $this->performScriptExecution($scriptName);
    }

    private function isDangerousScript(string $scriptName): bool
    {
        $dangerousPatterns = [
            'delete',
            'remove',
            'drop',
            'truncate',
            'clear',
            'purge',
            'destroy',
            'reset',
            'fresh',
            'wipe',
            'clean',
        ];

        $lowerScript = \strtolower($scriptName);

        foreach ($dangerousPatterns as $pattern) {
            if (\str_contains($lowerScript, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function performScriptExecution(string $scriptName): void
    {
        $this->isExecuting = true;

        // Switch focus to right panel to show output
        $this->focusedPanelIndex = 1;
        $this->updateFocus();

        // Create output display
        $this->detailsDisplay->setText("Executing script '$scriptName'...\n\n");
        $this->rightPanel->setTitle("Running: $scriptName");

        try {
            // Execute script
            $result = $this->composerService->runScript($scriptName, function (string $line): void {
                // Strip ANSI escape codes and normalize line endings
                $cleaned = $this->cleanOutput($line);
                if ($cleaned !== '') {
                    $this->detailsDisplay->appendText($cleaned);
                }
            });

            // Display result
            $this->detailsDisplay->appendText("\n" . \str_repeat('─', 50) . "\n");

            if ($result['exitCode'] === 0) {
                $this->detailsDisplay->appendText("✅ Success (exit code: 0)\n");
            } else {
                $this->detailsDisplay->appendText("❌ Failed (exit code: {$result['exitCode']})\n");

                if (!empty($result['error'])) {
                    $errorMessage = "Script failed with exit code {$result['exitCode']}.\n\n{$result['error']}";
                    $this->showErrorModal($errorMessage);
                }
            }

            $this->detailsDisplay->appendText("\nPress Enter to run again, Tab to select another script.");
        } catch (\Throwable $e) {
            $this->detailsDisplay->appendText("\n❌ EXCEPTION\n" . \str_repeat('─', 50) . "\n");
            $this->detailsDisplay->appendText($e->getMessage() . "\n");
            $this->showErrorModal('An exception occurred while executing the script: ' . $e->getMessage());
        } finally {
            $this->isExecuting = false;
        }
    }

    private function renderExecutingIndicator(Renderer $renderer, int $x, int $y, int $width): void
    {
        $indicator = ' [EXECUTING...] ';
        $leftWidth = (int) ($width * 0.4);
        $rightWidth = $width - $leftWidth;
        $indicatorX = $x + $leftWidth + (int) (($rightWidth - \mb_strlen($indicator)) / 2);

        $renderer->writeAt(
            $indicatorX,
            $y + 1,
            $indicator,
            ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW, ColorScheme::BOLD),
        );
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

    private function showErrorModal(string $message): void
    {
        $this->activeModal = Modal::error('Error', $message);
        $this->activeModal->onClose(fn() => $this->activeModal = null);
    }

    private function showConfirmationModal(string $title, string $message, callable $onConfirm): void
    {
        $this->activeModal = Modal::confirm($title, $message);
        $this->activeModal->onClose(function ($confirmed) use ($onConfirm): void {
            $this->activeModal = null;
            if ($confirmed) {
                $onConfirm();
            }
        });
    }

    /**
     * Clean output by removing ANSI escape codes and normalizing line endings
     */
    private function cleanOutput(string $output): string
    {
        // Strip all ANSI escape sequences (CSI, OSC, etc.)
        // This handles colors, cursor movements, progress bars, etc.
        $cleaned = \preg_replace([
            '/\x1b\[[0-9;?]*[a-zA-Z]/',  // CSI sequences (colors, cursor control)
            '/\x1b\][0-9;]*[^\x07]*\x07/', // OSC sequences
            '/\x1b[>=]/',                 // Other escape sequences
            '/\x1b[()][AB012]/',          // Character set selection
        ], '', $output);

        // Normalize line endings (convert \r\n and \r to \n)
        $cleaned = \str_replace(["\r\n", "\r"], ["\n", "\n"], $cleaned ?? '');

        return $cleaned;
    }
}
