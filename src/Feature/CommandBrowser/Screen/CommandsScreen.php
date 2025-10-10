<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\CommandBrowser\Screen;

use Butschster\Commander\Feature\CommandBrowser\Service\CommandDiscovery;
use Butschster\Commander\Feature\CommandBrowser\Service\CommandExecutor;
use Butschster\Commander\Feature\CommandBrowser\Service\CommandMetadata;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Decorator\Padding;
use Butschster\Commander\UI\Component\Display\ListComponent;
use Butschster\Commander\UI\Component\Display\TextDisplay;
use Butschster\Commander\UI\Component\Input\FormComponent;
use Butschster\Commander\UI\Component\Layout\Modal;
use Butschster\Commander\UI\Component\Layout\Panel;
use Butschster\Commander\UI\Component\Layout\StatusBar;
use Butschster\Commander\UI\Component\Container\GridLayout;
use Butschster\Commander\UI\Component\Container\StackLayout;
use Butschster\Commander\UI\Component\Container\Direction;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Command Browser Screen - Built with composite layout system
 *
 * Architecture:
 * ┌──────────────────────────────────────────────────┐
 * │ StackLayout (Vertical)                           │
 * │ ┌──────────────────────────────────────────────┐ │
 * │ │ GridLayout (30% / 70%)                       │ │
 * │ │ ┌─────────────┬──────────────────────────┐  │ │
 * │ │ │ Panel       │ Panel                    │  │ │
 * │ │ │ ┌─────────┐ │ ┌──────────────────────┐ │  │ │
 * │ │ │ │ List    │ │ │ Form or TextDisplay  │ │  │ │
 * │ │ │ └─────────┘ │ └──────────────────────┘ │  │ │
 * │ │ └─────────────┴──────────────────────────┘  │ │
 * │ └──────────────────────────────────────────────┘ │
 * │ ┌──────────────────────────────────────────────┐ │
 * │ │ StatusBar (Fixed height: 1)                  │ │
 * │ └──────────────────────────────────────────────┘ │
 * └──────────────────────────────────────────────────┘
 */
final class CommandsScreen implements ScreenInterface
{
    // Layout components
    private StackLayout $rootLayout;
    private GridLayout $mainArea;
    private StatusBar $statusBar;

    // Content panels
    private Panel $leftPanel;
    private Panel $rightPanel;

    // Content components
    private ListComponent $commandList;
    private ?FormComponent $commandForm = null;
    private ?TextDisplay $outputDisplay = null;

    // State
    private int $focusedPanelIndex = 0;
    private bool $isExecuting = false;
    private bool $showingForm = true;
    private ?CommandMetadata $selectedCommand = null;
    private ?Modal $activeModal = null;

    public function __construct(
        private readonly CommandDiscovery $commandDiscovery,
        private readonly CommandExecutor $commandExecutor,
    ) {
        $this->initializeComponents();
    }

    /**
     * Render the entire screen
     */
    public function render(Renderer $renderer): void
    {
        $size = $renderer->getSize();
        $width = $size['width'];
        $height = $size['height'];

        // Update panel focus states
        $this->leftPanel->setFocused($this->focusedPanelIndex === 0);
        $this->rightPanel->setFocused($this->focusedPanelIndex === 1);

        // Single render call! Layout system handles all calculations
        $this->rootLayout->render(
            $renderer,
            0,
            1,  // x, y (account for global menu bar)
            $width,
            $height - 1,  // Account for global menu bar
        );

        // Overlay: Executing indicator
        if ($this->isExecuting) {
            $this->renderExecutingIndicator($renderer, $width);
        }

        // Overlay: Modal dialog
        if ($this->activeModal !== null) {
            $this->activeModal->setFocused(true);
            $this->activeModal->render($renderer, 0, 0, $width, $height);
        }
    }

    /**
     * Handle keyboard input
     */
    public function handleInput(string $key): bool
    {
        // Priority 1: Modal (if active)
        if ($this->activeModal !== null) {
            return $this->activeModal->handleInput($key);
        }

        // Priority 2: Block input during command execution
        if ($this->isExecuting) {
            return true;
        }

        // Priority 3: Global shortcuts
        if ($this->handleGlobalShortcuts($key)) {
            return true;
        }

        // Priority 4: Panel navigation
        if ($this->handlePanelNavigation($key)) {
            return true;
        }

        // Priority 5: Delegate to focused panel
        return $this->delegateToFocusedPanel($key);
    }

    // ScreenInterface implementation

    public function onActivate(): void
    {
        // Screen activated
    }

    public function onDeactivate(): void
    {
        // Screen deactivated
    }

    public function update(): void
    {
        // Update frame (currently unused)
    }

    public function getTitle(): string
    {
        return 'Command Browser';
    }

    /**
     * Initialize all components and build layout structure
     */
    private function initializeComponents(): void
    {
        // 1. Create content components
        $commands = $this->commandDiscovery->getAllCommands();
        $this->commandList = $this->createCommandList($commands);
        $this->outputDisplay = new TextDisplay();

        // 2. Wrap content in panels (with padding for right panel)
        $this->leftPanel = new Panel('Commands (' . \count($commands) . ')', $this->commandList);

        // Right panel with padding around content
        $paddedOutput = Padding::symmetric($this->outputDisplay, vertical: 1, horizontal: 2);
        $this->rightPanel = new Panel('Output', $paddedOutput);

        // 3. Arrange panels in grid (30% / 70% split)
        $this->mainArea = new GridLayout(columns: ['30%', '70%']);
        $this->mainArea->setColumn(0, $this->leftPanel);
        $this->mainArea->setColumn(1, $this->rightPanel);

        // 4. Create status bar
        $this->statusBar = new StatusBar([]);
        $this->updateStatusBar();

        // 5. Build root layout (vertical stack)
        $this->rootLayout = new StackLayout(Direction::VERTICAL);
        $this->rootLayout->addChild($this->mainArea);  // Takes all remaining space
        $this->rootLayout->addChild($this->statusBar, size: 1);  // Fixed 1 line height

        // 6. Initialize with first command
        if (!empty($commands)) {
            $this->showCommandForm($commands[0]);
        }
    }

    /**
     * Create and configure command list component
     */
    private function createCommandList(array $commands): ListComponent
    {
        $list = new ListComponent($commands);
        $list->setFocused(true);

        // Selection change callback (arrow keys)
        $list->onChange(function (?string $commandName, int $index): void {
            if ($commandName !== null) {
                $this->showCommandForm($commandName);
            }
        });

        // Selection confirm callback (Enter key)
        $list->onSelect(function (string $commandName, int $index): void {
            $this->showCommandForm($commandName);
            $this->switchToRightPanel();
        });

        return $list;
    }

    /**
     * Render executing indicator overlay
     */
    private function renderExecutingIndicator(Renderer $renderer, int $screenWidth): void
    {
        $indicator = ' [EXECUTING...] ';
        $leftWidth = (int) ($screenWidth * 0.3);
        $rightWidth = $screenWidth - $leftWidth;
        $indicatorX = $leftWidth + (int) (($rightWidth - \mb_strlen($indicator)) / 2);

        $renderer->writeAt(
            $indicatorX,
            1,
            $indicator,
            ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW, ColorScheme::BOLD),
        );
    }

    /**
     * Handle global keyboard shortcuts
     */
    private function handleGlobalShortcuts(string $key): bool
    {
        switch ($key) {
            case 'F10':
                return false; // Let application handle quit

            case 'F1':
                $this->showHelpModal();
                return true;

            case 'CTRL_E':
                $this->handleExecuteCommand();
                return true;

            default:
                return false;
        }
    }

    /**
     * Handle panel navigation (Tab, Escape)
     */
    private function handlePanelNavigation(string $key): bool
    {
        switch ($key) {
            case 'TAB':
                $this->switchPanel();
                return true;

            case 'ESCAPE':
                if ($this->focusedPanelIndex === 1) {
                    $this->switchToLeftPanel();
                    return true;
                }
                return false;

            default:
                return false;
        }
    }

    /**
     * Delegate input to currently focused panel
     */
    private function delegateToFocusedPanel(string $key): bool
    {
        $panel = $this->focusedPanelIndex === 0 ? $this->leftPanel : $this->rightPanel;
        return $panel->handleInput($key);
    }

    /**
     * Handle Ctrl+E key (Execute command)
     */
    private function handleExecuteCommand(): void
    {
        if ($this->selectedCommand === null) {
            $this->showErrorModal('No command selected. Please select a command from the list first.');
            return;
        }

        if ($this->showingForm && $this->commandForm !== null) {
            // Execute from form
            $this->executeCurrentCommand();
            $this->switchToRightPanel();
        } elseif (!$this->showingForm) {
            // Switch back to form to run again
            $this->showCommandForm($this->selectedCommand->name);
            $this->switchToRightPanel();
        }
    }

    /**
     * Switch focus to left panel
     */
    private function switchToLeftPanel(): void
    {
        $this->focusedPanelIndex = 0;
        $this->commandList->setFocused(true);

        if ($this->commandForm !== null) {
            $this->commandForm->setFocused(false);
        }
        if ($this->outputDisplay !== null) {
            $this->outputDisplay->setFocused(false);
        }

        $this->updateStatusBar();
    }

    /**
     * Switch focus to right panel
     */
    private function switchToRightPanel(): void
    {
        $this->focusedPanelIndex = 1;
        $this->commandList->setFocused(false);

        if ($this->showingForm && $this->commandForm !== null) {
            $this->commandForm->setFocused(true);
        } elseif (!$this->showingForm && $this->outputDisplay !== null) {
            $this->outputDisplay->setFocused(true);
        }

        $this->updateStatusBar();
    }

    /**
     * Toggle focus between left and right panels
     */
    private function switchPanel(): void
    {
        if ($this->focusedPanelIndex === 0) {
            $this->switchToRightPanel();
        } else {
            $this->switchToLeftPanel();
        }
    }

    /**
     * Update status bar hints based on current context
     */
    private function updateStatusBar(): void
    {
        $hints = match (true) {
            $this->focusedPanelIndex === 0 => $this->getLeftPanelHints(),
            $this->showingForm => $this->getFormPanelHints(),
            default => $this->getOutputPanelHints(),
        };

        $this->statusBar->setHints($hints);
    }

    /**
     * Get status hints for left panel (command list)
     */
    private function getLeftPanelHints(): array
    {
        return [
            '↑↓' => ' Navigate',
            'Enter' => ' Select',
            'Tab' => ' Switch',
            'Ctrl+E' => ' Execute',
        ];
    }

    /**
     * Get status hints for form panel
     */
    private function getFormPanelHints(): array
    {
        return [
            '↑↓' => ' Fields',
            'Tab' => ' Switch',
            'Ctrl+E' => ' Execute',
            'ESC' => ' Cancel',
        ];
    }

    /**
     * Get status hints for output panel
     */
    private function getOutputPanelHints(): array
    {
        return [
            '↑↓' => ' Scroll',
            'PgUp/Dn' => ' Page',
            'Tab' => ' Switch',
            'Ctrl+E' => ' Run Again',
            'ESC' => ' Back',
        ];
    }

    /**
     * Show command form in right panel
     */
    private function showCommandForm(string $commandName): void
    {
        $metadata = $this->commandDiscovery->getCommandMetadata($commandName);

        if ($metadata === null) {
            return;
        }

        $this->selectedCommand = $metadata;
        $this->showingForm = true;

        // Create and configure form
        $form = $this->buildCommandForm($metadata);

        // Update right panel with padding
        $this->commandForm = $form;
        $paddedForm = Padding::symmetric($form, horizontal: 2, vertical: 1);
        $this->rightPanel->setTitle('Execute: ' . $commandName);
        $this->rightPanel->setContent($paddedForm);

        // Focus form if right panel is focused
        if ($this->focusedPanelIndex === 1) {
            $form->setFocused(true);
        }

        $this->updateStatusBar();
    }

    /**
     * Build form for command metadata
     */
    private function buildCommandForm(CommandMetadata $metadata): FormComponent
    {
        $form = new FormComponent();

        // Add argument fields
        foreach ($metadata->arguments as $argument) {
            if ($argument->isArray) {
                $form->addArrayField(
                    $argument->name,
                    $argument->name,
                    $argument->required,
                    $argument->description,
                );
            } else {
                $form->addTextField(
                    $argument->name,
                    $argument->name,
                    $argument->required,
                    $argument->default,
                    $argument->description,
                );
            }
        }

        // Add option fields
        foreach ($metadata->options as $option) {
            $label = '--' . $option->name;
            if ($option->shortcut !== null) {
                $label .= ' (-' . $option->shortcut . ')';
            }

            if (!$option->acceptValue) {
                // Boolean flag
                $form->addCheckboxField(
                    'option_' . $option->name,
                    $label,
                    (bool) $option->default,
                    $option->description,
                );
            } elseif ($option->isArray) {
                // Array option
                $form->addArrayField(
                    'option_' . $option->name,
                    $label,
                    $option->isValueRequired,
                    $option->description,
                );
            } else {
                // Regular value option
                $form->addTextField(
                    'option_' . $option->name,
                    $label,
                    $option->isValueRequired,
                    $option->default,
                    $option->description,
                );
            }
        }

        // Set form callbacks
        $form->onSubmit(fn() => $this->executeCurrentCommand());
        $form->onCancel(fn() => $this->switchToLeftPanel());

        return $form;
    }

    /**
     * Execute the currently selected command
     */
    private function executeCurrentCommand(): void
    {
        if ($this->selectedCommand === null || $this->commandForm === null) {
            $this->showErrorModal('Command not properly initialized.');
            return;
        }

        // Validate form
        $errors = $this->commandForm->validate();
        if (!empty($errors)) {
            $this->showValidationErrorModal($errors);
            return;
        }

        // Check for dangerous commands
        if ($this->isDangerousCommand($this->selectedCommand->name)) {
            $this->showConfirmationModal(
                'Confirm Execution',
                "You are about to execute '{$this->selectedCommand->name}'.\n\n" .
                "This command may perform destructive operations.\n\n" .
                "Are you sure you want to continue?",
                fn() => $this->performCommandExecution(),
            );
            return;
        }

        // Execute directly
        $this->performCommandExecution();
    }

    /**
     * Check if command is potentially dangerous
     */
    private function isDangerousCommand(string $commandName): bool
    {
        $dangerousPatterns = [
            'delete', 'remove', 'drop', 'truncate', 'clear', 'purge', 'destroy',
            'cache:clear', 'migrate:reset', 'migrate:fresh', 'db:wipe',
        ];

        $lowerCommand = \strtolower($commandName);

        foreach ($dangerousPatterns as $pattern) {
            if (\str_contains($lowerCommand, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Perform actual command execution
     */
    private function performCommandExecution(): void
    {
        $this->isExecuting = true;
        $this->showingForm = false;

        // Prepare parameters
        $formValues = $this->commandForm->getValues();
        $parameters = $this->commandExecutor->prepareParameters($formValues, $this->selectedCommand);

        // Create output display with padding
        $this->outputDisplay = new TextDisplay();
        $paddedOutput = Padding::symmetric($this->outputDisplay, horizontal: 2, vertical: 1);
        $this->rightPanel->setTitle("Output: {$this->selectedCommand->name}");
        $this->rightPanel->setContent($paddedOutput);

        try {
            // Execute command
            $result = $this->commandExecutor->execute($this->selectedCommand->name, $parameters);

            // Display output
            $this->displayCommandOutput($result);
        } catch (\Throwable $e) {
            $this->displayCommandException($e);
        } finally {
            $this->isExecuting = false;
            $this->updateStatusBar();
        }
    }

    /**
     * Display command execution output
     */
    private function displayCommandOutput(array $result): void
    {
        // Show command output
        if (!empty($result['output'])) {
            $this->outputDisplay->appendText($result['output'] . "\n");
        } else {
            $this->outputDisplay->appendText("[No output]\n");
        }

        // Show separator
        $this->outputDisplay->appendText("\n" . \str_repeat('─', 50) . "\n");

        // Show result
        if ($result['exitCode'] === 0) {
            $this->outputDisplay->appendText("✅ Success (exit code: 0)\n");
        } else {
            $this->outputDisplay->appendText("❌ Failed (exit code: {$result['exitCode']})\n");

            if (!empty($result['error'])) {
                $errorMessage = "Command failed with exit code {$result['exitCode']}.\n\n{$result['error']}";
                $this->showErrorModal($errorMessage);
            }
        }

        if (!empty($result['error']) && $result['exitCode'] === 0) {
            $this->outputDisplay->appendText("⚠️  Warning: {$result['error']}\n");
        }

        $this->outputDisplay->appendText("\nPress Ctrl+E to run again, Tab to select another command.");
    }

    /**
     * Display command exception
     */
    private function displayCommandException(\Throwable $e): void
    {
        $this->outputDisplay->appendText("\n❌ EXCEPTION\n" . \str_repeat('─', 50) . "\n");
        $this->outputDisplay->appendText($e->getMessage() . "\n");
        $this->outputDisplay->appendText("\nPress Ctrl+E to run again, Tab to select another command.");

        $this->showExecutionErrorModal('An exception occurred while executing the command.', $e);
    }

    // Modal display methods

    private function showHelpModal(): void
    {
        $helpText = <<<'HELP'
            Command Browser - Keyboard Shortcuts

            Navigation:
              ↑/↓         Navigate through command list
              Tab         Switch between panels
              Enter       Select command and edit parameters
              Escape      Go back to command list

            Execution:
              Ctrl+E      Execute selected command

            List Navigation:
              Page Up     Scroll up one page
              Page Down   Scroll down one page
              Home        Jump to first command
              End         Jump to last command

            Form Navigation:
              ↑/↓         Navigate between fields
              Tab         Move to next field
              ←/→         Move cursor in text fields
              Backspace   Delete character before cursor
              Delete      Delete character at cursor

            Output View:
              ↑/↓         Scroll output
              Page Up/Down Scroll output by page
              Home/End    Jump to start/end of output

            General:
              F1          Show this help
              F2          Commands screen
              F3          Files screen
              F5          Composer screen
              F10         Exit application
            HELP;

        $this->activeModal = Modal::info('Help', $helpText);
        $this->activeModal->setSize(70, 32);
        $this->activeModal->onClose(fn() => $this->activeModal = null);
    }

    private function showErrorModal(string $message): void
    {
        $this->activeModal = Modal::error('Error', $message);
        $this->activeModal->onClose(fn() => $this->activeModal = null);
    }

    private function showValidationErrorModal(array $errors): void
    {
        $message = "Please fix the following errors:\n\n";
        foreach ($errors as $error) {
            $message .= "• $error\n";
        }

        $this->activeModal = Modal::error('Validation Errors', $message);
        $this->activeModal->onClose(fn() => $this->activeModal = null);
    }

    private function showExecutionErrorModal(string $message, ?\Throwable $exception = null): void
    {
        $fullMessage = $message;

        if ($exception !== null) {
            $fullMessage .= "\n\n" . $exception::class . ":\n" . $exception->getMessage();

            if ($exception->getFile()) {
                $fullMessage .= "\n\nFile: {$exception->getFile()}:{$exception->getLine()}";
            }
        }

        $this->activeModal = Modal::error('Execution Error', $fullMessage);
        $this->activeModal->setSize(80, 20);
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
}
