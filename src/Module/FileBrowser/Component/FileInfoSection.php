<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\FileBrowser\Component;

use Butschster\Commander\Module\FileBrowser\Service\FileSystemService;
use Butschster\Commander\UI\Component\Display\Text\Container;
use Butschster\Commander\UI\Component\Display\Text\KeyValue;
use Butschster\Commander\UI\Component\Display\Text\Section;
use Butschster\Commander\UI\Component\Display\Text\TextBlock;

/**
 * Feature component for displaying file metadata
 *
 * Encapsulates file-specific presentation logic using text components
 */
final class FileInfoSection
{
    /**
     * Create file information display
     *
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
     * } $metadata
     */
    public static function create(array $metadata, FileSystemService $fileSystem): Container
    {
        return Container::create([
            Section::create(
                'File Information',
                Container::create([
                    KeyValue::create('Name', \basename($metadata['path'])),
                    KeyValue::create('Path', \dirname($metadata['path'])),
                ])->spacing(0),
            )->hideSeparator()->marginBottom(0),

            Section::create(
                'Details',
                Container::create([
                    KeyValue::create('Type', $metadata['type']),
                    KeyValue::create('Size', $fileSystem->formatSize($metadata['size'])),
                    KeyValue::create('Permissions', $metadata['permissions']),
                    KeyValue::create('Owner', $metadata['owner']),
                    KeyValue::create('Group', $metadata['group']),
                    KeyValue::create('Modified', $fileSystem->formatDate($metadata['modified'])),
                    KeyValue::create('Lines', (string) $metadata['lines'])
                        ->displayWhen($metadata['lines'] > 0),
                ])->spacing(0),
            ),
            TextBlock::repeat('─', 41),
            TextBlock::create('Press [Enter] to view file contents'),
        ])->spacing(1);
    }
}
