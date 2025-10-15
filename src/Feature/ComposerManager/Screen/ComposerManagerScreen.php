<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Screen;

use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;
use Butschster\Commander\Feature\ComposerManager\Tab\InstalledPackagesTab;
use Butschster\Commander\Feature\ComposerManager\Tab\OutdatedPackagesTab;
use Butschster\Commander\Feature\ComposerManager\Tab\ScriptsTab;
use Butschster\Commander\Feature\ComposerManager\Tab\SecurityAuditTab;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Container\TabContainer;
use Butschster\Commander\UI\Component\Layout\StatusBar;
use Butschster\Commander\UI\Screen\Attribute\Metadata;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\UI\Screen\ScreenManager;
use Butschster\Commander\UI\Screen\ScreenManagerAware;

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
 * - Run composer scripts
 */
#[Metadata(
    name: 'composer_manager',
    title: 'Composer Manager',
    description: 'Manage Composer packages and dependencies',
    category: 'system',
    priority: 20,
)]
final class ComposerManagerScreen implements ScreenInterface, ScreenManagerAware
{
    private TabContainer $tabContainer;

    public function __construct(
        private readonly ComposerService $composerService,
    ) {}

    public function setScreenManager(ScreenManager $screenManager): void
    {
        // Create tabs
        $installedTab = new InstalledPackagesTab($this->composerService, $screenManager);
        $outdatedTab = new OutdatedPackagesTab($this->composerService);
        $securityTab = new SecurityAuditTab($this->composerService);
        $scriptsTab = new ScriptsTab($this->composerService);

        // Create tab container with all tabs
        $this->tabContainer = new TabContainer([
            $scriptsTab,
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

    // ScreenInterface implementation

    public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
    {
        // Get actual size if not provided
        if ($width === null || $height === null) {
            $size = $renderer->getSize();
            $width ??= $size['width'] - $x;
            $height ??= $size['height'] - $y;
        }

        // Render tab container (includes tab headers, content, and status bar)
        $this->tabContainer->render($renderer, $x, $y, $width, $height);
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
}
