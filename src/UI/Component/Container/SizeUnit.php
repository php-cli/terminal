<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Container;

/**
 * Utility for parsing and calculating size units
 *
 * Supports:
 * - Percentages: '50%'
 * - Fractions: '1fr', '2fr' (CSS Grid style)
 * - Fixed: 20 (integer)
 * - Auto: '*' (takes remaining space, equivalent to 1fr)
 */
final readonly class SizeUnit
{
    private function __construct(
        public SizeType $type,
        public float $value,
    ) {}

    /**
     * Parse size specification into SizeUnit
     *
     * @param string|int $size Size specification
     */
    public static function parse(string|int $size): self
    {
        // Integer = fixed size
        if (\is_int($size)) {
            return new self(SizeType::FIXED, (float) $size);
        }

        // String parsing
        $size = \trim($size);

        // Auto ('*') = 1fr
        if ($size === '*') {
            return new self(SizeType::FRACTION, 1.0);
        }

        // Percentage ('50%')
        if (\str_ends_with($size, '%')) {
            $value = (float) \substr($size, 0, -1);
            return new self(SizeType::PERCENTAGE, $value);
        }

        // Fraction ('2fr')
        if (\str_ends_with($size, 'fr')) {
            $value = (float) \substr($size, 0, -2);
            return new self(SizeType::FRACTION, $value);
        }

        // Try to parse as number (treat as fixed)
        if (\is_numeric($size)) {
            return new self(SizeType::FIXED, (float) $size);
        }

        throw new \InvalidArgumentException("Invalid size format: {$size}");
    }

    /**
     * Calculate actual size in cells
     *
     * @param int $availableSpace Total space available
     * @param float $fractionUnit Size of one fractional unit (calculated from remaining space)
     * @return int Calculated size in cells
     */
    public function calculate(int $availableSpace, float $fractionUnit = 0.0): int
    {
        return match ($this->type) {
            SizeType::FIXED => (int) $this->value,
            SizeType::PERCENTAGE => (int) ($availableSpace * ($this->value / 100)),
            SizeType::FRACTION => (int) ($this->value * $fractionUnit),
        };
    }

    /**
     * Check if size is fixed (doesn't depend on available space)
     */
    public function isFixed(): bool
    {
        return $this->type === SizeType::FIXED;
    }

    /**
     * Check if size is flexible (depends on remaining space)
     */
    public function isFlexible(): bool
    {
        return $this->type === SizeType::FRACTION;
    }
}