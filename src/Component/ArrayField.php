<?php

declare(strict_types=1);

namespace Butschster\Commander\Component;

/**
 * Array field (comma-separated values)
 */
final class ArrayField extends TextField
{
    public function __construct(
        string $name,
        string $label,
        bool $required = false,
    ) {
        parent::__construct($name, $label . ' (comma-separated)', $required, '');
    }

    public function getValue(): mixed
    {
        $valueStr = (string) $this->value;

        if ($valueStr === '') {
            return [];
        }

        return array_map('trim', explode(',', $valueStr));
    }
}