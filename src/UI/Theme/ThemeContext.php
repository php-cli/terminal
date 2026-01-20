<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

/**
 * Injectable theme context - replaces static ColorScheme properties
 */
final readonly class ThemeContext
{
    private ColorSet $normal;
    private ColorSet $menu;
    private ColorSet $status;
    private ColorSet $selected;
    private ColorSet $input;
    private BorderColorSet $borders;
    private SemanticColorSet $semantic;

    public function __construct(
        private ThemeInterface $theme,
    ) {
        // Pre-compute color sets for efficiency
        $this->normal = new ColorSet($theme->getNormalBg(), $theme->getNormalFg());
        $this->menu = new ColorSet($theme->getMenuBg(), $theme->getMenuFg());
        $this->status = new ColorSet($theme->getStatusBg(), $theme->getStatusFg());
        $this->selected = new ColorSet($theme->getSelectedBg(), $theme->getSelectedFg());
        $this->input = new ColorSet($theme->getInputBg(), $theme->getInputFg());
        $this->borders = new BorderColorSet($theme->getActiveBorder(), $theme->getInactiveBorder());
        $this->semantic = new SemanticColorSet(
            $theme->getErrorText(),
            $theme->getWarningText(),
            $theme->getHighlightText(),
            $theme->getScrollbar(),
        );
    }

    public function getTheme(): ThemeInterface
    {
        return $this->theme;
    }

    // Direct accessors (for backward compatibility / convenience)
    public function getNormalText(): string
    {
        return $this->normal->combined();
    }

    public function getSelectedText(): string
    {
        return $this->selected->combined();
    }

    public function getMenuText(): string
    {
        return $this->menu->combined();
    }

    public function getStatusText(): string
    {
        return $this->status->combined();
    }

    public function getInputText(): string
    {
        return $this->input->combined();
    }

    // Value object accessors
    public function getNormalColors(): ColorSet
    {
        return $this->normal;
    }

    public function getMenuColors(): ColorSet
    {
        return $this->menu;
    }

    public function getStatusColors(): ColorSet
    {
        return $this->status;
    }

    public function getSelectedColors(): ColorSet
    {
        return $this->selected;
    }

    public function getInputColors(): ColorSet
    {
        return $this->input;
    }

    public function getBorderColors(): BorderColorSet
    {
        return $this->borders;
    }

    public function getSemanticColors(): SemanticColorSet
    {
        return $this->semantic;
    }

    // Additional convenience methods
    public function getActiveBorder(): string
    {
        return $this->borders->active;
    }

    public function getInactiveBorder(): string
    {
        return $this->borders->inactive;
    }

    public function getErrorText(): string
    {
        return $this->semantic->error;
    }

    public function getWarningText(): string
    {
        return $this->semantic->warning;
    }

    public function getHighlightText(): string
    {
        return $this->semantic->highlight;
    }

    public function getScrollbar(): string
    {
        return $this->semantic->scrollbar;
    }

    // Special accessors from theme
    public function getMenuHotkey(): string
    {
        return $this->theme->getMenuHotkey();
    }

    public function getStatusKey(): string
    {
        return $this->theme->getStatusKey();
    }

    public function getInputCursor(): string
    {
        return $this->theme->getInputCursor();
    }

    public function getNormalBg(): string
    {
        return $this->normal->background;
    }

    public function getMutedText(): string
    {
        return $this->theme->getMutedText();
    }
}
