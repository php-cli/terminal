<?php

declare(strict_types=1);

namespace Butschster\Commander\Component;

use Butschster\Commander\Service\Renderer;
use Butschster\Commander\Theme\ColorScheme;

/**
 * Top menu bar component (MC-style)
 */
final class MenuBar extends AbstractComponent
{
    /**
     * @param array<string, string> $items Menu items (key => label)
     */
    public function __construct(
        private array $items = [],
    ) {}

    /**
     * Set menu items
     *
     * @param array<string, string> $items
     */
    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);

        // Fill background
        $renderer->fillRect($x, $y, $width, 1, ' ', ColorScheme::MENU_TEXT);

        // Render menu items
        $currentX = $x + 1;

        foreach ($this->items as $key => $label) {
            if ($currentX >= $x + $width - 1) {
                break;
            }

            // Render key (highlighted)
            $renderer->writeAt($currentX, $y, $key, ColorScheme::MENU_HOTKEY);
            $currentX += mb_strlen($key);

            // Render label
            $renderer->writeAt($currentX, $y, $label, ColorScheme::MENU_TEXT);
            $currentX += mb_strlen($label) + 2; // Add spacing
        }
    }

    public function handleInput(string $key): bool
    {
        // MenuBar typically doesn't handle input directly
        // Input is handled by the application or screen
        return false;
    }

    public function getMinSize(): array
    {
        return ['width' => 10, 'height' => 1];
    }
}
