<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\ComposerManager\Service;

/**
 * Utility to locate the Composer binary on the system.
 */
final class ComposerBinaryLocator
{
    /**
     * Common locations to check for Composer binary.
     *
     * @var array<string>
     */
    private const array CANDIDATES = [
        'composer',           // In PATH
        'composer.phar',      // Local phar
        '/usr/local/bin/composer',
        '/usr/bin/composer',
    ];

    private static ?string $cachedBinary = null;

    /**
     * Find the Composer binary.
     *
     * @return string|null Path to Composer binary, or null if not found
     */
    public static function find(): ?string
    {
        if (self::$cachedBinary !== null) {
            return self::$cachedBinary;
        }

        $candidates = self::CANDIDATES;

        // Add home directory location
        $home = $_SERVER['HOME'] ?? \getenv('HOME');
        if ($home !== false && $home !== '') {
            $candidates[] = $home . '/.composer/composer.phar';
        }

        foreach ($candidates as $candidate) {
            if (self::isExecutable($candidate)) {
                self::$cachedBinary = $candidate;
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Clear the cached binary path.
     * Useful for testing or when Composer is installed during session.
     */
    public static function clearCache(): void
    {
        self::$cachedBinary = null;
    }

    /**
     * Check if a file is a valid Composer executable.
     */
    private static function isExecutable(string $file): bool
    {
        $output = @\shell_exec(\escapeshellarg($file) . ' --version 2>&1');
        return $output !== null && \stripos($output, 'composer') !== false;
    }
}
