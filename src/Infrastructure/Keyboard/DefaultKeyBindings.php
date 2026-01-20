<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Keyboard;

/**
 * Registers default keyboard bindings for the application.
 *
 * Call DefaultKeyBindings::register() to populate a registry
 * with standard application shortcuts.
 */
final class DefaultKeyBindings
{
    /**
     * Register all default key bindings.
     */
    public static function register(KeyBindingRegistryInterface $registry): void
    {
        self::registerGlobalActions($registry);
        self::registerMenuNavigation($registry);
        self::registerNavigationKeys($registry);
    }

    /**
     * Create a pre-populated registry with default bindings.
     */
    public static function createRegistry(): KeyBindingRegistry
    {
        $registry = new KeyBindingRegistry();
        self::register($registry);

        return $registry;
    }

    private static function registerGlobalActions(KeyBindingRegistryInterface $registry): void
    {
        // Quit - PRIMARY: F12 (avoids GNOME Terminal conflict with F10)
        $registry->register(new KeyBinding(
            combination: KeyCombination::key(Key::F12),
            actionId: 'app.quit',
            description: 'Quit application',
            category: 'global',
            priority: 100,
        ));

        // Quit - ALTERNATIVE: Ctrl+Q (universal)
        $registry->register(new KeyBinding(
            combination: KeyCombination::ctrl(Key::Q),
            actionId: 'app.quit',
            description: 'Quit application',
            category: 'global',
            priority: 101,
        ));

        // Quit - ALTERNATIVE: Ctrl+C (interrupt)
        $registry->register(new KeyBinding(
            combination: KeyCombination::ctrl(Key::C),
            actionId: 'app.quit',
            description: 'Quit application',
            category: 'global',
            priority: 102,
        ));
    }

    private static function registerMenuNavigation(KeyBindingRegistryInterface $registry): void
    {
        $registry->register(new KeyBinding(
            combination: KeyCombination::key(Key::F1),
            actionId: 'menu.help',
            description: 'Help',
            category: 'menu',
            priority: 1,
        ));

        $registry->register(new KeyBinding(
            combination: KeyCombination::key(Key::F2),
            actionId: 'menu.tools',
            description: 'Tools menu',
            category: 'menu',
            priority: 2,
        ));

        $registry->register(new KeyBinding(
            combination: KeyCombination::key(Key::F3),
            actionId: 'menu.files',
            description: 'Files menu',
            category: 'menu',
            priority: 3,
        ));

        $registry->register(new KeyBinding(
            combination: KeyCombination::key(Key::F4),
            actionId: 'menu.system',
            description: 'System menu',
            category: 'menu',
            priority: 4,
        ));

        $registry->register(new KeyBinding(
            combination: KeyCombination::key(Key::F5),
            actionId: 'menu.composer',
            description: 'Composer menu',
            category: 'menu',
            priority: 5,
        ));
    }

    private static function registerNavigationKeys(KeyBindingRegistryInterface $registry): void
    {
        $registry->register(new KeyBinding(
            combination: KeyCombination::key(Key::ESCAPE),
            actionId: 'nav.back',
            description: 'Go back / Close',
            category: 'navigation',
            priority: 0,
        ));

        $registry->register(new KeyBinding(
            combination: KeyCombination::key(Key::TAB),
            actionId: 'nav.next_panel',
            description: 'Switch panel',
            category: 'navigation',
            priority: 1,
        ));
    }
}
