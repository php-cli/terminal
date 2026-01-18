<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Menu;

use Butschster\Commander\Infrastructure\Keyboard\KeyBindingRegistryInterface;
use Butschster\Commander\UI\Screen\ScreenRegistry;

/**
 * Menu Builder - automatically builds menu system from registered screens
 *
 * Groups screens by category and assigns F-keys from the key binding registry.
 */
final class MenuBuilder
{
    /** @var array<string, MenuDefinition> */
    private array $menus = [];

    public function __construct(
        private readonly ScreenRegistry $registry,
        private readonly KeyBindingRegistryInterface $keyBindings,
    ) {}

    /**
     * Build menu system from registry
     *
     * @return array<string, MenuDefinition>
     */
    public function build(): array
    {
        $screensByCategory = $this->registry->getByCategory();

        $priority = 0;

        foreach ($screensByCategory as $category => $screens) {
            if (empty($screens)) {
                continue;
            }

            // Get F-key from registry using action ID convention: menu.{category}
            $actionId = 'menu.' . $category;
            $binding = $this->keyBindings->getPrimaryByActionId($actionId);
            $fkey = $binding?->combination;

            // Create menu items from screens
            $items = [];
            foreach ($screens as $metadata) {
                $items[] = MenuItem::screen(
                    $metadata->getDisplayText(),
                    $metadata->name,
                );
            }

            // Create menu definition
            $label = \ucfirst($category);
            $this->menus[$category] = new MenuDefinition(
                $label,
                $fkey,
                $items,
                $priority++,
            );
        }

        // Add standalone Quit menu - get F-key from registry (F12 from DefaultKeyBindings)
        $quitBinding = $this->keyBindings->getPrimaryByActionId('app.quit');
        $this->menus['quit'] = new MenuDefinition(
            'Quit',
            $quitBinding?->combination,
            [
                MenuItem::action('Quit', static function (): void {
                    // This is a marker action that MenuSystem will recognize
                    // It will be handled by Application to actually stop
                }, 'q'),
            ],
            999, // High priority to appear last
        );

        return $this->menus;
    }

    /**
     * Add custom menu item to category
     *
     * @param string $category Category name
     * @param MenuItem $item Menu item to add
     */
    public function addItem(string $category, MenuItem $item): self
    {
        if (!isset($this->menus[$category])) {
            throw new \RuntimeException("Category not found: {$category}");
        }

        $menu = $this->menus[$category];
        $items = $menu->items;
        $items[] = $item;

        $this->menus[$category] = new MenuDefinition(
            $menu->label,
            $menu->fkey,
            $items,
            $menu->priority,
        );

        return $this;
    }

    /**
     * Add separator to category menu
     *
     * @param string $category Category name
     */
    public function addSeparator(string $category): self
    {
        return $this->addItem($category, MenuItem::separator());
    }
}
