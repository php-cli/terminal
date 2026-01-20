<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

/**
 * Immutable value object for background/foreground color pairs
 */
final readonly class ColorSet
{
    public function __construct(
        public string $background,
        public string $foreground,
    ) {}

    /**
     * Get combined ANSI code (bg + fg)
     */
    public function combined(): string
    {
        return $this->background . $this->foreground;
    }

    /**
     * Create with additional style (bold, italic, etc.)
     */
    public function withStyle(string $style): string
    {
        return $this->background . $this->foreground . $style;
    }
}
