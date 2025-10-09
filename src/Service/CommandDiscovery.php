<?php

declare(strict_types=1);

namespace Butschster\Commander\Service;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

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
        return array_keys($metadata);
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

        ksort($this->commandMetadata);

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
            if (in_array(
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

    /**
     * Search commands by name or description
     *
     * @param string $query Search query
     * @return array<string>
     */
    public function searchCommands(string $query): array
    {
        if ($query === '') {
            return $this->getAllCommands();
        }

        $query = strtolower($query);
        $metadata = $this->getAllCommandMetadata();
        $results = [];

        foreach ($metadata as $name => $meta) {
            if (str_contains(strtolower($name), $query) ||
                str_contains(strtolower($meta->description), $query)) {
                $results[] = $name;
            }
        }

        return $results;
    }

    /**
     * Check if a command exists
     */
    public function commandExists(string $command): bool
    {
        return isset($this->getAllCommandMetadata()[$command]);
    }

    /**
     * Clear command cache
     */
    public function clearCache(): void
    {
        $this->commandMetadata = null;
    }
}

/**
 * Command metadata structure
 */
final class CommandMetadata
{
    /**
     * @param string $name Command name
     * @param string $description Short description
     * @param string $help Full help text
     * @param array<ArgumentMetadata> $arguments Command arguments
     * @param array<OptionMetadata> $options Command options
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $help,
        public readonly array $arguments,
        public readonly array $options,
    ) {}
}

/**
 * Argument metadata structure
 */
final class ArgumentMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly bool $required,
        public readonly bool $isArray,
        public readonly mixed $default,
    ) {}
}

/**
 * Option metadata structure
 */
final class OptionMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $shortcut,
        public readonly string $description,
        public readonly bool $acceptValue,
        public readonly bool $isValueRequired,
        public readonly bool $isArray,
        public readonly mixed $default,
    ) {}
}
