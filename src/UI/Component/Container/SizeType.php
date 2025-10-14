<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Container;

/**
 * Size unit type enumeration
 */
enum SizeType
{
    case FIXED;      // Absolute size in cells
    case PERCENTAGE; // Percentage of available space
    case FRACTION;   // Fractional unit (shares remaining space)
}
