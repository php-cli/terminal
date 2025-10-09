<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Container;

/**
 * Alignment options for layout components
 */
enum Alignment: string
{
    case START = 'start';
    case CENTER = 'center';
    case END = 'end';
    case STRETCH = 'stretch';
}
