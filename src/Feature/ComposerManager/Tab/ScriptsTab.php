<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Tab;

use Butschster\Commander\Feature\ComposerManager\Service\ComposerBinaryLocator;
use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;
use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Container\AbstractTab;
use Butschster\Commander\UI\Component\Container\GridLayout;
use Butschster\Commander\UI\Component\Decorator\Padding;
use Butschster\Commander\UI\Component\Display\Alert;
use Butschster\Commander\UI\Component\Display\Spinner;
use Butschster\Commander\UI\Component\Display\TableColumn;
use Butschster\Commander\UI\Component\Display\TableComponent;
use Butschster\Commander\UI\Component\Display\Text\TextBlock;
use Butschster\Commander\UI\Component\Display\TextDisplay;
use Butschster\Commander\UI\Component\Layout\Modal;
use Butschster\Commander\UI\Component\Layout\Panel;
use Butschster\Commander\UI\Theme\ColorScheme;
use Symfony\Component\Process\Process;

/**
 * Scripts Tab
 *
 * Shows all available composer scripts with:
 * - Script name
 * - Script commands
 * - Ability to execute scripts with real-time output
 * - Spinner animation during execution
 */
final class ScriptsTab extends AbstractTab
{
    /** @var array<string> Patterns that indicate potentially destructive scripts */
    private const array DESTRUCTIVE_SCRIPT_PATTERNS = [
        'post-',
        'pre-',
        'deploy',
        'migrate',
        'db:',
        'database',
        'drop',
        'delete',
        'remove',
        'clean',
        'purge',
    ];

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
    private ?Process $runningProcess = null;
    private readonly Spinner $spinner;
    private string $lastProgressLine = '';

    public function __construct(
        private readonly ComposerService $composerService,
    ) {
        $this->spinner = new Spinner(Spinner::STYLE_BRAILLE, 0.1);
        $this->initializeComponents();
    }

    #[\Override]
    public function getTitle(): string
    {
        return 'Scripts';
    }

    #[\Override]
    public function getShortcuts(): array
    {
        if ($this->isExecuting) {
            return [
                'Ctrl+C' => 'Cancel',
            ];
        }

        return [
            'Tab' => 'Switch Panel',
            'Enter' => 'Run Script',
            'Ctrl+R' => 'Refresh',
        ];
    }

    #[\Override]
    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);

        // Update right panel title with spinner during execution
        if ($this->isExecuting && $this->selectedScript !== null) {
            $spinnerFrame = $this->spinner->getCurrentFrame();
            $this->rightPanel->setTitle("$spinnerFrame Running: {$this->selectedScript}");
        }

        $this->layout->render($renderer, $x, $y, $width, $height);

        // Overlay: Status alert at top of viewport (error, success, or executing)
        if ($this->statusAlert !== null) {
            $leftWidth = (int) ((float) $width * 0.4);
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
        $input = KeyInput::from($key);

        // Priority 1: Modal (if active)
        if ($this->activeModal !== null) {
            return $this->activeModal->handleInput($key);
        }

        // Priority 2: Cancel running process with Ctrl+C (NOT quit - this is process cancellation)
        if ($this->isExecuting && $input->isCtrl(Key::C)) {
            $this->cancelExecution();
            return true;
        }

        // Priority 3: Block other input during script execution
        if ($this->isExecuting) {
            return true;
        }

        // Refresh data (Ctrl+R)
        if ($input->isCtrl(Key::R)) {
            $this->composerService->clearCache();
            $this->loadData();
            return true;
        }

        // Switch panel focus
        if ($input->is(Key::TAB)) {
            $this->focusedPanelIndex = ($this->focusedPanelIndex + 1) % 2;
            $this->updateFocus();
            return true;
        }

        // Escape from right panel
        if ($input->is(Key::ESCAPE) && $this->focusedPanelIndex === 1) {
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

        // Update spinner animation during execution
        if ($this->isExecuting && $this->runningProcess !== null) {
            $this->spinner->update();

            // Update alert with current spinner frame
            $spinnerFrame = $this->spinner->getCurrentFrame();
            $this->showAlert(Alert::info("$spinnerFrame EXECUTING..."));

            // Check process status and read output
            $this->updateProcessOutput();
        }

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

        $paddedDetails = Padding::symmetric($this->detailsDisplay, vertical: 1, horizontal: 2);
        $this->rightPanel = new Panel('Details', $paddedDetails);

        // Create grid layout
        $this->layout = new GridLayout(columns: ['30%', '70%']);
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
                        return \implode(' && ', \array_map(self::truncateCommand(...), $value));
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

        // Only update title if not currently executing
        if (!$this->isExecuting) {
            $this->rightPanel->setTitle("Details: {$script['name']}");
        }
    }

    private function runScript(string $scriptName): void
    {
        if ($this->isExecuting) {
            return;
        }

        if ($this->isDestructiveScript($scriptName)) {
            $this->activeModal = Modal::confirm(
                'Run Script',
                "The script '{$scriptName}' may make changes to your project.\n\nAre you sure you want to run it?",
            );
            $this->activeModal->setFocused(true);
            $this->activeModal->onClose(function (mixed $confirmed) use ($scriptName): void {
                $this->activeModal = null;
                if ($confirmed === true) {
                    $this->performScriptExecution($scriptName);
                }
            });
        } else {
            $this->performScriptExecution($scriptName);
        }
    }

    private function isDestructiveScript(string $scriptName): bool
    {
        $lowerName = \strtolower($scriptName);

        foreach (self::DESTRUCTIVE_SCRIPT_PATTERNS as $pattern) {
            if (\str_contains($lowerName, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function performScriptExecution(string $scriptName): void
    {
        $this->isExecuting = true;
        $this->lastProgressLine = '';

        // Start spinner
        $this->spinner->start();

        // Show executing alert with spinner
        $spinnerFrame = $this->spinner->getCurrentFrame();
        $this->showAlert(Alert::info("$spinnerFrame EXECUTING..."));

        // Switch focus to right panel to show output
        $this->focusedPanelIndex = 1;
        $this->updateFocus();

        // Create output display
        $this->detailsDisplay->setText("Executing script '$scriptName'...\n\n");
        $this->rightPanel->setTitle("Running: $scriptName");

        try {
            // Find composer binary
            $composerBinary = $this->findComposerBinary();
            if ($composerBinary === null) {
                throw new \RuntimeException('Composer binary not found');
            }

            // Create process
            $this->runningProcess = new Process(
                [$composerBinary, 'run-script', $scriptName],
                \getcwd(),
                null,
                null,
                null, // No timeout
            );

            // Start process
            $this->runningProcess->start();
            // Note: Output will be read in update() method for real-time display
        } catch (\Throwable $e) {
            $this->detailsDisplay->appendText("\n❌ EXCEPTION\n" . \str_repeat('─', 50) . "\n");
            $this->detailsDisplay->appendText($e->getMessage() . "\n");
            $this->detailsDisplay->appendText("\nPress Enter to run again, Tab to select another script.");
            $this->showAlert(Alert::error('EXCEPTION'));
            $this->isExecuting = false;
            $this->runningProcess = null;
            $this->spinner->stop();
        }
    }

    /**
     * Update process output in real-time
     */
    private function updateProcessOutput(): void
    {
        if ($this->runningProcess === null) {
            return;
        }

        // Read incremental output (non-blocking)
        $output = $this->runningProcess->getIncrementalOutput();
        $errorOutput = $this->runningProcess->getIncrementalErrorOutput();

        // Process and append output
        if ($output !== '') {
            $this->processOutput($output);
        }

        if ($errorOutput !== '') {
            $this->processOutput($errorOutput);
        }

        // Check if process finished
        if (!$this->runningProcess->isRunning()) {
            $this->handleProcessCompletion();
        }
    }

    /**
     * Process output handling carriage returns for progress bars
     */
    private function processOutput(string $output): void
    {
        // Remove ANSI escape sequences first
        $output = $this->stripAnsiCodes($output);

        // Process character by character to handle \r and \n correctly
        $buffer = '';
        $len = \strlen($output);

        for ($i = 0; $i < $len; $i++) {
            $char = $output[$i];
            if ($char === "\r") {
                // Carriage return - this line will be overwritten
                if ($buffer !== '') {
                    // If we have a previous progress line, replace it
                    if ($this->lastProgressLine !== '') {
                        $this->replaceLastLine($buffer);
                    } else {
                        // First progress line - append it
                        $this->detailsDisplay->appendText($buffer);
                    }
                    $this->lastProgressLine = $buffer;
                    $buffer = '';
                }
            } elseif ($char === "\n") {
                // Newline - finalize the line
                if ($this->lastProgressLine !== '') {
                    // We had a progress line, replace it with final content
                    if ($buffer !== '') {
                        $this->replaceLastLine($buffer . "\n");
                    } else {
                        // Just add newline to existing progress line
                        $this->detailsDisplay->appendText("\n");
                    }
                    $this->lastProgressLine = '';
                } else {
                    // Normal line
                    $this->detailsDisplay->appendText($buffer . "\n");
                }
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }

        // Handle remaining buffer
        if ($buffer !== '') {
            if ($this->lastProgressLine !== '') {
                // Update progress line
                $this->replaceLastLine($buffer);
                $this->lastProgressLine = $buffer;
            } else {
                // Append to output
                $this->detailsDisplay->appendText($buffer);
            }
        }
    }

    /**
     * Replace the last line in the display
     */
    private function replaceLastLine(string $newLine): void
    {
        $currentText = $this->detailsDisplay->getText();
        $lines = \explode("\n", $currentText);

        if (!empty($lines)) {
            // Replace the last line
            $lines[\count($lines) - 1] = $newLine;
            $this->detailsDisplay->setText(\implode("\n", $lines));
        }
    }

    /**
     * Strip ANSI escape codes from output
     */
    private function stripAnsiCodes(string $output): string
    {
        // Remove ANSI escape sequences (colors, cursor movements, etc.)
        $output = (string) \preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $output);

        // Remove ANSI OSC sequences (e.g., setting terminal title)
        $output = (string) \preg_replace('/\x1b\][^\x07]*\x07/', '', $output);

        // Remove other control sequences
        $output = (string) \preg_replace('/\x1b[><=]/', '', $output);

        return $output;
    }

    /**
     * Handle process completion
     */
    private function handleProcessCompletion(): void
    {
        if ($this->runningProcess === null) {
            return;
        }

        $exitCode = $this->runningProcess->getExitCode();

        // Display result
        $this->detailsDisplay->appendText("\n" . \str_repeat('─', 50) . "\n");

        if ($exitCode === 0) {
            $this->detailsDisplay->appendText("✅ Success (exit code: 0)\n");
            $this->showAlert(Alert::success('SUCCESS'));
        } else {
            $this->detailsDisplay->appendText("❌ Failed (exit code: {$exitCode})\n");
            $this->showAlert(Alert::error('FAILED'));
        }

        $this->detailsDisplay->appendText("\nPress Enter to run again, Tab to select another script.");

        // Cleanup
        $this->isExecuting = false;
        $this->runningProcess = null;
        $this->spinner->stop();
    }

    /**
     * Find composer binary
     */
    private function findComposerBinary(): ?string
    {
        return ComposerBinaryLocator::find();
    }

    /**
     * Cancel running process
     */
    private function cancelExecution(): void
    {
        if ($this->runningProcess === null) {
            return;
        }

        try {
            $this->runningProcess->stop(3, SIGTERM); // Give 3 seconds to gracefully stop

            $this->detailsDisplay->appendText(TextBlock::repeat('-', 50));
            $this->detailsDisplay->appendText("⚠️  Cancelled by user\n");
            $this->detailsDisplay->appendText("\nPress Enter to run again, Tab to select another script.");

            $this->showAlert(Alert::warning('CANCELLED'));
        } catch (\Throwable $e) {
            $this->detailsDisplay->appendText("\n\n❌ Error cancelling process: {$e->getMessage()}\n");
            $this->showAlert(Alert::error('ERROR'));
        } finally {
            $this->isExecuting = false;
            $this->runningProcess = null;
            $this->spinner->stop();
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
}
