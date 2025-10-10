<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Container;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Component\ComponentInterface;
use Butschster\Commander\UI\Component\Layout\StatusBar;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Tab Container Component
 *
 * Manages multiple tabs with keyboard navigation (Ctrl+Left/Right)
 * and renders the active tab's content.
 *
 * Example:
 * ```php
 * $tabContainer = new TabContainer([
 *     new InstalledTab(),
 *     new OutdatedTab(),
 *     new SecurityTab(),
 * ]);
 * ```
 */
final class TabContainer extends AbstractComponent
{
    private int $activeTabIndex = 0;

    /** @var array<TabInterface> */
    private array $tabs = [];

    private ?StatusBar $statusBar = null;
    private int $statusBarHeight = 1;

    /**
     * @param array<TabInterface> $tabs
     */
    public function __construct(array $tabs = [])
    {
        $this->tabs = $tabs;

        // Activate first tab
        if (!empty($this->tabs)) {
            $this->tabs[0]->onActivate();
        }
    }

    /**
     * Add a tab
     */
    public function addTab(TabInterface $tab): void
    {
        $this->tabs[] = $tab;

        // If this is the first tab, activate it
        if (count($this->tabs) === 1) {
            $tab->onActivate();
        }
    }

    /**
     * Get active tab
     */
    public function getActiveTab(): ?TabInterface
    {
        return $this->tabs[$this->activeTabIndex] ?? null;
    }

    /**
     * Get active tab index
     */
    public function getActiveTabIndex(): int
    {
        return $this->activeTabIndex;
    }

    /**
     * Switch to specific tab by index
     */
    public function switchToTab(int $index): void
    {
        if ($index < 0 || $index >= count($this->tabs)) {
            return;
        }

        if ($index === $this->activeTabIndex) {
            return;
        }

        // Deactivate current tab
        if (isset($this->tabs[$this->activeTabIndex])) {
            $this->tabs[$this->activeTabIndex]->onDeactivate();
        }

        // Switch to new tab
        $this->activeTabIndex = $index;
        $this->tabs[$this->activeTabIndex]->onActivate();

        // Update status bar
        $this->updateStatusBar();
    }

    /**
     * Switch to next tab
     */
    public function nextTab(): void
    {
        $nextIndex = ($this->activeTabIndex + 1) % count($this->tabs);
        $this->switchToTab($nextIndex);
    }

    /**
     * Switch to previous tab
     */
    public function previousTab(): void
    {
        $prevIndex = ($this->activeTabIndex - 1 + count($this->tabs)) % count($this->tabs);
        $this->switchToTab($prevIndex);
    }

    /**
     * Set status bar (optional)
     */
    public function setStatusBar(?StatusBar $statusBar, int $height = 1): void
    {
        $this->statusBar = $statusBar;
        $this->statusBarHeight = $height;
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);

        $contentHeight = $height;
        $statusBarY = $y + $height - $this->statusBarHeight;

        // Reserve space for status bar if present
        if ($this->statusBar !== null) {
            $contentHeight -= $this->statusBarHeight;
        }

        // Render tab headers
        $headerHeight = 1;
        $this->renderTabHeaders($renderer, $x, $y, $width, $headerHeight);

        // Render active tab content
        $activeTab = $this->getActiveTab();
        if ($activeTab !== null) {
            $activeTab->render(
                $renderer,
                $x,
                $y + $headerHeight,
                $width,
                $contentHeight - $headerHeight,
            );
        }

        // Render status bar
        if ($this->statusBar !== null) {
            $this->statusBar->render($renderer, $x, $statusBarY, $width, $this->statusBarHeight);
        }
    }

    public function handleInput(string $key): bool
    {
        // Tab navigation with Ctrl+Left/Right
        if ($key === 'CTRL_LEFT') {
            $this->previousTab();
            return true;
        }

        if ($key === 'CTRL_RIGHT') {
            $this->nextTab();
            return true;
        }

        // Delegate to active tab
        $activeTab = $this->getActiveTab();
        if ($activeTab !== null) {
            return $activeTab->handleInput($key);
        }

        return false;
    }

    public function setFocused(bool $focused): void
    {
        parent::setFocused($focused);

        // Propagate focus to active tab
        $activeTab = $this->getActiveTab();
        if ($activeTab !== null) {
            $activeTab->setFocused($focused);
        }
    }

    /**
     * Render tab headers
     */
    private function renderTabHeaders(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $currentX = $x;

        foreach ($this->tabs as $index => $tab) {
            $isActive = $index === $this->activeTabIndex;
            $tabTitle = $tab->getTitle();

            // Calculate tab width (title + padding + separators)
            $tabWidth = mb_strlen($tabTitle) + 4; // 2 spaces padding + 2 for brackets/separators

            // Choose colors based on active state
            if ($isActive) {
                $bgColor = ColorScheme::BG_CYAN;
                $fgColor = ColorScheme::FG_BLACK;
                $style = ColorScheme::BOLD;
            } else {
                $bgColor = ColorScheme::BG_BLUE;
                $fgColor = ColorScheme::FG_WHITE;
                $style = '';
            }

            $color = ColorScheme::combine($bgColor, $fgColor, $style);

            // Render tab
            $tabText = $isActive ? "[ {$tabTitle} ]" : "  {$tabTitle}  ";
            $renderer->writeAt($currentX, $y, $tabText, $color);

            $currentX += $tabWidth;

            // Stop if we exceed width
            if ($currentX >= $x + $width) {
                break;
            }
        }

        // Fill remaining space with background
        if ($currentX < $x + $width) {
            $remaining = $x + $width - $currentX;
            $renderer->writeAt(
                $currentX,
                $y,
                str_repeat(' ', $remaining),
                ColorScheme::NORMAL_TEXT,
            );
        }
    }

    /**
     * Update status bar with current tab's shortcuts
     */
    private function updateStatusBar(): void
    {
        if ($this->statusBar === null) {
            return;
        }

        $activeTab = $this->getActiveTab();
        if ($activeTab === null) {
            return;
        }

        // Get tab-specific shortcuts and merge with navigation shortcuts
        $shortcuts = array_merge(
            [
                'Ctrl+←/→' => 'switch Tab',
            ],
            $activeTab->getShortcuts(),
        );

        $this->statusBar = new StatusBar($shortcuts);
    }
}
