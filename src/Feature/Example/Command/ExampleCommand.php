<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\Example\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Example Symfony Console command for demonstration
 */
final class ExampleCommand extends Command
{
    protected static $defaultDescription = 'Greet someone with customizable options';

    #[\Override]
    public static function getDefaultName(): string
    {
        return 'example:greet';
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command demonstrates how arguments and options work in the MC-style interface')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Name of the person to greet',
            )
            ->addArgument(
                'title',
                InputArgument::OPTIONAL,
                'Optional title (Mr., Mrs., Dr., etc.)',
                '',
            )
            ->addOption(
                'uppercase',
                'u',
                InputOption::VALUE_NONE,
                'Display greeting in uppercase',
            )
            ->addOption(
                'repeat',
                'r',
                InputOption::VALUE_REQUIRED,
                'Number of times to repeat the greeting',
                1,
            )
            ->addOption(
                'prefix',
                'p',
                InputOption::VALUE_REQUIRED,
                'Prefix to add before the greeting',
                'Hello',
            )
            ->addOption(
                'exclamation',
                'e',
                InputOption::VALUE_NONE,
                'Add exclamation marks',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $title = $input->getArgument('title');
        $uppercase = $input->getOption('uppercase');
        $repeat = (int) $input->getOption('repeat');
        $prefix = $input->getOption('prefix');
        $exclamation = $input->getOption('exclamation');

        // Build greeting
        $fullName = $title ? "$title $name" : $name;
        $greeting = "$prefix, $fullName";

        if ($exclamation) {
            $greeting .= '!!!';
        } else {
            $greeting .= '.';
        }

        if ($uppercase) {
            $greeting = \strtoupper($greeting);
        }

        // Output repeated greeting
        for ($i = 0; $i < $repeat; $i++) {
            $output->writeln($greeting);
        }

        return Command::SUCCESS;
    }
}
