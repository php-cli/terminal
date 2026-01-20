<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Keyboard\Mapping;

/**
 * Terminal type for sequence compatibility.
 *
 * Different terminals use different escape sequences for the same keys.
 * This allows registering multiple sequences for the same key.
 */
enum TerminalType: string
{
    /** Works in all terminals */
    case Common = 'common';

    /** xterm-specific sequences */
    case Xterm = 'xterm';

    /** Linux console specific */
    case Linux = 'linux';

    /** VT100 compatible terminals */
    case VT100 = 'vt100';
}
