<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\ComposerManager\Service;

/**
 * Package search result
 */
final readonly class SearchResult
{
    public function __construct(
        public string $name,
        public string $description,
        public int $downloads,
        public int $favers,
    ) {}
}
