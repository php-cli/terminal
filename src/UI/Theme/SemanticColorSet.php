<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

/**
 * Immutable value object for semantic colors (error, warning, highlight, scrollbar)
 */
final readonly class SemanticColorSet
{
    public function __construct(
        public string $error,
        public string $warning,
        public string $highlight,
        public string $scrollbar,
    ) {}
}
