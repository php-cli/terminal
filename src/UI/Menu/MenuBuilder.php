<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Menu;

use Butschster\Commander\UI\Screen\ScreenRegistry;

/**
 * Menu Builder - automatically builds menu system from registered screens
 *
 * Groups screens by category and assigns F-keys automatically.
 */
final class MenuBuilder
{
    /** @var array<string, MenuDefinition> */
    private array $menus = [];

    /** Default F-key assignments for categories */
    private const array DEFAULT_FKEY_MAP = [
        'tools' => 'F9',
        'files' => 'F10',
        'system' => 'F11',
        'help' => 'F1',
    ];

    /** @var array<string, string> Custom F-key assignments */
    private array $fkeyMap = [];

    public function __construct(
        private readonly ScreenRegistry $registry,
    ) {
        $this->fkeyMap = self::DEFAULT_FKEY_MAP;
    }

    /**
     * Set custom F-key mapping for categories
     *
     * @param array<string, string> $map Category => F-key mapping
     */
    public function withFKeys(array $map): self
    {
        $this->fkeyMap = \array_merge($this->fkeyMap, $map);
        return $this;
    }

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

            // Get F-key for category (if assigned)
            $fkey = $this->fkeyMap[$category] ?? null;

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

        // Add quit menu item to last menu
        if (!empty($this->menus)) {
            $lastCategory = \array_key_last($this->menus);
            $lastMenu = $this->menus[$lastCategory];

            // Add separator and quit item
            $items = $lastMenu->items;
            $items[] = MenuItem::separator();
            $items[] = MenuItem::action('Quit', fn() => null, 'q');

            $this->menus[$lastCategory] = new MenuDefinition(
                $lastMenu->label,
                $lastMenu->fkey,
                $items,
                $lastMenu->priority,
            );
        }

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

    /**
     * Get all menu definitions
     *
     * @return array<string, MenuDefinition>
     */
    public function getMenus(): array
    {
        return $this->menus;
    }

    /**
     * Static factory: create builder from registry
     */
    public static function fromRegistry(ScreenRegistry $registry): self
    {
        return new self($registry);
    }
}
