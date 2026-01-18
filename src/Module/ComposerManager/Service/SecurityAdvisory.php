<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\ComposerManager\Service;

/**
 * Security advisory information
 */
final readonly class SecurityAdvisory
{
    public function __construct(
        public string $packageName,
        public string $title,
        public ?string $cve,
        public string $affectedVersions,
        public ?string $link,
        public string $severity,
    ) {}

    public function isCritical(): bool
    {
        return \in_array($this->severity, ['critical', 'high'], true);
    }

    public function getSeverityColor(): string
    {
        return match ($this->severity) {
            'critical', 'high' => 'red',
            'medium' => 'yellow',
            default => 'white',
        };
    }
}
