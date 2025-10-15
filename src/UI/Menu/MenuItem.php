<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Menu;

/**
 * Menu Item - represents a single item in a dropdown menu
 */
final readonly class MenuItem
{
    public const string TYPE_SCREEN = 'screen';
    public const string TYPE_ACTION = 'action';
    public const string TYPE_SEPARATOR = 'separator';
    public const string TYPE_SUBMENU = 'submenu';

    /**
     * @param string $label Display label
     * @param string $type Item type (screen, action, separator, submenu)
     * @param string|null $screenName Screen name to navigate to (for TYPE_SCREEN)
     * @param \Closure|null $action Action to execute (for TYPE_ACTION)
     * @param array<MenuItem>|null $submenu Child menu items (for TYPE_SUBMENU)
     * @param string|null $hotkey Quick access key (e.g., 'c' for Copy)
     */
    public function __construct(
        public string $label,
        public string $type = self::TYPE_SCREEN,
        public ?string $screenName = null,
        public ?\Closure $action = null,
        public ?array $submenu = null,
        public ?string $hotkey = null,
    ) {}

    /**
     * Create a screen navigation item
     */
    public static function screen(string $label, string $screenName, ?string $hotkey = null): self
    {
        return new self($label, self::TYPE_SCREEN, $screenName, null, null, $hotkey);
    }

    /**
     * Create an action item
     */
    public static function action(string $label, callable $action, ?string $hotkey = null): self
    {
        return new self($label, self::TYPE_ACTION, null, $action, null, $hotkey);
    }

    /**
     * Create a separator
     */
    public static function separator(): self
    {
        return new self('─────────', self::TYPE_SEPARATOR);
    }

    /**
     * Create a submenu
     *
     * @param array<MenuItem> $items
     */
    public static function submenu(string $label, array $items, ?string $hotkey = null): self
    {
        return new self($label, self::TYPE_SUBMENU, null, null, $items, $hotkey);
    }

    /**
     * Check if item is a separator
     */
    public function isSeparator(): bool
    {
        return $this->type === self::TYPE_SEPARATOR;
    }

    /**
     * Check if item is a screen navigation
     */
    public function isScreen(): bool
    {
        return $this->type === self::TYPE_SCREEN;
    }

    /**
     * Check if item is an action
     */
    public function isAction(): bool
    {
        return $this->type === self::TYPE_ACTION;
    }

    /**
     * Check if item is a submenu
     */
    public function isSubmenu(): bool
    {
        return $this->type === self::TYPE_SUBMENU;
    }

    /**
     * Get hotkey match (first character if not explicitly set)
     */
    public function getHotkey(): ?string
    {
        if ($this->hotkey !== null) {
            return \mb_strtolower($this->hotkey);
        }

        if ($this->isSeparator()) {
            return null;
        }

        return \mb_strtolower(\mb_substr($this->label, 0, 1));
    }
}
