# Command Browser Module

The Command Browser module provides a terminal UI for browsing and executing Symfony Console commands.

## Features

- List all available Symfony Console commands
- Dynamic form generation for command arguments and options
- Real-time command output display
- Dangerous command confirmation dialogs
- Form validation for required parameters
- Scrollable output viewer
- Help modal with keyboard shortcuts

## Installation

The module is included in Commander by default. To use it with the Module SDK:

```php
use Butschster\Commander\SDK\Builder\ApplicationBuilder;
use Butschster\Commander\Module\CommandBrowser\CommandBrowserModule;
use Symfony\Component\Console\Application as SymfonyApplication;

$symfonyApp = new SymfonyApplication('My App', '1.0.0');
// Add your commands...

$app = ApplicationBuilder::create()
    ->withModule(new CommandBrowserModule($symfonyApp))
    ->withInitialScreen('command_browser')
    ->build();

$app->run();
```

## Screens

### Command Browser Screen (`command_browser`)

Two-panel layout:

- **Left Panel (30%)**: Scrollable command list
- **Right Panel (70%)**: Command form or execution output

#### Command Form

- Text fields for arguments
- Checkbox fields for boolean options
- Array fields for multi-value inputs
- Descriptions and validation

#### Output Display

- Real-time command output
- Success/failure indicators
- Exit code display
- Scrollable output history

## Keyboard Shortcuts

### Navigation

| Key       | Action                               |
|-----------|--------------------------------------|
| `↑` / `↓` | Navigate command list or form fields |
| `Tab`     | Switch between panels                |
| `Enter`   | Select command / Move to form        |
| `Escape`  | Return to command list               |
| `F1`      | Show help                            |

### Execution

| Key      | Action                   |
|----------|--------------------------|
| `Ctrl+E` | Execute selected command |

### List Navigation

| Key                     | Action                     |
|-------------------------|----------------------------|
| `Page Up` / `Page Down` | Scroll list by page        |
| `Home` / `End`          | Jump to first/last command |

### Output View

| Key                     | Action            |
|-------------------------|-------------------|
| `↑` / `↓`               | Scroll output     |
| `Page Up` / `Page Down` | Scroll by page    |
| `Ctrl+E`                | Run command again |

## Menu

The module adds a "Commands" menu accessible via `F2`:

- **Command Browser** (`c`) - Open the command browser

## Services

### CommandDiscovery

Discovers and provides metadata for all registered commands:

```php
use Butschster\Commander\Module\CommandBrowser\Service\CommandDiscovery;

$discovery = new CommandDiscovery($symfonyApp);

// Get all command names
$commands = $discovery->getAllCommands();
// Returns: ['list', 'help', 'app:deploy', ...]

// Get command metadata
$metadata = $discovery->getCommandMetadata('app:deploy');
// Returns CommandMetadata with arguments, options, description
```

### CommandExecutor

Executes commands and captures output:

```php
use Butschster\Commander\Module\CommandBrowser\Service\CommandExecutor;

$executor = new CommandExecutor($symfonyApp);

// Prepare parameters from form values
$params = $executor->prepareParameters($formValues, $commandMetadata);

// Execute command
$result = $executor->execute('app:deploy', [
    'environment' => 'production',
    '--force' => true,
]);

// Result structure:
// [
//     'output' => 'Deploying to production...',
//     'error' => '',
//     'exitCode' => 0,
// ]
```

## Data Types

### CommandMetadata

Contains command information:

```php
readonly class CommandMetadata {
    public string $name;
    public string $description;
    public string $help;
    /** @var ArgumentMetadata[] */
    public array $arguments;
    /** @var OptionMetadata[] */
    public array $options;
}
```

### ArgumentMetadata

Describes a command argument:

```php
readonly class ArgumentMetadata {
    public string $name;
    public string $description;
    public bool $required;
    public bool $isArray;
    public mixed $default;
}
```

### OptionMetadata

Describes a command option:

```php
readonly class OptionMetadata {
    public string $name;
    public ?string $shortcut;
    public string $description;
    public bool $acceptValue;
    public bool $isValueRequired;
    public bool $isArray;
    public mixed $default;
}
```

## Safety Features

### Dangerous Command Detection

The module automatically detects potentially dangerous commands and shows a confirmation dialog before execution:

Detected patterns:

- `delete`, `remove`, `drop`, `truncate`
- `clear`, `purge`, `destroy`
- `cache:clear`, `migrate:reset`, `migrate:fresh`, `db:wipe`

### Form Validation

Required arguments and options are validated before execution:

- Empty required fields show validation errors
- Modal dialog displays all validation issues

## Configuration

The module requires a Symfony Console Application instance:

```php
use Symfony\Component\Console\Application;

$symfonyApp = new Application('My App', '1.0.0');
$symfonyApp->add(new MyCommand());
$symfonyApp->add(new AnotherCommand());

new CommandBrowserModule($symfonyApp)
```

## Example: Adding Custom Commands

```php
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class DeployCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('app:deploy')
            ->setDescription('Deploy application to environment')
            ->addArgument('environment', InputArgument::REQUIRED, 'Target environment')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force deployment')
            ->addOption('tag', 't', InputOption::VALUE_REQUIRED, 'Git tag to deploy');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $env = $input->getArgument('environment');
        $output->writeln("Deploying to {$env}...");
        return Command::SUCCESS;
    }
}

// Register command
$symfonyApp->add(new DeployCommand());
```

The command browser will automatically generate a form with:

- Required text field for "environment"
- Checkbox for "--force"
- Optional text field for "--tag"
