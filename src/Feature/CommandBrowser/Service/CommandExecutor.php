<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\CommandBrowser\Service;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Executes Symfony Console commands and captures output
 */
final readonly class CommandExecutor
{
    public function __construct(
        private ?Application $application = null,
    ) {}

    /**
     * Execute a command and return output
     *
     * @param string $commandName Command name
     * @param array<string, mixed> $parameters Command parameters (arguments + options)
     * @return array{output: string, exitCode: int, error: string}
     */
    public function execute(string $commandName, array $parameters = []): array
    {
        if ($this->application === null) {
            return [
                'output' => '',
                'exitCode' => 1,
                'error' => 'Application not initialized',
            ];
        }

        try {
            $command = $this->application->find($commandName);

            // Prepare input
            $input = new ArrayInput(
                \array_merge(
                    ['command' => $commandName],
                    $parameters,
                ),
            );

            // Capture output
            $output = new BufferedOutput(
                OutputInterface::VERBOSITY_NORMAL,
                true, // decorated
            );

            // Execute command
            $exitCode = $command->run($input, $output);

            return [
                'output' => $output->fetch(),
                'exitCode' => $exitCode,
                'error' => '',
            ];
        } catch (\Throwable $e) {
            return [
                'output' => '',
                'exitCode' => 1,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get command help text
     */
    public function getHelp(string $commandName): string
    {
        if ($this->application === null) {
            return "Application not initialized";
        }

        try {
            $command = $this->application->find($commandName);

            $help = $command->getHelp();
            if ($help === '') {
                $help = $command->getDescription();
            }

            return $help;
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /**
     * Prepare parameters from form values
     * Converts form values to Symfony Console input format
     *
     * @param array<string, mixed> $formValues Form field values
     * @param CommandMetadata $metadata Command metadata
     * @return array<string, mixed> Prepared parameters
     */
    public function prepareParameters(array $formValues, CommandMetadata $metadata): array
    {
        $parameters = [];

        // Process arguments
        foreach ($metadata->arguments as $argument) {
            $value = $formValues[$argument->name] ?? $argument->default;

            // Skip if value is empty and not required
            if ($value === '' && !$argument->required) {
                continue;
            }

            $parameters[$argument->name] = $value;
        }

        // Process options
        foreach ($metadata->options as $option) {
            $value = $formValues['option_' . $option->name] ?? $option->default;

            // Handle checkbox (boolean) options
            if (!$option->acceptValue) {
                // This is a flag option
                if ($value === true) {
                    $parameters['--' . $option->name] = true;
                }
                continue;
            }

            // Skip if value is empty/false and not required
            if (($value === '' || $value === false) && !$option->isValueRequired) {
                continue;
            }

            // Add option with value
            if ($value !== '' && $value !== false) {
                $parameters['--' . $option->name] = $value;
            }
        }

        return $parameters;
    }
}
