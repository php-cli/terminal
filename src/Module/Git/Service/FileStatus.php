<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\Git\Service;

/**
 * Represents the status of a file in the git working tree
 */
final readonly class FileStatus
{
    public const string STAGED = 'staged';
    public const string UNSTAGED = 'unstaged';
    public const string UNTRACKED = 'untracked';
    public const string CONFLICT = 'conflict';

    public function __construct(
        public string $path,
        public string $status,
        public string $indexStatus,
        public string $workTreeStatus,
        public ?string $originalPath = null, // For renamed files
    ) {}

    public function isStaged(): bool
    {
        return $this->status === self::STAGED;
    }

    public function isUnstaged(): bool
    {
        return $this->status === self::UNSTAGED;
    }

    public function isUntracked(): bool
    {
        return $this->status === self::UNTRACKED;
    }

    public function isConflict(): bool
    {
        return $this->status === self::CONFLICT;
    }

    public function isRenamed(): bool
    {
        return $this->originalPath !== null;
    }

    public function getStatusLabel(): string
    {
        return match ($this->indexStatus . $this->workTreeStatus) {
            'M ', ' M' => 'modified',
            'A ', ' A' => 'added',
            'D ', ' D' => 'deleted',
            'R ', ' R' => 'renamed',
            'C ', ' C' => 'copied',
            '??' => 'untracked',
            'UU', 'AA', 'DD' => 'conflict',
            'MM' => 'modified (both)',
            default => 'changed',
        };
    }

    public function getDisplayPath(): string
    {
        if ($this->originalPath !== null) {
            return "{$this->originalPath} → {$this->path}";
        }
        return $this->path;
    }
}
