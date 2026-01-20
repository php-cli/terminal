<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\ComposerManager\Component;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\ComponentInterface;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Context menu for package actions.
 */
final class PackageActionMenu implements ComponentInterface
{
    private bool $visible = false;
    private ?array $package = null;
    private int $selectedIndex = 0;

    /** @var array<array{label: string, action: string, enabled: bool}> */
    private array $items = [];

    private ?\Closure $onAction = null;

    /**
     * @param array{name: string, isDirect: bool, outdated: ?string, source?: ?string} $package
     */
    public function show(array $package, bool $hasUpdate = false): void
    {
        $this->package = $package;
        $this->visible = true;
        $this->selectedIndex = 0;

        // Build menu items based on package state
        $this->items = [
            [
                'label' => 'View Details',
                'action' => 'details',
                'enabled' => true,
            ],
            [
                'label' => 'Update Package',
                'action' => 'update',
                'enabled' => $hasUpdate,
            ],
            [
                'label' => 'Remove Package',
                'action' => 'remove',
                'enabled' => $package['isDirect'] ?? false,
            ],
        ];

        // Find first enabled item
        foreach ($this->items as $i => $item) {
            if ($item['enabled']) {
                $this->selectedIndex = $i;
                break;
            }
        }
    }

    public function hide(): void
    {
        $this->visible = false;
        $this->package = null;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function onAction(callable $callback): self
    {
        $this->onAction = $callback(...);
        return $this;
    }

    #[\Override]
    public function render(Renderer $renderer, int $x, int $y, ?int $width, ?int $height): void
    {
        if (!$this->visible || $width === null || $height === null) {
            return;
        }

        $menuWidth = 25;
        $menuHeight = \count($this->items) + 2;

        // Position menu near center
        $menuX = $x + (int) (($width - $menuWidth) / 2);
        $menuY = $y + (int) (($height - $menuHeight) / 2);

        // Draw overlay
        $dimColor = ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_GRAY);
        for ($row = $y; $row < $y + $height; $row++) {
            $renderer->writeAt($x, $row, \str_repeat(' ', $width), $dimColor);
        }

        // Draw menu box
        $borderColor = ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::FG_WHITE);
        $contentColor = ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::FG_WHITE);
        $selectedColor = ColorScheme::combine(ColorScheme::BG_WHITE, ColorScheme::FG_BLACK, ColorScheme::BOLD);
        $disabledColor = ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::FG_GRAY);

        // Top border
        $renderer->writeAt($menuX, $menuY, '┌' . \str_repeat('─', $menuWidth - 2) . '┐', $borderColor);

        // Menu items
        foreach ($this->items as $i => $item) {
            $rowY = $menuY + 1 + (int) $i;
            $isSelected = $i === $this->selectedIndex;

            $label = $item['label'];
            $label = \str_pad($label, $menuWidth - 4);

            if (!$item['enabled']) {
                $color = $disabledColor;
                $prefix = '  ';
            } elseif ($isSelected) {
                $color = $selectedColor;
                $prefix = '> ';
            } else {
                $color = $contentColor;
                $prefix = '  ';
            }

            $renderer->writeAt($menuX, $rowY, '│', $borderColor);
            $renderer->writeAt($menuX + 1, $rowY, $prefix . $label, $color);
            $renderer->writeAt($menuX + $menuWidth - 1, $rowY, '│', $borderColor);
        }

        // Bottom border
        $renderer->writeAt($menuX, $menuY + $menuHeight - 1, '└' . \str_repeat('─', $menuWidth - 2) . '┘', $borderColor);
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        if (!$this->visible) {
            return false;
        }

        $input = KeyInput::from($key);

        // Navigation
        if ($input->is(Key::UP)) {
            $this->moveSelection(-1);
            return true;
        }

        if ($input->is(Key::DOWN)) {
            $this->moveSelection(1);
            return true;
        }

        // Select action
        if ($input->is(Key::ENTER)) {
            $item = $this->items[$this->selectedIndex];
            if ($item['enabled'] && $this->onAction !== null && $this->package !== null) {
                ($this->onAction)($item['action'], $this->package);
            }
            $this->hide();
            return true;
        }

        // Cancel
        if ($input->is(Key::ESCAPE)) {
            $this->hide();
            return true;
        }

        return true; // Consume all input when visible
    }

    #[\Override]
    public function setFocused(bool $focused): void {}

    #[\Override]
    public function isFocused(): bool
    {
        return $this->visible;
    }

    #[\Override]
    public function update(): void {}

    #[\Override]
    public function getMinSize(): array
    {
        return ['width' => 25, 'height' => 8];
    }

    private function moveSelection(int $direction): void
    {
        $count = \count($this->items);
        $startIndex = $this->selectedIndex;

        do {
            $this->selectedIndex = ($this->selectedIndex + $direction + $count) % $count;
            // Prevent infinite loop if all items are disabled
            if ($this->selectedIndex === $startIndex) {
                break;
            }
        } while (!$this->items[$this->selectedIndex]['enabled']);
    }
}
