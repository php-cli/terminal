<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

/**
 * Abstract base class for themes
 */
abstract class AbstractTheme implements ThemeInterface
{
    #[\Override]
    public function getNormalText(): string
    {
        return $this->getNormalBg() . $this->getNormalFg();
    }

    #[\Override]
    public function getMenuText(): string
    {
        return $this->getMenuBg() . $this->getMenuFg();
    }

    #[\Override]
    public function getStatusText(): string
    {
        return $this->getStatusBg() . $this->getStatusFg();
    }

    #[\Override]
    public function getSelectedText(): string
    {
        return $this->getSelectedBg() . $this->getSelectedFg();
    }

    #[\Override]
    public function getInputText(): string
    {
        return $this->getInputBg() . $this->getInputFg();
    }

    #[\Override]
    public function getMutedText(): string
    {
        return $this->getNormalBg() . ColorScheme::FG_GRAY;
    }
}
