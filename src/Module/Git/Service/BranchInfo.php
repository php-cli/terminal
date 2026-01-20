<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\Git\Service;

/**
 * Represents information about a git branch
 */
final readonly class BranchInfo
{
    public function __construct(
        public string $name,
        public bool $isCurrent,
        public bool $isRemote,
        public ?string $upstream = null,
        public ?string $lastCommitHash = null,
        public ?string $lastCommitMessage = null,
        public ?int $aheadCount = null,
        public ?int $behindCount = null,
    ) {}

    public function getDisplayName(): string
    {
        $prefix = $this->isCurrent ? '* ' : '  ';
        return $prefix . $this->name;
    }

    public function hasUpstream(): bool
    {
        return $this->upstream !== null;
    }

    public function getTrackingStatus(): string
    {
        if (!$this->hasUpstream()) {
            return '';
        }

        $parts = [];
        if ($this->aheadCount > 0) {
            $parts[] = "↑{$this->aheadCount}";
        }
        if ($this->behindCount > 0) {
            $parts[] = "↓{$this->behindCount}";
        }

        return $parts ? '[' . \implode(' ', $parts) . ']' : '[=]';
    }

    public function getShortCommitHash(): string
    {
        if ($this->lastCommitHash === null) {
            return '';
        }
        return \substr($this->lastCommitHash, 0, 7);
    }
}
