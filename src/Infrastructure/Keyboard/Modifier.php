<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Keyboard;

/**
 * Enum representing keyboard modifiers.
 */
enum Modifier: string
{
    case CTRL = 'CTRL';
    case ALT = 'ALT';
    case SHIFT = 'SHIFT';

    /**
     * Get the raw prefix used in KeyboardHandler output strings.
     *
     * @return string The prefix like "CTRL_", "ALT_", "SHIFT_"
     */
    public function toRawPrefix(): string
    {
        return $this->value . '_';
    }
}
