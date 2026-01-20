<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\FileBrowser\Component;

use Butschster\Commander\Module\FileBrowser\Service\FileSystemService;
use Butschster\Commander\UI\Component\Display\Text\Container;
use Butschster\Commander\UI\Component\Display\Text\KeyValue;
use Butschster\Commander\UI\Component\Display\Text\Section;

/**
 * Feature component for displaying directory metadata
 *
 * Encapsulates directory-specific presentation logic using text components
 */
final class DirectoryInfoSection
{
    /**
     * Create directory information display
     *
     * @param string $path Directory path
     * @param array{
     *     name: string,
     *     path: string,
     *     size: int,
     *     modified: int,
     *     permissions: string,
     *     owner: string,
     *     group: string,
     *     type: string,
     *     mimeType: string,
     *     lines: int
     * } $metadata Directory metadata
     * @param array<array{name: string, path: string, type: string, size: int, modified: int, isDir: bool, readable: bool, writable: bool}> $items Directory contents
     */
    public static function create(string $path, array $metadata, array $items, FileSystemService $fileSystem): Container
    {
        $stats = self::calculateStats($items);

        return Container::create([
            Section::create(
                'Directory Information',
                KeyValue::create('Path', $path),
            )->hideSeparator()->marginBottom(0),

            Section::create(
                'Contents',
                Container::create([
                    KeyValue::create('Directories', (string) $stats['dirCount']),
                    KeyValue::create('Files', (string) $stats['fileCount']),
                    KeyValue::create('Total size', $fileSystem->formatSize($stats['totalSize'])),
                ])->spacing(0),
            ),

            Section::create(
                'Metadata',
                Container::create([
                    KeyValue::create('Permissions', $metadata['permissions']),
                    KeyValue::create('Owner', $metadata['owner']),
                    KeyValue::create('Group', $metadata['group']),
                    KeyValue::create('Modified', $fileSystem->formatDate($metadata['modified'])),
                ])->spacing(0),
            ),
        ])->spacing(1);
    }

    /**
     * Calculate directory statistics
     *
     * @param array<array{name: string, path: string, type: string, size: int, modified: int, isDir: bool, readable: bool, writable: bool}> $items
     * @return array{dirCount: int, fileCount: int, totalSize: int}
     */
    private static function calculateStats(array $items): array
    {
        $dirCount = 0;
        $fileCount = 0;
        $totalSize = 0;

        foreach ($items as $item) {
            if ($item['name'] === '..') {
                continue;
            }

            if ($item['isDir']) {
                $dirCount++;
            } else {
                $fileCount++;
                $totalSize += $item['size'];
            }
        }

        return [
            'dirCount' => $dirCount,
            'fileCount' => $fileCount,
            'totalSize' => $totalSize,
        ];
    }
}
