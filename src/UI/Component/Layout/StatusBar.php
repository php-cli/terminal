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

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);

        // Fill background
        $renderer->fillRect($x, $y, $width, 1, ' ', ColorScheme::STATUS_TEXT);

        // Render hints
        $currentX = $x;
        $spacing = 1;

        foreach ($this->hints as $key => $description) {
            $itemWidth = mb_strlen($key) + mb_strlen($description) + $spacing;

            if ($currentX + $itemWidth >= $x + $width) {
                break;
            }

            // Render key number/name (bold)
            $renderer->writeAt($currentX, $y, $key, ColorScheme::STATUS_KEY);
            $currentX += mb_strlen($key);

            // Render description
            $renderer->writeAt($currentX, $y, $description, ColorScheme::STATUS_TEXT);
            $currentX += mb_strlen($description) + $spacing;
        }
    }

    public function handleInput(string $key): bool
    {
        // StatusBar typically doesn't handle input directly
        return false;
    }

    public function getMinSize(): array
    {
        return ['width' => 10, 'height' => 1];
    }
}
