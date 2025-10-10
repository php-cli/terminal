<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Service;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\BufferIO;
use Composer\Package\CompletePackageInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;

/**
 * Handles interaction with Composer API
 *
 * Uses Composer's internal API instead of CLI for:
 * - Better performance (no process spawning)
 * - Richer metadata access
 * - Direct access to package objects
 */
final class ComposerService
{
    private ?Composer $composer = null;
    private ?InstalledRepositoryInterface $installedRepo = null;
    private ?array $installedPackagesCache = null;
    private ?array $outdatedPackagesCache = null;

    public function __construct(
        private readonly string $workingDirectory = '.',
    ) {}

    /**
     * Get list of installed packages with full metadata
     *
     * @return array<PackageInfo>
     */
    public function getInstalledPackages(): array
    {
        if ($this->installedPackagesCache !== null) {
            return $this->installedPackagesCache;
        }

        $composer = $this->getComposer();
        if ($composer === null) {
            return [];
        }

        $installedRepo = $this->getInstalledRepository();
        $rootPackage = $composer->getPackage();

        // Get direct dependencies from root package
        $directDeps = \array_merge(
            \array_keys($rootPackage->getRequires()),
            \array_keys($rootPackage->getDevRequires()),
        );

        $packages = [];
        foreach ($installedRepo->getPackages() as $package) {
            $packages[] = $this->createPackageInfo($package, \in_array($package->getName(), $directDeps, true));
        }

        // Sort by name
        \usort($packages, static fn($a, $b) => \strcasecmp($a->name, $b->name));

        $this->installedPackagesCache = $packages;
        return $packages;
    }

    /**
     * Get detailed information about a specific package
     */
    public function getPackageDetails(string $packageName): ?PackageInfo
    {
        $composer = $this->getComposer();
        if ($composer === null) {
            return null;
        }

        $installedRepo = $this->getInstalledRepository();
        $package = $installedRepo->findPackage($packageName, '*');

        if ($package === null) {
            return null;
        }

        $rootPackage = $composer->getPackage();
        $directDeps = \array_merge(
            \array_keys($rootPackage->getRequires()),
            \array_keys($rootPackage->getDevRequires()),
        );

        return $this->createPackageInfo($package, \in_array($packageName, $directDeps, true));
    }

    /**
     * Get package dependencies (required packages)
     *
     * @return array<string, string> Package name => version constraint
     */
    public function getPackageDependencies(string $packageName): array
    {
        $composer = $this->getComposer();
        if ($composer === null) {
            return [];
        }

        $installedRepo = $this->getInstalledRepository();
        $package = $installedRepo->findPackage($packageName, '*');

        if ($package === null) {
            return [];
        }

        $deps = [];
        foreach ($package->getRequires() as $link) {
            $deps[$link->getTarget()] = $link->getConstraint()->getPrettyString();
        }

        return $deps;
    }

    /**
     * Get packages that depend on this package (reverse dependencies)
     *
     * @return array<string> Package names
     */
    public function getReverseDependencies(string $packageName): array
    {
        $composer = $this->getComposer();
        if ($composer === null) {
            return [];
        }

        $installedRepo = $this->getInstalledRepository();
        $dependents = [];

        foreach ($installedRepo->getPackages() as $package) {
            foreach ($package->getRequires() as $link) {
                if ($link->getTarget() === $packageName) {
                    $dependents[] = $package->getName();
                    break;
                }
            }
        }

        \sort($dependents);
        return $dependents;
    }

    /**
     * Get package autoload configuration
     *
     * @return array{psr4: array, psr0: array, classmap: array, files: array}
     */
    public function getPackageAutoload(string $packageName): array
    {
        $composer = $this->getComposer();
        if ($composer === null) {
            return ['psr4' => [], 'psr0' => [], 'classmap' => [], 'files' => []];
        }

        $installedRepo = $this->getInstalledRepository();
        $package = $installedRepo->findPackage($packageName, '*');

        if ($package === null) {
            return ['psr4' => [], 'psr0' => [], 'classmap' => [], 'files' => []];
        }

        $autoload = $package->getAutoload();

        return [
            'psr4' => $autoload['psr-4'] ?? [],
            'psr0' => $autoload['psr-0'] ?? [],
            'classmap' => $autoload['classmap'] ?? [],
            'files' => $autoload['files'] ?? [],
        ];
    }

    /**
     * Get package scripts
     *
     * @return array<string, string|array> Script name => command(s)
     */
    public function getPackageScripts(string $packageName): array
    {
        $composer = $this->getComposer();
        if ($composer === null) {
            return [];
        }

        $installedRepo = $this->getInstalledRepository();
        $package = $installedRepo->findPackage($packageName, '*');

        if ($package === null || !$package instanceof CompletePackageInterface) {
            return [];
        }

        return $package->getScripts();
    }

    /**
     * Get outdated packages
     *
     * @return array<OutdatedPackageInfo>
     */
    public function getOutdatedPackages(): array
    {
        if ($this->outdatedPackagesCache !== null) {
            return $this->outdatedPackagesCache;
        }

        // Use CLI for outdated check
        $result = $this->runComposerCommand(['outdated', '--format=json', '--direct']);

        if ($result['exitCode'] !== 0) {
            $this->outdatedPackagesCache = [];
            return [];
        }

        $output = $result['output'];
        $data = \json_decode($output, true);

        if (!\is_array($data) || !isset($data['installed'])) {
            $this->outdatedPackagesCache = [];
            return [];
        }

        $packages = [];
        foreach ($data['installed'] as $pkg) {
            $packages[] = new OutdatedPackageInfo(
                name: $pkg['name'],
                currentVersion: $pkg['version'],
                latestVersion: $pkg['latest'] ?? $pkg['latest-status'] ?? 'unknown',
                description: $pkg['description'] ?? '',
                warning: $pkg['warning'] ?? null,
            );
        }

        $this->outdatedPackagesCache = $packages;
        return $packages;
    }

    /**
     * Run security audit
     *
     * @return array{summary: array, advisories: array<SecurityAdvisory>}
     */
    public function runAudit(): array
    {
        $result = $this->runComposerCommand(['audit', '--format=json']);

        if ($result['exitCode'] !== 0 && $result['exitCode'] !== 1) {
            // Exit code 1 means vulnerabilities found (expected)
            return ['summary' => [], 'advisories' => []];
        }

        $output = $result['output'];
        $data = \json_decode($output, true);

        if (!\is_array($data)) {
            return ['summary' => [], 'advisories' => []];
        }

        $summary = [
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        $advisories = [];

        foreach ($data['advisories'] ?? [] as $packageName => $packageAdvisories) {
            foreach ($packageAdvisories as $advisory) {
                $severity = \strtolower($advisory['severity'] ?? 'unknown');

                if (isset($summary[$severity])) {
                    $summary[$severity]++;
                } elseif (\in_array($severity, ['critical'])) {
                    $summary['high']++;
                }

                $advisories[] = new SecurityAdvisory(
                    packageName: $packageName,
                    title: $advisory['title'] ?? 'Unknown vulnerability',
                    cve: $advisory['cve'] ?? null,
                    affectedVersions: $advisory['affectedVersions'] ?? 'unknown',
                    link: $advisory['link'] ?? null,
                    severity: $severity,
                );
            }
        }

        return [
            'summary' => $summary,
            'advisories' => $advisories,
        ];
    }

    /**
     * Update a specific package
     *
     * @param callable(string): void $outputCallback Called with each line of output
     * @return array{exitCode: int, error: string}
     */
    public function updatePackage(string $packageName, callable $outputCallback): array
    {
        return $this->runComposerCommand(['update', $packageName, '--with-dependencies'], $outputCallback);
    }

    /**
     * Update all packages
     *
     * @param callable(string): void $outputCallback Called with each line of output
     * @return array{exitCode: int, error: string}
     */
    public function updateAll(callable $outputCallback): array
    {
        return $this->runComposerCommand(['update'], $outputCallback);
    }

    /**
     * Remove a package
     *
     * @param callable(string): void $outputCallback Called with each line of output
     * @return array{exitCode: int, error: string}
     */
    public function removePackage(string $packageName, callable $outputCallback): array
    {
        return $this->runComposerCommand(['remove', $packageName], $outputCallback);
    }

    /**
     * Require a package
     *
     * @param callable(string): void $outputCallback Called with each line of output
     * @return array{exitCode: int, error: string}
     */
    public function requirePackage(string $packageName, bool $dev, callable $outputCallback): array
    {
        $args = ['require', $packageName];

        if ($dev) {
            $args[] = '--dev';
        }

        return $this->runComposerCommand($args, $outputCallback);
    }

    /**
     * Get platform requirements
     *
     * @return array<string, string> Platform package => version constraint
     */
    public function getPlatformRequirements(): array
    {
        $composer = $this->getComposer();
        if ($composer === null) {
            return [];
        }

        $rootPackage = $composer->getPackage();
        $platform = [];

        foreach ($rootPackage->getRequires() as $link) {
            $target = $link->getTarget();
            if (\str_starts_with($target, 'php') || \str_starts_with($target, 'ext-')) {
                $platform[$target] = $link->getConstraint()->getPrettyString();
            }
        }

        return $platform;
    }

    /**
     * Check if package can be safely removed (no other packages depend on it)
     */
    public function canRemovePackage(string $packageName): bool
    {
        $reverseDeps = $this->getReverseDependencies($packageName);

        // Package can be removed if no other packages depend on it
        // (except if it's only depended on by dev packages)
        return empty($reverseDeps);
    }

    /**
     * Get all root-level scripts defined in composer.json
     *
     * @return array<string, string|array>
     */
    public function getRootScripts(): array
    {
        $composer = $this->getComposer();
        if ($composer === null) {
            return [];
        }

        $rootPackage = $composer->getPackage();
        if (!$rootPackage instanceof CompletePackageInterface) {
            return [];
        }

        return $rootPackage->getScripts();
    }

    /**
     * Run a composer script
     *
     * @param callable(string): void $outputCallback Called with each line of output
     * @return array{exitCode: int, error: string}
     */
    public function runScript(string $scriptName, callable $outputCallback): array
    {
        return $this->runComposerCommand(['run-script', $scriptName], $outputCallback);
    }

    /**
     * Clear all caches
     */
    public function clearCache(): void
    {
        $this->installedPackagesCache = null;
        $this->outdatedPackagesCache = null;
        $this->composer = null;
        $this->installedRepo = null;
    }

    /**
     * Check if composer.json exists and is valid
     */
    public function isAvailable(): bool
    {
        return $this->getComposer() !== null;
    }

    /**
     * Get composer version
     */
    public function getVersion(): string
    {
        return Composer::getVersion();
    }

    /**
     * Get or create Composer instance
     */
    private function getComposer(): ?Composer
    {
        if ($this->composer !== null) {
            return $this->composer;
        }

        $composerFile = $this->workingDirectory . '/composer.json';

        if (!\file_exists($composerFile)) {
            return null;
        }

        try {
            $io = new BufferIO();
            $this->composer = Factory::create($io, $composerFile);
            return $this->composer;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get installed repository
     */
    private function getInstalledRepository(): ?InstalledRepositoryInterface
    {
        if ($this->installedRepo !== null) {
            return $this->installedRepo;
        }

        $composer = $this->getComposer();
        if ($composer === null) {
            return null;
        }

        $this->installedRepo = $composer->getRepositoryManager()->getLocalRepository();
        return $this->installedRepo;
    }

    /**
     * Convert Composer Package to our PackageInfo DTO
     */
    private function createPackageInfo(PackageInterface $package, bool $isDirect): PackageInfo
    {
        $abandoned = false;
        $source = null;
        $homepage = null;
        $keywords = [];
        $authors = [];
        $license = [];
        $support = [];
        $suggests = [];
        $binaries = [];

        // CompletePackageInterface has additional metadata
        if ($package instanceof CompletePackageInterface) {
            $abandoned = $package->isAbandoned();
            $homepage = $package->getHomepage();
            $keywords = $package->getKeywords();
            $authors = \array_map(static fn($author)
                => [
                    'name' => $author['name'] ?? null,
                    'email' => $author['email'] ?? null,
                    'homepage' => $author['homepage'] ?? null,
                    'role' => $author['role'] ?? null,
                ], $package->getAuthors());
            $license = $package->getLicense();
            $support = $package->getSupport();
            $suggests = $package->getSuggests();
            $binaries = $package->getBinaries();
        }

        // Get source info
        if ($package->getSourceUrl()) {
            $source = $package->getSourceUrl();
        } elseif ($package->getDistUrl()) {
            $source = $package->getDistUrl();
        }

        // Get dependencies
        $requires = [];
        foreach ($package->getRequires() as $link) {
            $requires[$link->getTarget()] = $link->getConstraint()->getPrettyString();
        }

        $devRequires = [];
        foreach ($package->getDevRequires() as $link) {
            $devRequires[$link->getTarget()] = $link->getConstraint()->getPrettyString();
        }

        // Get autoload info
        $autoload = $package->getAutoload();

        return new PackageInfo(
            name: $package->getName(),
            version: $package->getPrettyVersion(),
            description: $package->getDescription() ?? '',
            type: $package->getType(),
            source: $source,
            homepage: $homepage,
            abandoned: $abandoned,
            isDirect: $isDirect,
            keywords: $keywords,
            authors: $authors,
            license: $license,
            support: $support,
            requires: $requires,
            devRequires: $devRequires,
            suggests: $suggests,
            autoload: [
                'psr4' => $autoload['psr-4'] ?? [],
                'psr0' => $autoload['psr-0'] ?? [],
                'classmap' => $autoload['classmap'] ?? [],
                'files' => $autoload['files'] ?? [],
            ],
            binaries: $binaries,
        );
    }

    /**
     * Run a composer command via CLI
     *
     * @param array<string> $args Command arguments
     * @param callable(string): void|null $outputCallback Called with each line of output
     * @return array{exitCode: int, output: string, error: string}
     */
    private function runComposerCommand(array $args, ?callable $outputCallback = null): array
    {
        // Find composer binary
        $composerBinary = $this->findComposerBinary();

        if ($composerBinary === null) {
            return [
                'exitCode' => 1,
                'output' => '',
                'error' => 'Composer binary not found',
            ];
        }

        // Build command
        $command = \array_merge([$composerBinary], $args);
        $commandString = \implode(' ', \array_map('escapeshellarg', $command));

        // Execute command
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = \proc_open(
            $commandString,
            $descriptors,
            $pipes,
            $this->workingDirectory,
        );

        if (!\is_resource($process)) {
            return [
                'exitCode' => 1,
                'output' => '',
                'error' => 'Failed to start composer process',
            ];
        }

        // Close stdin
        \fclose($pipes[0]);

        // Read output
        \stream_set_blocking($pipes[1], false);
        \stream_set_blocking($pipes[2], false);

        $output = '';
        $error = '';

        while (true) {
            // Read stdout
            $stdout = \fgets($pipes[1]);
            if ($stdout !== false) {
                $output .= $stdout;
                if ($outputCallback !== null) {
                    $outputCallback($stdout);
                }
            }

            // Read stderr
            $stderr = \fgets($pipes[2]);
            if ($stderr !== false) {
                $error .= $stderr;
                if ($outputCallback !== null) {
                    $outputCallback($stderr);
                }
            }

            // Check if process is still running
            $status = \proc_get_status($process);
            if (!$status['running']) {
                // Read remaining output
                while (($stdout = \fgets($pipes[1])) !== false) {
                    $output .= $stdout;
                    if ($outputCallback !== null) {
                        $outputCallback($stdout);
                    }
                }
                while (($stderr = \fgets($pipes[2])) !== false) {
                    $error .= $stderr;
                    if ($outputCallback !== null) {
                        $outputCallback($stderr);
                    }
                }
                break;
            }

            // Small delay to prevent busy-waiting
            \usleep(10000); // 10ms
        }

        \fclose($pipes[1]);
        \fclose($pipes[2]);

        $exitCode = \proc_close($process);

        return [
            'exitCode' => $exitCode,
            'output' => $output,
            'error' => $error,
        ];
    }

    /**
     * Find composer binary
     */
    private function findComposerBinary(): ?string
    {
        // Try common locations
        $candidates = [
            'composer',           // In PATH
            'composer.phar',      // Local phar
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            $_SERVER['HOME'] . '/.composer/composer.phar',
        ];

        foreach ($candidates as $candidate) {
            if ($this->isExecutable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Check if a file is executable
     */
    private function isExecutable(string $file): bool
    {
        // Try to execute with --version
        $output = @\shell_exec(\escapeshellarg($file) . ' --version 2>&1');
        return $output !== null && \stripos($output, 'composer') !== false;
    }
}
