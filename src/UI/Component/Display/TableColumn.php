<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Display;

/**
 * Table column definition
 *
 * Defines how a column should be displayed in a table:
 * - Width specification (fixed, percentage, or flex)
 * - Alignment
 * - Header label
 * - Value extraction/formatting
 */
final class TableColumn
{
    public const string ALIGN_LEFT = 'left';
    public const string ALIGN_RIGHT = 'right';
    public const string ALIGN_CENTER = 'center';

    /**
     * @param string $key Column identifier
     * @param string $label Header label
     * @param string|int $width Width specification:
     *                          - int: fixed width in characters
     *                          - '30%': percentage of available width
     *                          - '*': flex (takes remaining space)
     * @param string $align Text alignment (left, right, center)
     * @param callable|null $formatter Optional formatter: fn(mixed $value, array $row): string
     * @param callable|null $colorizer Optional colorizer: fn(mixed $value, array $row, bool $selected): string
     */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string|int $width = '*',
        private readonly string $align = self::ALIGN_LEFT,
        private $formatter = null,
        private $colorizer = null,
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getWidth(): string|int
    {
        return $this->width;
    }

    public function getAlign(): string
    {
        return $this->align;
    }

    /**
     * Format cell value
     *
     * @param array<string, mixed> $row Full row data
     */
    public function formatValue(mixed $value, array $row): string
    {
        if ($this->formatter !== null) {
            return (string) ($this->formatter)($value, $row);
        }

        return (string) $value;
    }

    /**
     * Get color for cell
     *
     * @param array<string, mixed> $row Full row data
     * @return string|null ANSI color code or null for default
     */
    public function getColor(mixed $value, array $row, bool $selected): ?string
    {
        if ($this->colorizer !== null) {
            return ($this->colorizer)($value, $row, $selected);
        }

        return null;
    }
}
