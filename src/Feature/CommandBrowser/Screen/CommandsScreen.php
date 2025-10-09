<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\CommandBrowser\Screen;

use Butschster\Commander\Feature\CommandBrowser\Service\CommandDiscovery;
use Butschster\Commander\Feature\CommandBrowser\Service\CommandExecutor;
use Butschster\Commander\Feature\CommandBrowser\Service\CommandMetadata;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
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
 * Welcome screen - Two-panel command browser
 * Left: List of available Symfony Console commands
 * Right: Command form or output when executed
 */
final class CommandsScreen implements ScreenInterface
{
    private StatusBar $statusBar;
    private StackLayout $rootLayout;
    private Panel $leftPanel;
    private Panel $rightPanel;
    private ListComponent $commandList;
    private ?FormComponent $commandForm = null;
    private ?TextDisplay $outputDisplay = null;
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

    public function render(Renderer $renderer): void
    {
        $size = $renderer->getSize();
        $width = $size['width'];
        $height = $size['height'];

        // Update focus state
        $this->leftPanel->setFocused($this->focusedPanelIndex === 0);
        $this->rightPanel->setFocused($this->focusedPanelIndex === 1);

        // NEW: Single render call! Layout system handles all positioning
        $this->rootLayout->render(
            $renderer,
            0,
            1,  // x, y (account for global menu bar at top)
            $width,
            $height - 1,  // Account for global menu bar
        );

        // Render executing indicator if running command
        if ($this->isExecuting) {
            $indicator = ' [EXECUTING...] ';
            $leftWidth = (int) ($width * 0.3);
            $rightWidth = $width - $leftWidth;
            $indicatorX = $leftWidth + (int) (($rightWidth - \mb_strlen($indicator)) / 2);
            $renderer->writeAt(
                $indicatorX,
                1,
                $indicator,
                ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW, ColorScheme::BOLD),
            );
        }

        // Render modal on top if active
        if ($this->activeModal !== null) {
            $this->activeModal->setFocused(true);
            $this->activeModal->render($renderer, 0, 0, $width, $height);
        }
    }

    public function handleInput(string $key): bool
    {
        // Modal has priority for input
        if ($this->activeModal !== null) {
            return $this->activeModal->handleInput($key);
        }

        // Don't handle input while executing
        if ($this->isExecuting) {
            return true;
        }

        // Global shortcuts
        if ($key === 'F10') {
            return false; // Let application handle quit
        }

        // F1: Show help
        if ($key === 'F1') {
            $this->showHelpModal();
            return true;
        }

        // F2: Execute command (works from any panel)
        if ($key === 'F4') {
            // Check if we have a selected command and a form
            if ($this->selectedCommand !== null && $this->commandForm !== null && $this->showingForm) {
                $this->executeCurrentCommand();
                // Switch focus to right panel to view output
                $this->focusedPanelIndex = 1;
                $this->commandList->setFocused(false);
                if ($this->outputDisplay !== null) {
                    $this->outputDisplay->setFocused(true);
                }
                if ($this->commandForm !== null) {
                    $this->commandForm->setFocused(false);
                }
            } elseif ($this->selectedCommand === null) {
                // Show error modal if no command selected
                $this->showErrorModal('No command selected. Please select a command from the list first.');
            } elseif (!$this->showingForm) {
                // If showing output, F2 switches back to form
                $this->showCommandForm($this->selectedCommand->name);
                $this->focusedPanelIndex = 1;
                $this->commandList->setFocused(false);
                $this->commandForm?->setFocused(true);
            }
            return true;
        }

        // Tab to switch panels
        if ($key === 'TAB') {
            $this->switchPanel();
            return true;
        }

        // Escape: Go back to command list if in right panel
        if ($key === 'ESCAPE') {
            if ($this->focusedPanelIndex === 1) {
                $this->focusedPanelIndex = 0;
                $this->commandList->setFocused(true);
                if ($this->commandForm !== null) {
                    $this->commandForm->setFocused(false);
                }
                if ($this->outputDisplay !== null) {
                    $this->outputDisplay->setFocused(false);
                }
                return true;
            }
        }

        // Delegate to focused panel
        if ($this->focusedPanelIndex === 0) {
            return $this->leftPanel->handleInput($key);
        }
        return $this->rightPanel->handleInput($key);

    }

    public function onActivate(): void
    {
        // Nothing to do
    }

    public function onDeactivate(): void
    {
        // Nothing to do
    }

    public function update(): void
    {
        // Nothing to update
    }

    public function getTitle(): string
    {
        return 'Command Browser';
    }

    private function initializeComponents(): void
    {
        // Bottom status bar - will be updated dynamically based on context
        $this->statusBar = new StatusBar([]);
        $this->updateStatusBar();

        // Left panel - Command list
        $commands = $this->commandDiscovery->getAllCommands();
        $this->commandList = new ListComponent($commands);
        $this->commandList->setFocused(true);

        // Handle command selection change
        $this->commandList->onChange(function (?string $commandName, int $index): void {
            if ($commandName !== null) {
                $this->showCommandForm($commandName);
            }
        });

        // Handle command execution (Enter key on list)
        $this->commandList->onSelect(function (string $commandName, int $index): void {
            $this->showCommandForm($commandName);
            // Switch to form panel
            $this->focusedPanelIndex = 1;
            $this->commandList->setFocused(false);
            if ($this->commandForm !== null) {
                $this->commandForm->setFocused(true);
            }
            $this->updateStatusBar();
        });

        $this->leftPanel = new Panel('Commands (' . \count($commands) . ')', $this->commandList);

        // Right panel - Form or output
        $this->outputDisplay = new TextDisplay();
        $this->rightPanel = new Panel('Output', $this->outputDisplay);

        // Show form for first command
        if (!empty($commands)) {
            $this->showCommandForm($commands[0]);
        }

        // NEW: Build layout structure using composition
        $mainArea = new GridLayout(columns: ['30%', '70%']);
        $mainArea->setColumn(0, $this->leftPanel);
        $mainArea->setColumn(1, $this->rightPanel);

        // Root layout: vertical stack (content + status bar)
        $this->rootLayout = new StackLayout(Direction::VERTICAL);
        $this->rootLayout->addChild($mainArea);  // Takes remaining space
        $this->rootLayout->addChild($this->statusBar, size: 1);
    }

    /**
     * Update status bar based on current screen state
     */
    private function updateStatusBar(): void
    {
        $hints = [];

        if ($this->focusedPanelIndex === 0) {
            // Left panel (command list) is focused
            $hints = [
                '↑↓' => ' Navigate',
                'Enter' => ' Select',
                'Tab' => ' Switch',
                'F4' => ' Execute',
            ];
        } elseif ($this->showingForm && $this->commandForm !== null) {
            // Right panel with form is focused
            $hints = [
                '↑↓' => ' Fields',
                'Tab' => ' Switch',
                'F4' => ' Execute',
                'ESC' => ' Cancel',
            ];
        } else {
            // Right panel with output is focused
            $hints = [
                '↑↓' => ' Scroll',
                'PgUp/Dn' => ' Page',
                'Tab' => ' Switch',
                'F4' => ' Run Again',
                'ESC' => ' Back',
            ];
        }

        $this->statusBar->setHints($hints);
    }

    /**
     * Show success modal
     */
    private function showSuccessModal(string $message): void
    {
        $this->activeModal = Modal::info('Success', $message);
        $this->activeModal->onClose(function (): void {
            $this->activeModal = null;
        });
    }

    /**
     * Show help modal
     */
    private function showHelpModal(): void
    {
        $helpText = <<<HELP
            Command Browser - Keyboard Shortcuts
            
            Navigation:
              ↑/↓         Navigate through command list
              Tab         Switch between panels
              Enter       Select command and edit parameters
              Escape      Go back to command list
            
            Execution:
              F4          Execute selected command
            
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
              F10         Exit application
            HELP;

        $this->activeModal = Modal::info('Help', $helpText);
        $this->activeModal->setSize(70, 30);
        $this->activeModal->onClose(function (): void {
            $this->activeModal = null;
        });
    }

    /**
     * Show error message in a modal
     */
    private function showErrorModal(string $message): void
    {
        $this->activeModal = Modal::error('Error', $message);
        $this->activeModal->onClose(function (): void {
            $this->activeModal = null;
        });
    }

    /**
     * Show validation errors in a modal
     */
    private function showValidationErrorModal(array $errors): void
    {
        $message = "Please fix the following errors:\n\n";
        foreach ($errors as $error) {
            $message .= "• $error\n";
        }

        $this->activeModal = Modal::error('Validation Errors', $message);
        $this->activeModal->onClose(function (): void {
            $this->activeModal = null;
        });
    }

    /**
     * Show execution error in a modal
     */
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
        $this->activeModal->setSize(80, 20); // Larger modal for exception details
        $this->activeModal->onClose(function (): void {
            $this->activeModal = null;
        });
    }

    /**
     * Show error message in right panel (legacy method - now uses modal)
     * @deprecated Use showErrorModal() instead
     */
    private function showError(string $message): void
    {
        $this->showErrorModal($message);
    }

    /**
     * Switch focus between panels
     */
    private function switchPanel(): void
    {
        $this->focusedPanelIndex = ($this->focusedPanelIndex + 1) % 2;

        $this->commandList->setFocused($this->focusedPanelIndex === 0);

        if ($this->showingForm && $this->commandForm !== null) {
            $this->commandForm->setFocused($this->focusedPanelIndex === 1);
        } elseif (!$this->showingForm && $this->outputDisplay !== null) {
            $this->outputDisplay->setFocused($this->focusedPanelIndex === 1);
        }

        // Update status bar to reflect new context
        $this->updateStatusBar();
    }

    /**
     * Show command form
     */
    private function showCommandForm(string $commandName): void
    {
        $metadata = $this->commandDiscovery->getCommandMetadata($commandName);

        if ($metadata === null) {
            return;
        }

        $this->selectedCommand = $metadata;
        $this->showingForm = true;

        // Create form
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
            // Build option label
            $label = '--' . $option->name;
            if ($option->shortcut !== null) {
                $label .= ' (-' . $option->shortcut . ')';
            }

            if (!$option->acceptValue) {
                // Boolean flag option - use checkbox
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
        $form->onSubmit(function (array $values): void {
            $this->executeCurrentCommand();
        });

        $form->onCancel(function (): void {
            // Switch back to command list
            $this->focusedPanelIndex = 0;
            $this->commandList->setFocused(true);
            if ($this->commandForm !== null) {
                $this->commandForm->setFocused(false);
            }
            $this->updateStatusBar();
        });

        $this->commandForm = $form;

        // Update right panel
        $this->rightPanel->setTitle('Execute: ' . $commandName);
        $this->rightPanel->setContent($form);

        // If right panel is focused, focus the form
        if ($this->focusedPanelIndex === 1) {
            $form->setFocused(true);
        }

        // Update status bar to reflect form context
        $this->updateStatusBar();
    }

    /**
     * Execute current command
     */
    private function executeCurrentCommand(): void
    {
        if ($this->selectedCommand === null) {
            $this->showErrorModal('No command selected.');
            return;
        }

        if ($this->commandForm === null) {
            $this->showErrorModal('Command form not initialized.');
            return;
        }

        // Validate form
        $errors = $this->commandForm->validate();

        if (!empty($errors)) {
            // Show validation errors in modal
            $this->showValidationErrorModal($errors);
            return;
        }

        // Check if command is potentially dangerous and needs confirmation
        if ($this->isDangerousCommand($this->selectedCommand->name)) {
            $this->showConfirmationModal(
                'Confirm Execution',
                "You are about to execute '{$this->selectedCommand->name}'.\n\n" .
                "This command may perform destructive operations.\n\n" .
                "Are you sure you want to continue?",
                function (): void {
                    $this->performCommandExecution();
                },
            );
            return;
        }

        // Execute directly if not dangerous
        $this->performCommandExecution();
    }

    /**
     * Check if command is potentially dangerous
     */
    private function isDangerousCommand(string $commandName): bool
    {
        $dangerousPatterns = [
            'delete',
            'remove',
            'drop',
            'truncate',
            'clear',
            'purge',
            'destroy',
            'cache:clear',
            'migrate:reset',
            'migrate:fresh',
            'db:wipe',
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
     * Show confirmation modal
     */
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
     * Perform the actual command execution
     */
    private function performCommandExecution(): void
    {
        $this->isExecuting = true;
        $this->showingForm = false;

        // Get form values
        $formValues = $this->commandForm->getValues();

        // Prepare parameters
        $parameters = $this->commandExecutor->prepareParameters($formValues, $this->selectedCommand);

        // Create output display
        $this->outputDisplay = new TextDisplay();
        $commandWithParams = $this->selectedCommand->name;
        if (!empty($parameters)) {
            $paramStr = [];
            foreach ($parameters as $key => $value) {
                if (\is_bool($value)) {
                    $paramStr[] = $key;
                } elseif (\is_array($value)) {
                    $paramStr[] = "$key=" . \implode(',', $value);
                } else {
                    $paramStr[] = "$key=\"$value\"";
                }
            }
            $commandWithParams .= ' ' . \implode(' ', $paramStr);
        }

        $this->rightPanel->setTitle("Output: {$this->selectedCommand->name}");
        $this->rightPanel->setContent($this->outputDisplay);

        try {
            // Execute command
            $result = $this->commandExecutor->execute($this->selectedCommand->name, $parameters);

            // Show output
            if (!empty($result['output'])) {
                $this->outputDisplay->appendText($result['output'] . "\n");
            } else {
                $this->outputDisplay->appendText("[No output]\n");
            }

            // Show exit code
            $this->outputDisplay->appendText("\n" . \str_repeat('─', 50) . "\n");

            if ($result['exitCode'] === 0) {
                $this->outputDisplay->appendText("✅ Success (exit code: 0)\n");
            } else {
                $this->outputDisplay->appendText("❌ Failed (exit code: {$result['exitCode']})\n");

                // Show error modal for failed commands
                $errorMessage = "Command execution failed with exit code {$result['exitCode']}.";
                if (!empty($result['error'])) {
                    $errorMessage .= "\n\n" . $result['error'];
                }
                $this->showErrorModal($errorMessage);
            }

            if (!empty($result['error']) && $result['exitCode'] === 0) {
                $this->outputDisplay->appendText("Error: {$result['error']}\n");
            }

            $this->outputDisplay->appendText("\nPress F2 to run again, Tab to select another command.");
        } catch (\Throwable $e) {
            $this->outputDisplay->appendText("\n❌ EXCEPTION\n" . \str_repeat('─', 50) . "\n");
            $this->outputDisplay->appendText($e->getMessage() . "\n");
            $this->outputDisplay->appendText("\nPress F2 to run again, Tab to select another command.");

            // Show exception modal
            $this->showExecutionErrorModal('An exception occurred while executing the command.', $e);
        } finally {
            $this->isExecuting = false;
            // Update status bar to show output view hints
            $this->updateStatusBar();
        }
    }
}
