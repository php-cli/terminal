<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Layout;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Bottom status bar component (MC-style)
 * Shows function key hints
 */
final class StatusBar extends AbstractComponent
{
    /**
     * @param array<string, string> $hints Key hints (key => description)
     */
    public function __construct(
        private array $hints = [],
    ) {}

    /**
     * Set key hints
     *
     * @param array<string, string> $hints
     */
    public function setHints(array $hints): void
    {
        $this->hints = $hints;
    }

    #[\Override]
    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);

        // Fill background
        $renderer->fillRect($x, $y, $width, 1, ' ', ColorScheme::$STATUS_TEXT);

        // Render hints
        $currentX = $x + 1; // Add left padding
        $itemSpacing = 2; // Space between items

        foreach ($this->hints as $key => $description) {
            // Ensure key and description are strings
            $keyStr = (string) $key;
            $descStr = (string) $description;

            // Calculate item width: "F1" + "Help" + space
            $itemWidth = \mb_strlen($keyStr) + \mb_strlen($descStr) + 1; // 1 for space between key and description

            // Check if we have room for this item
            if ($currentX + $itemWidth >= $x + $width - 1) {
                break;
            }

            // Render key number/name (bold white)
            $renderer->writeAt($currentX, $y, $keyStr, ColorScheme::$STATUS_KEY);
            $currentX += \mb_strlen($keyStr);

            // Render description (black text on cyan)
            $renderer->writeAt($currentX, $y, $descStr, ColorScheme::$STATUS_TEXT);
            $currentX += \mb_strlen($descStr) + $itemSpacing;
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        // StatusBar typically doesn't handle input directly
        return false;
    }

    #[\Override]
    public function getMinSize(): array
    {
        return ['width' => 10, 'height' => 1];
    }
}
