<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\FileBrowser\Service;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * File system operations service using Symfony Finder
 */
final class FileSystemService
{
    /**
     * List directory contents
     *
     * @return array<array{
     *     name: string,
     *     path: string,
     *     type: string,
     *     size: int,
     *     modified: int,
     *     readable: bool,
     *     writable: bool,
     *     isDir: bool
     * }>
     */
    public function listDirectory(string $path, bool $showHidden = false): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $items = [];

        // Add parent directory entry if not root
        if ($path !== '/' && dirname($path) !== $path) {
            $parentPath = dirname($path);
            $parentStat = @stat($parentPath);
            $items[] = [
                'name' => '..',
                'path' => $parentPath,
                'type' => 'dir',
                'size' => 0,
                'modified' => $parentStat !== false ? $parentStat['mtime'] : filemtime($parentPath),
                'readable' => is_readable($parentPath),
                'writable' => is_writable($parentPath),
                'isDir' => true,
            ];
        }

        try {
            $finder = new Finder();
            $finder
                ->in($path)
                ->depth('== 0');

            if (!$showHidden) {
                $finder->ignoreDotFiles(true);
            }

            $directories = [];
            $files = [];

            /** @var SplFileInfo $file */
            foreach ($finder as $file) {
                $item = [
                    'name' => $file->getFilename(),
                    'path' => $file->getRealPath() ?: $file->getPathname(),
                    'type' => $file->isDir() ? 'dir' : $this->detectFileType($file),
                    'size' => $file->isFile() ? $file->getSize() : 0,
                    'modified' => $file->getMTime(),
                    'readable' => $file->isReadable(),
                    'writable' => $file->isWritable(),
                    'isDir' => $file->isDir(),
                ];

                if ($file->isDir()) {
                    $directories[] = $item;
                } else {
                    $files[] = $item;
                }
            }

            // Sort directories and files separately by name
            usort($directories, fn($a, $b) => strcasecmp($a['name'], $b['name']));
            usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

            // Merge: directories first, then files
            $items = array_merge($items, $directories, $files);
        } catch (\Exception $e) {
            // Handle permission errors gracefully
            return $items;
        }

        return $items;
    }

    /**
     * Read file contents
     *
     * @param string $path File path
     * @param int $maxLines Maximum number of lines to read (0 = unlimited)
     * @return string File contents
     */
    public function readFileContents(string $path, int $maxLines = 1000): string
    {
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }

        // Check if file is binary
        if ($this->isBinaryFile($path)) {
            return $this->getBinaryFileInfo($path);
        }

        try {
            $handle = fopen($path, 'r');
            if ($handle === false) {
                return '';
            }

            $contents = '';
            $lineCount = 0;

            while (!feof($handle) && ($maxLines === 0 || $lineCount < $maxLines)) {
                $line = fgets($handle);
                if ($line === false) {
                    break;
                }
                $contents .= $line;
                $lineCount++;
            }

            fclose($handle);

            if ($maxLines > 0 && $lineCount >= $maxLines) {
                $contents .= "\n\n... (file truncated, showing first {$maxLines} lines)";
            }

            return $contents;
        } catch (\Exception $e) {
            return "Error reading file: {$e->getMessage()}";
        }
    }

    /**
     * Get file metadata
     *
     * @return array{
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
     * }|null
     */
    public function getFileMetadata(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $stat = stat($path);
        if ($stat === false) {
            return null;
        }

        $isFile = is_file($path);

        return [
            'name' => basename($path),
            'path' => realpath($path) ?: $path,
            'size' => $stat['size'],
            'modified' => $stat['mtime'],
            'permissions' => $this->formatPermissions($stat['mode']),
            'owner' => function_exists('posix_getpwuid')
                ? (posix_getpwuid($stat['uid'])['name'] ?? $stat['uid'])
                : (string) $stat['uid'],
            'group' => function_exists('posix_getgrgid')
                ? (posix_getgrgid($stat['gid'])['name'] ?? $stat['gid'])
                : (string) $stat['gid'],
            'type' => $isFile ? $this->detectFileType(new \SplFileInfo($path)) : 'directory',
            'mimeType' => $isFile ? $this->getMimeType($path) : 'directory',
            'lines' => $isFile && !$this->isBinaryFile($path) ? $this->countLines($path) : 0,
        ];
    }

    /**
     * Format file size to human-readable format
     */
    public function formatSize(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }

    /**
     * Format Unix timestamp to date string
     */
    public function formatDate(int $timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Detect file type based on extension
     */
    private function detectFileType(\SplFileInfo $file): string
    {
        $extension = strtolower($file->getExtension());

        return match ($extension) {
            'php' => 'php',
            'js', 'jsx', 'ts', 'tsx' => 'javascript',
            'json' => 'json',
            'xml' => 'xml',
            'html', 'htm' => 'html',
            'css', 'scss', 'sass', 'less' => 'css',
            'md', 'markdown' => 'markdown',
            'txt', 'log' => 'text',
            'yaml', 'yml' => 'yaml',
            'sql' => 'sql',
            'sh', 'bash' => 'shell',
            'py' => 'python',
            'rb' => 'ruby',
            'go' => 'go',
            'rs' => 'rust',
            'java' => 'java',
            'c', 'cpp', 'h', 'hpp' => 'c/c++',
            'zip', 'tar', 'gz', 'bz2', '7z', 'rar' => 'archive',
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp' => 'image',
            'mp3', 'wav', 'ogg', 'flac' => 'audio',
            'mp4', 'avi', 'mkv', 'mov', 'wmv' => 'video',
            'pdf' => 'pdf',
            'doc', 'docx' => 'document',
            'xls', 'xlsx' => 'spreadsheet',
            default => 'file',
        };
    }

    /**
     * Check if file is binary
     */
    private function isBinaryFile(string $path): bool
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return false;
        }

        $chunk = fread($handle, 8192);
        fclose($handle);

        if ($chunk === false) {
            return false;
        }

        // Check for null bytes (common in binary files)
        return str_contains($chunk, "\0");
    }

    /**
     * Get binary file information
     */
    private function getBinaryFileInfo(string $path): string
    {
        $size = filesize($path);
        $mimeType = $this->getMimeType($path);

        return <<<TEXT
            Binary file
            -----------
            Size: {$this->formatSize($size)}
            MIME Type: {$mimeType}
            
            (Binary files cannot be displayed as text)
            TEXT;
    }

    /**
     * Get MIME type of file
     */
    private function getMimeType(string $path): string
    {
        if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($path);
            if ($mimeType !== false) {
                return $mimeType;
            }
        }

        return 'application/octet-stream';
    }

    /**
     * Count lines in file
     */
    private function countLines(string $path): int
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return 0;
        }

        $lines = 0;
        while (!feof($handle)) {
            if (fgets($handle) !== false) {
                $lines++;
            }
        }

        fclose($handle);

        return $lines;
    }

    /**
     * Format file permissions
     */
    private function formatPermissions(int $mode): string
    {
        $perms = '';

        // File type
        $perms .= match ($mode & 0170000) {
            0040000 => 'd', // Directory
            0100000 => '-', // Regular file
            0120000 => 'l', // Symbolic link
            default => '?',
        };

        // Owner permissions
        $perms .= (($mode & 0400) ? 'r' : '-');
        $perms .= (($mode & 0200) ? 'w' : '-');
        $perms .= (($mode & 0100) ? 'x' : '-');

        // Group permissions
        $perms .= (($mode & 040) ? 'r' : '-');
        $perms .= (($mode & 020) ? 'w' : '-');
        $perms .= (($mode & 010) ? 'x' : '-');

        // Other permissions
        $perms .= (($mode & 04) ? 'r' : '-');
        $perms .= (($mode & 02) ? 'w' : '-');
        $perms .= (($mode & 01) ? 'x' : '-');

        return $perms;
    }
}
