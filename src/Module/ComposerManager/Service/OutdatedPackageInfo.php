<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\ComposerManager\Service;

/**
 * Outdated package information
 */
final readonly class OutdatedPackageInfo
{
    public function __construct(
        public string $name,
        public string $currentVersion,
        public string $latestVersion,
        public string $description,
        public ?string $warning,
    ) {}

    public function isMajorUpdate(): bool
    {
        return $this->getVersionDifference() === 'major';
    }

    public function isMinorUpdate(): bool
    {
        return $this->getVersionDifference() === 'minor';
    }

    public function isPatchUpdate(): bool
    {
        return $this->getVersionDifference() === 'patch';
    }

    private function getVersionDifference(): string
    {
        $current = $this->parseVersion($this->currentVersion);
        $latest = $this->parseVersion($this->latestVersion);

        if ($current['major'] !== $latest['major']) {
            return 'major';
        }

        if ($current['minor'] !== $latest['minor']) {
            return 'minor';
        }

        return 'patch';
    }

    /**
     * @return array{major: int, minor: int, patch: int}
     */
    private function parseVersion(string $version): array
    {
        // Remove 'v' prefix if present
        $version = \ltrim($version, 'v');

        // Extract version numbers
        if (\preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $matches)) {
            return [
                'major' => (int) $matches[1],
                'minor' => (int) $matches[2],
                'patch' => (int) $matches[3],
            ];
        }

        return ['major' => 0, 'minor' => 0, 'patch' => 0];
    }
}
