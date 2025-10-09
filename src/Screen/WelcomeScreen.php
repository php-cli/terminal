<?php

declare(strict_types=1);

namespace Butschster\Commander\Screen;

use Butschster\Commander\Component\FormComponent;
use Butschster\Commander\Component\ListComponent;
use Butschster\Commander\Component\MenuBar;
use Butschster\Commander\Component\Panel;
use Butschster\Commander\Component\StatusBar;
use Butschster\Commander\Component\TextDisplay;
use Butschster\Commander\Service\CommandDiscovery;
use Butschster\Commander\Service\CommandExecutor;
use Butschster\Commander\Service\CommandMetadata;
use Butschster\Commander\Service\Renderer;
use Butschster\Commander\Theme\ColorScheme;

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
        CommandExecutor $commandExecutor
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
            'Tab' => 'Switch Panel',
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
        $leftWidth = (int)($width * 0.4);
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
            $indicatorX = $leftWidth + (int)(($rightWidth - mb_strlen($indicator)) / 2);
            $renderer->writeAt(
                $indicatorX,
                1,
                $indicator,
                ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW, ColorScheme::BOLD)
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

        if ($key === 'F2') {
            // Execute command if form is focused
            if ($this->focusedPanelIndex === 1 && $this->commandForm !== null) {
                $this->executeCurrentCommand();
            }
            return true;
        }

        // Tab to switch panels
        if ($key === 'TAB') {
            $this->switchPanel();
            return true;
        }

        // Delegate to focused panel
        if ($this->focusedPanelIndex === 0) {
            return $this->leftPanel->handleInput($key);
        } else {
            return $this->rightPanel->handleInput($key);
        }
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
                    $argument->name . ' (' . $argument->description . ')',
                    $argument->required
                );
            } else {
                $form->addTextField(
                    $argument->name,
                    $argument->name . ' (' . $argument->description . ')',
                    $argument->required,
                    $argument->default
                );
            }
        }
        
        // Add option fields
        foreach ($metadata->options as $option) {
            $label = '--' . $option->name;
            if ($option->shortcut !== null) {
                $label .= ' (-' . $option->shortcut . ')';
            }
            $label .= ': ' . $option->description;
            
            if (!$option->acceptValue) {
                // Boolean flag option - use checkbox
                $form->addCheckboxField(
                    'option_' . $option->name,
                    $label,
                    (bool)$option->default
                );
            } elseif ($option->isArray) {
                // Array option
                $form->addArrayField(
                    'option_' . $option->name,
                    $label,
                    $option->isValueRequired
                );
            } else {
                // Regular value option
                $form->addTextField(
                    'option_' . $option->name,
                    $label,
                    $option->isValueRequired,
                    $option->default
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
        if ($this->selectedCommand === null || $this->commandForm === null) {
            return;
        }
        
        // Validate form
        $errors = $this->commandForm->validate();
        
        if (!empty($errors)) {
            // Show errors
            $errorText = "Validation errors:\n\n";
            foreach ($errors as $error) {
                $errorText .= "• $error\n";
            }
            
            $this->outputDisplay = new TextDisplay($errorText);
            $this->rightPanel->setTitle('Errors');
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
        $this->outputDisplay->setText("Executing: {$this->selectedCommand->name}\n" . str_repeat('─', 50) . "\n\n");
        
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
            $this->outputDisplay->appendText("Exit code: {$result['exitCode']}\n");
            
            if (!empty($result['error'])) {
                $this->outputDisplay->appendText("Error: {$result['error']}\n");
            }
            
        } catch (\Throwable $e) {
            $this->outputDisplay->appendText("\n[ERROR] " . $e->getMessage() . "\n");
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

