<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Component;

use Butschster\Commander\UI\Component\Display\Text\TextComponent;

/**
 * Component for displaying package statistics
 */
final class PackageStatistics extends TextComponent
{
    /**
     * @param int $totalDependencies Total number of dependencies
     * @param int $productionCount Production dependencies count
     * @param int $developmentCount Development dependencies count
     * @param int $reverseDependenciesCount Packages that depend on this
     * @param int|null $namespacesCount Number of autoload namespaces
     */
    public function __construct(
        private readonly int $totalDependencies,
        private readonly int $productionCount,
        private readonly int $developmentCount,
        private readonly int $reverseDependenciesCount,
        private readonly ?int $namespacesCount = null,
    ) {}

    protected function render(): string
    {
        $lines = [
            "  Dependencies: {$this->totalDependencies} total",
            "    ├─ Production: {$this->productionCount}",
            "    └─ Development: {$this->developmentCount}",
            "",
            "  Reverse Dependencies: {$this->reverseDependenciesCount} packages depend on this",
        ];

        if ($this->namespacesCount !== null) {
            $lines[] = "";
            $lines[] = "  Namespaces: {$this->namespacesCount}";
        }

        return \implode("\n", $lines);
    }
}
