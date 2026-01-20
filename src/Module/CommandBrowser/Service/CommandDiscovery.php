<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\CommandBrowser\Service;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

/**
 * Discovers available Symfony Console commands
 */
final class CommandDiscovery
{
    /** @var array<string, CommandMetadata> Cached command metadata */
    private ?array $commandMetadata = null;

    public function __construct(
        private readonly ?Application $application = null,
    ) {}

    /**
     * Get all available command names
     *
     * @return array<string>
     */
    public function getAllCommands(): array
    {
        $metadata = $this->getAllCommandMetadata();
        return \array_keys($metadata);
    }

    /**
     * Get all command metadata
     *
     * @return array<string, CommandMetadata>
     */
    public function getAllCommandMetadata(): array
    {
        if ($this->commandMetadata !== null) {
            return $this->commandMetadata;
        }

        $this->commandMetadata = [];

        if ($this->application === null) {
            return $this->commandMetadata;
        }

        foreach ($this->application->all() as $command) {
            if ($command->getName() === null || $command->isHidden()) {
                continue;
            }

            $this->commandMetadata[$command->getName()] = $this->extractMetadata($command);
        }

        \ksort($this->commandMetadata);

        return $this->commandMetadata;
    }

    /**
     * Get metadata for a specific command
     */
    public function getCommandMetadata(string $name): ?CommandMetadata
    {
        $all = $this->getAllCommandMetadata();
        return $all[$name] ?? null;
    }

    /**
     * Clear command cache
     */
    public function clearCache(): void
    {
        $this->commandMetadata = null;
    }

    /**
     * Extract metadata from a Symfony Command
     */
    private function extractMetadata(Command $command): CommandMetadata
    {
        $definition = $command->getDefinition();

        $arguments = [];
        foreach ($definition->getArguments() as $argument) {
            $arguments[] = new ArgumentMetadata(
                name: $argument->getName(),
                description: $argument->getDescription(),
                required: $argument->isRequired(),
                isArray: $argument->isArray(),
                default: $argument->getDefault(),
            );
        }

        $options = [];
        foreach ($definition->getOptions() as $option) {
            // Skip common options that are handled by Symfony
            if (\in_array(
                $option->getName(),
                ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction'],
                true,
            )) {
                continue;
            }

            $options[] = new OptionMetadata(
                name: $option->getName(),
                shortcut: $option->getShortcut(),
                description: $option->getDescription(),
                acceptValue: $option->acceptValue(),
                isValueRequired: $option->isValueRequired(),
                isArray: $option->isArray(),
                default: $option->getDefault(),
            );
        }

        return new CommandMetadata(
            name: $command->getName() ?? '',
            description: $command->getDescription(),
            help: $command->getHelp(),
            arguments: $arguments,
            options: $options,
        );
    }
}
