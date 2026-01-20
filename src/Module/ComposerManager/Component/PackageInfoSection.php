<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\ComposerManager\Component;

use Butschster\Commander\Module\ComposerManager\Service\PackageInfo;
use Butschster\Commander\UI\Component\Display\Text\Container;
use Butschster\Commander\UI\Component\Display\Text\KeyValue;
use Butschster\Commander\UI\Component\Display\Text\TextBlock;
use Butschster\Commander\UI\Component\Display\Text\TextComponent;

/**
 * Component for displaying basic package information header
 */
final class PackageInfoSection extends TextComponent
{
    public function __construct(
        private readonly PackageInfo $package,
    ) {}

    #[\Override]
    protected function render(): string
    {
        $content = Container::create([
            KeyValue::create('Name', $this->package->name),
            KeyValue::create('Version', $this->package->version),
            KeyValue::create('Type', $this->package->type),
            KeyValue::create('License', $this->package->getLicenseString()),
            WarningBox::create(
                'This package is ABANDONED!',
                'Consider migrating to an alternative.',
            )->displayWhen($this->package->abandoned),
            TextBlock::newLine(),
            KeyValue::create(
                'Status',
                $this->package->isDirect ? 'Direct dependency (in composer.json)' : 'Transitive dependency',
            ),
        ])->spacing(0);

        return (string) $content;
    }
}
