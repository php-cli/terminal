<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\Git\Service;

/**
 * Represents information about a git tag
 */
final readonly class TagInfo
{
    public function __construct(
        public string $name,
        public string $commitHash,
        public ?string $message = null,
        public ?string $taggerName = null,
        public ?string $taggerDate = null,
        public bool $isAnnotated = false,
    ) {}

    public function getShortCommitHash(): string
    {
        return \substr($this->commitHash, 0, 7);
    }

    public function getDisplayName(): string
    {
        $suffix = $this->isAnnotated ? ' (annotated)' : '';
        return $this->name . $suffix;
    }

    public function hasMessage(): bool
    {
        return $this->message !== null && $this->message !== '';
    }
}
