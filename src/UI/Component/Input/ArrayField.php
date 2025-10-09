<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Input;

/**
 * Array field (comma-separated values)
 */
final class ArrayField extends TextField
{
    public function __construct(
        string $name,
        string $label,
        bool $required = false,
        string $description = '',
    ) {
        // Append "(comma-separated)" to description instead of label
        $fullDescription = $description !== ''
            ? $description . ' (comma-separated)'
            : 'Comma-separated values';

        parent::__construct($name, $label, $required, '', $fullDescription);
    }

    public function getValue(): mixed
    {
        $valueStr = (string) $this->value;

        if ($valueStr === '') {
            return [];
        }

        return array_map(trim(...), explode(',', $valueStr));
    }
}