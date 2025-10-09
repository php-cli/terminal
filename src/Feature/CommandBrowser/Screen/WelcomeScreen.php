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
use Butschster\Commander\UI\Component\Layout\MenuBar;
use Butschster\Commander\UI\Component\Layout\Panel;
use Butschster\Commander\UI\Component\Layout\StatusBar;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Welcome screen - Two-panel command browser
 * Left: List of available Symfony Console commands
 * Right: Command form or output when executed
 */
final class WelcomeScreen implements ScreenInterface
{
    private MenuBar $menuBar;
    private StatusBar $statusBar;

    private Panel $leftPanel;
    private Panel $rightPanel;

    private ListComponent $commandList;
    private ?FormComponent $commandForm = null;
    private ?TextDisplay $outputDisplay = null;

    private CommandDiscovery $commandDiscovery;
    private CommandExecutor $commandExecutor;

    private int $focusedPanelIndex = 0;
    private bool $isExecuting = false;
    private bool $showingForm = true;

    private ?CommandMetadata $selectedCommand = null;

    public function __construct(
        CommandDiscovery $commandDiscovery,
        CommandExecutor $commandExecutor,
    ) {
        $this->commandDiscovery = $commandDiscovery;
        $this->commandExecutor = $commandExecutor;

        $this->initializeComponents();
    }

    private function initializeComponents(): void
    {
        // Top menu
        $this->menuBar = new MenuBar([
            'F1' => 'Help',
            'F2' => 'Exec',
            'F10' => 'Quit',
        ]);

        // Bottom status bar
        $this->statusBar = new StatusBar([
            'F1' => 'Help',
            'F2' => 'Execute',
            'Tab' => 'Switch',
            'Esc' => 'Back',
            'F10' => 'Quit',
        ]);

        // Left panel - Command list
        $commands = $this->commandDiscovery->getAllCommands();
        $this->commandList = new ListComponent($commands);
        $this->commandList->setFocused(true);

        // Handle command selection change
        $this->commandList->onChange(function (?string $commandName, int $index) {
            if ($commandName !== null) {
                $this->showCommandForm($commandName);
            }
        });

        // Handle command execution (Enter key on list)
        $this->commandList->onSelect(function (string $commandName, int $index) {
            $this->showCommandForm($commandName);
            // Switch to form panel
            $this->focusedPanelIndex = 1;
            $this->commandList->setFocused(false);
            if ($this->commandForm !== null) {
                $this->commandForm->setFocused(true);
            }
        });

        $this->leftPanel = new Panel('Commands (' . count($commands) . ')', $this->commandList);

        // Right panel - Form or output
        $this->outputDisplay = new TextDisplay();
        $this->rightPanel = new Panel('Output', $this->outputDisplay);

        // Show form for first command
        if (!empty($commands)) {
            $this->showCommandForm($commands[0]);
        }
    }

    public function render(Renderer $renderer): void
    {
        $size = $renderer->getSize();
        $width = $size['width'];
        $height = $size['height'];

        // Render menu bar (top)
        $this->menuBar->render($renderer, 0, 0, $width, 1);

        // Render status bar (bottom)
        $this->statusBar->render($renderer, 0, $height - 1, $width, 1);

        // Calculate panel dimensions (40% left, 60% right)
        $panelHeight = $height - 2;
        $leftWidth = (int) ($width * 0.4);
        $rightWidth = $width - $leftWidth;

        // Update focus state
        $this->leftPanel->setFocused($this->focusedPanelIndex === 0);
        $this->rightPanel->setFocused($this->focusedPanelIndex === 1);

        // Render left panel (command list)
        $this->leftPanel->render($renderer, 0, 1, $leftWidth, $panelHeight);

        // Render right panel (form or output)
        $this->rightPanel->render($renderer, $leftWidth, 1, $rightWidth, $panelHeight);

        // Render executing indicator if running command
        if ($this->isExecuting) {
            $indicator = ' [EXECUTING...] ';
            $indicatorX = $leftWidth + (int) (($rightWidth - mb_strlen($indicator)) / 2);
            $renderer->writeAt(
                $indicatorX,
                1,
                $indicator,
                ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW, ColorScheme::BOLD),
            );
        }
    }

    public function handleInput(string $key): bool
    {
        // Don't handle input while executing
        if ($this->isExecuting) {
            return true;
        }

        // Global shortcuts
        if ($key === 'F10') {
            return false; // Let application handle quit
        }

        // F2: Execute command (works from any panel)
        if ($key === 'F2') {
            // Check if we have a selected command and a form
            if ($this->selectedCommand !== null && $this->commandForm !== null && $this->showingForm) {
                $this->executeCurrentCommand();
            } elseif ($this->selectedCommand === null) {
                // Show error if no command selected
                $this->showError('No command selected. Please select a command from the list.');
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
        } else {
            return $this->rightPanel->handleInput($key);
        }
    }

    /**
     * Show error message in right panel
     */
    private function showError(string $message): void
    {
        $this->showingForm = false;
        $this->outputDisplay = new TextDisplay();
        $this->outputDisplay->setText(
            "❌ ERROR\n" .
            str_repeat('─', 50) . "\n\n" .
            $message . "\n"
        );
        $this->rightPanel->setTitle('Error');
        $this->rightPanel->setContent($this->outputDisplay);
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
        $form->onSubmit(function (array $values) {
            $this->executeCurrentCommand();
        });

        $form->onCancel(function () {
            // Switch back to command list
            $this->focusedPanelIndex = 0;
            $this->commandList->setFocused(true);
            if ($this->commandForm !== null) {
                $this->commandForm->setFocused(false);
            }
        });

        $this->commandForm = $form;

        // Update right panel
        $this->rightPanel->setTitle('Execute: ' . $commandName);
        $this->rightPanel->setContent($form);

        // If right panel is focused, focus the form
        if ($this->focusedPanelIndex === 1) {
            $form->setFocused(true);
        }
    }

    /**
     * Execute current command
     */
    private function executeCurrentCommand(): void
    {
        if ($this->selectedCommand === null) {
            $this->showError('No command selected.');
            return;
        }

        if ($this->commandForm === null) {
            $this->showError('Command form not initialized.');
            return;
        }

        // Validate form
        $errors = $this->commandForm->validate();

        if (!empty($errors)) {
            // Show errors
            $errorText = "❌ VALIDATION ERRORS\n" . str_repeat('─', 50) . "\n\n";
            foreach ($errors as $error) {
                $errorText .= "• $error\n";
            }
            $errorText .= "\nPlease correct the errors and try again (F2).";

            $this->outputDisplay = new TextDisplay($errorText);
            $this->rightPanel->setTitle('Validation Errors');
            $this->rightPanel->setContent($this->outputDisplay);
            $this->showingForm = false;

            return;
        }

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
                if (is_bool($value)) {
                    $paramStr[] = $key;
                } elseif (is_array($value)) {
                    $paramStr[] = "$key=" . implode(',', $value);
                } else {
                    $paramStr[] = "$key=\"$value\"";
                }
            }
            $commandWithParams .= ' ' . implode(' ', $paramStr);
        }

        $this->outputDisplay->setText(
            "🚀 EXECUTING COMMAND\n" .
            str_repeat('─', 50) . "\n" .
            "$commandWithParams\n" .
            str_repeat('─', 50) . "\n\n"
        );

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
            $this->outputDisplay->appendText("\n" . str_repeat('─', 50) . "\n");

            if ($result['exitCode'] === 0) {
                $this->outputDisplay->appendText("✅ Success (exit code: 0)\n");
            } else {
                $this->outputDisplay->appendText("❌ Failed (exit code: {$result['exitCode']})\n");
            }

            if (!empty($result['error'])) {
                $this->outputDisplay->appendText("Error: {$result['error']}\n");
            }

            $this->outputDisplay->appendText("\nPress F2 to run again, Tab to select another command.");
        } catch (\Throwable $e) {
            $this->outputDisplay->appendText("\n❌ EXCEPTION\n" . str_repeat('─', 50) . "\n");
            $this->outputDisplay->appendText($e->getMessage() . "\n");
            $this->outputDisplay->appendText("\nPress F2 to run again, Tab to select another command.");
        } finally {
            $this->isExecuting = false;
        }
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
}

