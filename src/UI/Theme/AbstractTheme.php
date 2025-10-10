<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

/**
 * Abstract base class for themes
 */
abstract class AbstractTheme implements ThemeInterface
{
    public function getNormalText(): string
    {
        return $this->getNormalBg() . $this->getNormalFg();
    }

    public function getMenuText(): string
    {
        return $this->getMenuBg() . $this->getMenuFg();
    }

    public function getStatusText(): string
    {
        return $this->getStatusBg() . $this->getStatusFg();
    }

    public function getSelectedText(): string
    {
        return $this->getSelectedBg() . $this->getSelectedFg();
    }

    public function getInputText(): string
    {
        return $this->getInputBg() . $this->getInputFg();
    }
}
