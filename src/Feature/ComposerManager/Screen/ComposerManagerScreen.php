<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Screen;

use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;
use Butschster\Commander\Feature\ComposerManager\Tab\InstalledPackagesTab;
use Butschster\Commander\Feature\ComposerManager\Tab\OutdatedPackagesTab;
use Butschster\Commander\Feature\ComposerManager\Tab\SecurityAuditTab;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Container\TabContainer;
use Butschster\Commander\UI\Component\Layout\StatusBar;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\UI\Screen\ScreenManager;

/**
 * Composer Manager Screen
 *
 * Refactored to use TabContainer with separate tab classes.
 * Navigate between tabs using Ctrl+Left/Right arrows.
 *
 * Features:
 * - View installed packages (composer show)
 * - Check for outdated packages (composer outdated)
 * - Security audit (composer audit)
 */
final class ComposerManagerScreen implements ScreenInterface
{
    private TabContainer $tabContainer;

    public function __construct(
        private readonly ComposerService $composerService,
    ) {
        $this->initializeComponents();
    }

    public function setScreenManager(ScreenManager $screenManager): void
    {
        // Pass screen manager to tabs that need it (for navigation)
        $activeTab = $this->tabContainer->getActiveTab();
        if ($activeTab instanceof InstalledPackagesTab) {
            // Since we can't modify the constructor, we'll need to handle this differently
            // For now, we'll create a new instance when setting screen manager
            $this->tabContainer = new TabContainer([
                new InstalledPackagesTab($this->composerService, $screenManager),
                new OutdatedPackagesTab($this->composerService),
                new SecurityAuditTab($this->composerService),
            ]);

            // Set status bar
            $statusBar = new StatusBar([
                'Ctrl+←/→' => 'Switch Tab',
            ]);
            $this->tabContainer->setStatusBar($statusBar, 1);
        }
    }

    // ScreenInterface implementation

    public function render(Renderer $renderer): void
    {
        $size = $renderer->getSize();

        // Render tab container (includes tab headers, content, and status bar)
        $this->tabContainer->render($renderer, 0, 1, $size['width'], $size['height'] - 1);
    }

    public function handleInput(string $key): bool
    {
        // Delegate all input to tab container
        // It will handle tab switching (Ctrl+Left/Right) and forward to active tab
        return $this->tabContainer->handleInput($key);
    }

    public function onActivate(): void
    {
        $this->tabContainer->setFocused(true);
    }

    public function onDeactivate(): void
    {
        $this->tabContainer->setFocused(false);
    }

    public function update(): void
    {
        // Update active tab
        $activeTab = $this->tabContainer->getActiveTab();
        if ($activeTab !== null) {
            $activeTab->update();
        }
    }

    public function getTitle(): string
    {
        return 'Composer Manager';
    }

    private function initializeComponents(): void
    {
        // Create tabs
        $installedTab = new InstalledPackagesTab($this->composerService);
        $outdatedTab = new OutdatedPackagesTab($this->composerService);
        $securityTab = new SecurityAuditTab($this->composerService);

        // Create tab container with all tabs
        $this->tabContainer = new TabContainer([
            $installedTab,
            $outdatedTab,
            $securityTab,
        ]);

        // Set status bar (will be updated by TabContainer based on active tab)
        $statusBar = new StatusBar([
            'Ctrl+←/→' => 'Switch Tab',
        ]);
        $this->tabContainer->setStatusBar($statusBar, 1);
    }
}
