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
    /** Default F-key assignments for categories */
    private const array DEFAULT_FKEY_MAP = [
        'help' => 'F1',
        'tools' => 'F2',
        'files' => 'F3',
        'system' => 'F4',
    ];

    /** @var array<string, MenuDefinition> */
    private array $menus = [];

    /** @var array<string, string> Custom F-key assignments */
    private array $fkeyMap = [];

    public function __construct(
        private readonly ScreenRegistry $registry,
    ) {
        $this->fkeyMap = self::DEFAULT_FKEY_MAP;
    }

    /**
     * Static factory: create builder from registry
     */
    public static function fromRegistry(ScreenRegistry $registry): self
    {
        return new self($registry);
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

        // Add standalone Quit menu with F10
        // Note: The quit action will be handled by Application via a special marker
        $this->menus['quit'] = new MenuDefinition(
            'Quit',
            'F10',
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

    /**
     * Get all menu definitions
     *
     * @return array<string, MenuDefinition>
     */
    public function getMenus(): array
    {
        return $this->menus;
    }
}
