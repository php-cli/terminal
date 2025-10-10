<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Service;

/**
 * Rich package information from Composer API
 */
final readonly class PackageInfo
{
    public function __construct(
        public string $name,
        public string $version,
        public string $description,
        public string $type,
        public ?string $source,
        public ?string $homepage,
        public bool $abandoned,
        public bool $isDirect,
        public array $keywords,
        public array $authors,
        public array $license,
        public array $support,
        public array $requires,
        public array $devRequires,
        public array $suggests,
        public array $autoload,
        public array $binaries,
    ) {}

    /**
     * Get primary author
     */
    public function getPrimaryAuthor(): ?array
    {
        return $this->authors[0] ?? null;
    }

    /**
     * Get license string
     */
    public function getLicenseString(): string
    {
        if (empty($this->license)) {
            return 'N/A';
        }

        return \implode(', ', $this->license);
    }

    /**
     * Get support links as formatted string
     */
    public function getSupportString(): string
    {
        if (empty($this->support)) {
            return 'N/A';
        }

        $links = [];
        foreach ($this->support as $type => $url) {
            $links[] = \ucfirst((string) $type) . ': ' . $url;
        }

        return \implode("\n", $links);
    }

    /**
     * Get total dependency count
     */
    public function getTotalDependencies(): int
    {
        return \count($this->requires) + \count($this->devRequires);
    }

    /**
     * Check if package has autoload configuration
     */
    public function hasAutoload(): bool
    {
        return !empty($this->autoload['psr4'])
            || !empty($this->autoload['psr0'])
            || !empty($this->autoload['classmap'])
            || !empty($this->autoload['files']);
    }

    /**
     * Get autoload namespaces (PSR-4 + PSR-0)
     */
    public function getNamespaces(): array
    {
        $namespaces = [];

        foreach (\array_keys($this->autoload['psr4'] ?? []) as $namespace) {
            $namespaces[] = \rtrim((string) $namespace, '\\');
        }

        foreach (\array_keys($this->autoload['psr0'] ?? []) as $namespace) {
            $namespaces[] = \rtrim((string) $namespace, '\\');
        }

        return \array_unique($namespaces);
    }

    /**
     * Check if package provides binaries
     */
    public function hasBinaries(): bool
    {
        return !empty($this->binaries);
    }

    /**
     * Check if package has suggestions
     */
    public function hasSuggestions(): bool
    {
        return !empty($this->suggests);
    }
}
