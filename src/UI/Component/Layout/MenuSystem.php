<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Layout;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Menu\MenuDefinition;
use Butschster\Commander\UI\Menu\MenuItem;
use Butschster\Commander\UI\Screen\ScreenManager;
use Butschster\Commander\UI\Screen\ScreenRegistry;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Complete menu system with top bar and dropdown menus
 *
 * Handles:
 * - Rendering top menu bar with F-key hints
 * - Opening dropdown menus on F-key press or click
 * - Navigation between menu items
 * - Screen navigation via menu items
 * - Custom action execution
 */
final class MenuSystem extends AbstractComponent
{
    /** @var array<MenuDefinition> Sorted menus for rendering */
    private array $sortedMenus = [];

    /** @var array<string, string> Raw key string to menu key mapping */
    private array $fkeyMap = [];

    private ?MenuDropdown $activeDropdown = null;
    private ?string $activeMenuKey = null;

    /** @var callable|null Callback when quit is requested */
    private $onQuit = null;

    /**
     * @param array<string, MenuDefinition> $menus Menu definitions
     * @param ScreenRegistry $registry Screen registry for navigation
     * @param ScreenManager $screenManager Screen manager for navigation
     */
    public function __construct(
        private array $menus,
        private readonly ScreenRegistry $registry,
        private readonly ScreenManager $screenManager,
    ) {
        $this->initializeMenus();
    }

    /**
     * Set callback for when quit is requested
     */
    public function onQuit(callable $callback): void
    {
        $this->onQuit = $callback;
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);

        // Only render the menu bar, not the dropdown
        // Dropdown will be rendered separately on top of screen content
        $this->renderMenuBar($renderer, $x, $y, $width);
    }

    /**
     * Render dropdown overlay (call this AFTER screen rendering)
     */
    public function renderDropdown(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        // Render active dropdown if present
        if ($this->activeDropdown !== null) {
            $this->activeDropdown->render($renderer, $x, $y, $width, $height);
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        // If dropdown is active, delegate to it first
        if ($this->activeDropdown !== null) {
            if ($this->activeDropdown->handleInput($key)) {
                return true;
            }

            // ESC or other keys might close dropdown
            if ($key === 'ESCAPE') {
                $this->closeDropdown();
                return true;
            }
        }

        // Handle F-key menu activation using raw key matching
        if (isset($this->fkeyMap[$key])) {
            $menuKey = $this->fkeyMap[$key];

            // Special handling for Quit menu - execute immediately without dropdown
            if ($menuKey === 'quit') {
                $menu = $this->menus[$menuKey] ?? null;
                if ($menu !== null) {
                    $firstItem = $menu->getFirstItem();
                    if ($firstItem !== null && $firstItem->isAction()) {
                        $this->handleMenuItemSelected($firstItem);
                        return true;
                    }
                }
            }

            // For other F-keys, open dropdown menu
            $this->openMenu($menuKey);
            return true;
        }

        return false;
    }

    #[\Override]
    public function getMinSize(): array
    {
        return ['width' => 10, 'height' => 1];
    }

    /**
     * Check if a dropdown menu is currently open
     */
    public function isDropdownOpen(): bool
    {
        return $this->activeDropdown !== null;
    }

    /**
     * Close any open dropdown
     */
    public function closeDropdown(): void
    {
        if ($this->activeDropdown !== null) {
            $this->removeChild($this->activeDropdown);
            $this->activeDropdown = null;
            $this->activeMenuKey = null;
        }
    }

    /**
     * Initialize menu system - sort menus and build F-key map
     */
    private function initializeMenus(): void
    {
        // Sort menus by priority
        $this->sortedMenus = $this->menus;
        \uasort($this->sortedMenus, static fn($a, $b) => $a->priority <=> $b->priority);

        // Build F-key map using KeyCombination::toRawKey() for matching
        foreach ($this->sortedMenus as $categoryKey => $menu) {
            if ($menu->fkey !== null) {
                $this->fkeyMap[$menu->fkey->toRawKey()] = $categoryKey;
            }
        }
    }

    /**
     * Render top menu bar
     */
    private function renderMenuBar(Renderer $renderer, int $x, int $y, int $width): void
    {
        // Fill background with cyan
        $menuBg = ColorScheme::combine(ColorScheme::RESET, ColorScheme::BG_CYAN, ColorScheme::FG_WHITE);
        $renderer->fillRect($x, $y, $width, 1, ' ', $menuBg);

        // Separate quit menu from other menus
        $leftMenus = [];
        $quitMenu = null;
        $quitKey = null;

        foreach ($this->sortedMenus as $key => $menu) {
            if ($menu->label === 'Quit') {
                $quitMenu = $menu;
                $quitKey = $key;
            } else {
                $leftMenus[$key] = $menu;
            }
        }

        // Render left-side menu items
        $currentX = $x + 1; // Start with 1 space from left edge

        foreach ($leftMenus as $key => $menu) {
            if ($currentX >= $x + $width - 20) { // Leave space for quit menu
                break;
            }

            $isActive = ($key === $this->activeMenuKey);

            // Active menu item: black background with bold white text
            // Inactive menu item: cyan background with bold white F-keys and normal white text
            if ($isActive) {
                $textColor = ColorScheme::combine(
                    ColorScheme::RESET,
                    ColorScheme::BG_BLACK,
                    ColorScheme::FG_WHITE,
                    ColorScheme::BOLD,
                );
            } else {
                // Inactive: cyan bg, bold white fg for F-keys, normal white for text
                $textColor = ColorScheme::combine(
                    ColorScheme::RESET,
                    ColorScheme::BG_CYAN,
                    ColorScheme::FG_WHITE,
                    ColorScheme::BOLD,
                );
            }

            // Render leading space (always)
            $renderer->writeAt($currentX, $y, ' ', $textColor);
            $currentX += 1;

            // Render F-key if present using KeyCombination's __toString()
            if ($menu->fkey !== null) {
                $fkeyText = (string) $menu->fkey;
                $renderer->writeAt($currentX, $y, $fkeyText, $textColor);
                $currentX += \mb_strlen($fkeyText);
            }

            // Space between F-key and label
            $renderer->writeAt($currentX, $y, ' ', $textColor);
            $currentX += 1;

            // Render menu label (white on cyan/black)
            $label = $menu->label;
            $renderer->writeAt($currentX, $y, $label, $textColor);
            $currentX += \mb_strlen($label);

            // Render trailing space (always)
            $renderer->writeAt($currentX, $y, ' ', $textColor);
            $currentX += 1;

            // Padding between menu items (~15px = ~3 spaces at typical terminal width)
            $currentX += 3;
        }

        // Render Quit menu on the right side
        if ($quitMenu !== null) {
            // Quit menu is never active (executes immediately)
            $textColor = ColorScheme::combine(
                ColorScheme::RESET,
                ColorScheme::BG_CYAN,
                ColorScheme::FG_WHITE,
                ColorScheme::BOLD,
            );

            $label = $quitMenu->label;
            $fkeyText = $quitMenu->fkey !== null ? (string) $quitMenu->fkey : '';
            // Calculate total width: leading space + F-key + space + label + trailing space
            $quitWidth = 1 + \mb_strlen($fkeyText) + 1 + \mb_strlen($label) + 1;
            $quitX = $x + $width - $quitWidth - 1; // Position from right edge with 1 space padding

            // Render leading space
            $renderer->writeAt($quitX, $y, ' ', $textColor);
            $quitX += 1;

            // Render F-key (F12)
            if ($quitMenu->fkey !== null) {
                $renderer->writeAt($quitX, $y, $fkeyText, $textColor);
                $quitX += \mb_strlen($fkeyText);
            }

            // Space between F-key and label
            $renderer->writeAt($quitX, $y, ' ', $textColor);
            $quitX += 1;

            // Render label
            $renderer->writeAt($quitX, $y, $label, $textColor);
            $quitX += \mb_strlen($label);

            // Render trailing space
            $renderer->writeAt($quitX, $y, ' ', $textColor);
        }
    }

    /**
     * Open dropdown menu for given menu key
     */
    private function openMenu(string $menuKey): void
    {
        if (!isset($this->menus[$menuKey])) {
            return;
        }

        // Close existing dropdown
        $this->closeDropdown();

        $menu = $this->menus[$menuKey];

        // Calculate dropdown position (below menu label)
        $menuX = $this->calculateMenuPosition($menuKey);
        $menuY = $this->y + 1; // Below menu bar

        // Create dropdown
        $this->activeDropdown = new MenuDropdown(
            $menu->items,
            $menuX,
            $menuY,
        );

        $this->activeDropdown->setFocused(true);
        $this->addChild($this->activeDropdown);

        // Set callbacks
        $this->activeDropdown->onSelect(function (MenuItem $item): void {
            $this->handleMenuItemSelected($item);
        });

        $this->activeDropdown->onClose(function (): void {
            $this->closeDropdown();
        });

        $this->activeMenuKey = $menuKey;
    }

    /**
     * Calculate X position for menu dropdown
     */
    private function calculateMenuPosition(string $targetKey): int
    {
        $x = $this->x + 1;

        foreach ($this->sortedMenus as $key => $menu) {
            if ($key === $targetKey) {
                return $x;
            }

            // Account for F-key width using KeyCombination's __toString()
            if ($menu->fkey !== null) {
                $x += \mb_strlen((string) $menu->fkey);
            }

            // Account for label width + spacing
            $x += \mb_strlen(' ' . $menu->label . ' ') + 1;
        }

        return $x;
    }

    /**
     * Handle menu item selection
     */
    private function handleMenuItemSelected(MenuItem $item): void
    {
        // Close dropdown first
        $this->closeDropdown();

        // Execute item action based on type
        if ($item->isScreen()) {
            $this->navigateToScreen($item->screenName);
        } elseif ($item->isAction()) {
            // Check if this is the Quit action
            if ($item->label === 'Quit' && $this->onQuit !== null) {
                ($this->onQuit)();
            } elseif ($item->action !== null) {
                ($item->action)();
            }
        }
    }

    /**
     * Navigate to screen by name
     */
    private function navigateToScreen(?string $screenName): void
    {
        if ($screenName === null) {
            return;
        }

        $screen = $this->registry->getScreen($screenName);

        if ($screen === null) {
            return;
        }

        // Check if we're already on this screen
        $currentScreen = $this->screenManager->getCurrentScreen();
        if ($currentScreen !== null && $currentScreen::class === $screen::class) {
            // Already on this screen, don't navigate
            return;
        }

        // Navigate to screen
        $this->screenManager->pushScreen($screen);
    }
}
