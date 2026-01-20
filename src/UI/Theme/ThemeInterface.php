<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Theme;

/**
 * Interface for UI themes
 */
interface ThemeInterface
{
    /**
     * Get theme name
     */
    public function getName(): string;

    /**
     * Normal background color
     */
    public function getNormalBg(): string;

    /**
     * Normal foreground color
     */
    public function getNormalFg(): string;

    /**
     * Normal text (combined bg + fg)
     */
    public function getNormalText(): string;

    /**
     * Menu bar background
     */
    public function getMenuBg(): string;

    /**
     * Menu bar foreground
     */
    public function getMenuFg(): string;

    /**
     * Menu bar text (combined)
     */
    public function getMenuText(): string;

    /**
     * Menu bar hotkey color
     */
    public function getMenuHotkey(): string;

    /**
     * Status bar background
     */
    public function getStatusBg(): string;

    /**
     * Status bar foreground
     */
    public function getStatusFg(): string;

    /**
     * Status bar text (combined)
     */
    public function getStatusText(): string;

    /**
     * Status bar key highlight
     */
    public function getStatusKey(): string;

    /**
     * Selection background
     */
    public function getSelectedBg(): string;

    /**
     * Selection foreground
     */
    public function getSelectedFg(): string;

    /**
     * Selection text (combined)
     */
    public function getSelectedText(): string;

    /**
     * Active border color
     */
    public function getActiveBorder(): string;

    /**
     * Inactive border color
     */
    public function getInactiveBorder(): string;

    /**
     * Input field background
     */
    public function getInputBg(): string;

    /**
     * Input field foreground
     */
    public function getInputFg(): string;

    /**
     * Input field text (combined)
     */
    public function getInputText(): string;

    /**
     * Input cursor style
     */
    public function getInputCursor(): string;

    /**
     * Scrollbar color
     */
    public function getScrollbar(): string;

    /**
     * Error text color
     */
    public function getErrorText(): string;

    /**
     * Warning text color
     */
    public function getWarningText(): string;

    /**
     * Highlighted/emphasized text color
     * Used for: directories, direct dependencies, script names, etc.
     */
    public function getHighlightText(): string;

    /**
     * Muted/dimmed text color
     * Used for: secondary information, hints, disabled items
     */
    public function getMutedText(): string;
}
