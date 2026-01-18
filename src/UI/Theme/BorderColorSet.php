<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

/**
 * Immutable value object for border colors
 */
final readonly class BorderColorSet
{
    public function __construct(
        public string $active,
        public string $inactive,
    ) {}
}
