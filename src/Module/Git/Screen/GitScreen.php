<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\Git\Screen;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\Module\Git\Service\GitService;
use Butschster\Commander\Module\Git\Tab\BranchesTab;
use Butschster\Commander\Module\Git\Tab\StatusTab;
use Butschster\Commander\Module\Git\Tab\TagsTab;
use Butschster\Commander\UI\Component\Container\TabContainer;
use Butschster\Commander\UI\Component\Layout\StatusBar;
use Butschster\Commander\UI\Screen\Attribute\Metadata;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\UI\Screen\ScreenManager;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Git Repository Screen
 *
 * Main screen for Git operations with tabs:
 * - Status: View and stage/unstage changes
 * - Branches: View and checkout branches
 * - Tags: View all tags
 *
 * Navigate between tabs using Ctrl+Left/Right arrows.
 */
#[Metadata(
    name: 'git',
    title: 'Git',
    description: 'Git repository management',
    category: 'tools',
    priority: 30,
)]
final class GitScreen implements ScreenInterface
{
    private ?TabContainer $tabContainer = null;
    private bool $isValidRepo = false;

    public function __construct(
        private readonly GitService $gitService,
        private readonly ScreenManager $screenManager,
    ) {}

    #[\Override]
    public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
    {
        if ($width === null || $height === null) {
            $size = $renderer->getSize();
            $width ??= $size['width'] - $x;
            $height ??= $size['height'] - $y;
        }

        if (!$this->isValidRepo) {
            $this->renderNotARepository($renderer, $x, $y, $width, $height);
            return;
        }

        $this->tabContainer?->render($renderer, $x, $y, $width, $height);
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        if (!$this->isValidRepo) {
            // ESC to go back
            if ($key === 'escape') {
                $this->screenManager->popScreen();
                return true;
            }
            return false;
        }

        return $this->tabContainer?->handleInput($key) ?? false;
    }

    #[\Override]
    public function onActivate(): void
    {
        $this->isValidRepo = $this->gitService->isValidRepository();

        if ($this->isValidRepo) {
            $this->initializeTabs();
            $this->tabContainer?->setFocused(true);
        }
    }

    #[\Override]
    public function onDeactivate(): void
    {
        $this->tabContainer?->setFocused(false);
    }

    #[\Override]
    public function update(): void
    {
        $activeTab = $this->tabContainer?->getActiveTab();
        $activeTab?->update();
    }

    #[\Override]
    public function getTitle(): string
    {
        $branch = $this->gitService->getCurrentBranch();
        $repoName = \basename($this->gitService->getRepositoryPath());

        if ($branch !== null) {
            return "Git: {$repoName} ({$branch})";
        }

        return "Git: {$repoName}";
    }

    private function initializeTabs(): void
    {
        $statusTab = new StatusTab($this->gitService);
        $branchesTab = new BranchesTab($this->gitService);
        $tagsTab = new TagsTab($this->gitService);

        $this->tabContainer = new TabContainer([
            $statusTab,
            $branchesTab,
            $tagsTab,
        ]);

        $statusBar = new StatusBar([
            'Ctrl+←/→' => 'Switch Tab',
        ]);

        $this->tabContainer->setStatusBar($statusBar, 1);
    }

    private function renderNotARepository(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $centerY = $y + (int) ($height / 2);
        $message = 'Not a Git repository';
        $path = $this->gitService->getRepositoryPath();
        $hint = 'Press ESC to go back';

        $renderer->writeAt(
            $x + (int) (($width - \strlen($message)) / 2),
            $centerY - 1,
            $message,
            ColorScheme::$ERROR_TEXT,
        );

        $renderer->writeAt(
            $x + (int) (($width - \strlen($path)) / 2),
            $centerY + 1,
            $path,
            ColorScheme::$MUTED_TEXT,
        );

        $renderer->writeAt(
            $x + (int) (($width - \strlen($hint)) / 2),
            $centerY + 3,
            $hint,
            ColorScheme::$MUTED_TEXT,
        );
    }
}
