<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Component;

use Butschster\Commander\UI\Component\Display\Text\Container;
use Butschster\Commander\UI\Component\Display\Text\ListComponent;
use Butschster\Commander\UI\Component\Display\Text\Section;
use Butschster\Commander\UI\Component\Display\Text\TextBlock;
use Butschster\Commander\UI\Component\Display\Text\TextComponent;

/**
 * Component for displaying package autoload configuration
 */
final class AutoloadSection extends TextComponent
{
    /**
     * @param array{psr4?: array<string, string|array<string>>, psr0?: array<string, string|array<string>>, classmap?: array<string>, files?: array<string>} $autoload
     */
    public function __construct(
        private readonly array $autoload,
    ) {}

    #[\Override]
    protected function render(): string
    {
        $hasPsr4 = !empty($this->autoload['psr4']);
        $hasPsr0 = !empty($this->autoload['psr0']);
        $hasClassmap = !empty($this->autoload['classmap']);
        $hasFiles = !empty($this->autoload['files']);
        $hasAny = $hasPsr4 || $hasPsr0 || $hasClassmap || $hasFiles;

        $content = Container::create([
            PackageHeader::create('AUTOLOAD CONFIGURATION'),
            TextBlock::newLine(),
            Section::create(
                'PSR-4 Namespaces',
                AutoloadNamespaces::create($this->autoload['psr4'] ?? []),
            )->displayWhen($hasPsr4),
            Section::create(
                'PSR-0 Namespaces',
                AutoloadNamespaces::create($this->autoload['psr0'] ?? []),
            )->displayWhen($hasPsr0),
            Section::create(
                'Classmap Directories',
                ListComponent::create($this->autoload['classmap'] ?? []),
            )->displayWhen($hasClassmap),
            Section::create(
                'Files (always loaded)',
                ListComponent::create($this->autoload['files'] ?? []),
            )->displayWhen($hasFiles),
            TextBlock::create('No autoload configuration defined.')->displayWhen(!$hasAny),
        ]);

        return (string) $content;
    }
}
