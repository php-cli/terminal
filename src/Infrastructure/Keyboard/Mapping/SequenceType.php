<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Keyboard\Mapping;

/**
 * Type of key sequence for categorization.
 */
enum SequenceType: string
{
    /** ESC sequences (arrows, function keys, navigation) */
    case Escape = 'escape';

    /** Ctrl+letter combinations (ASCII 1-26) */
    case Control = 'control';

    /** Special keys (Tab, Enter, Backspace, etc.) */
    case Special = 'special';

    /** Regular printable characters */
    case Printable = 'printable';
}
