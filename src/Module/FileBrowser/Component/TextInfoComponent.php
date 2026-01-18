<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\FileBrowser\Component;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Simple text info component - displays formatted text lines
 * Used for file/directory metadata display
 */
final class TextInfoComponent extends AbstractComponent
{
    /**
     * @param array<string> $lines Lines of text to display
     */
    public function __construct(private array $lines = []) {}

    /**
     * Set text lines
     *
     * @param array<string> $lines
     */
    public function setLines(array $lines): void
    {
        $this->lines = $lines;
    }

    /**
     * Clear all lines
     */
    public function clear(): void
    {
        $this->lines = [];
    }

    #[\Override]
    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);

        if (empty($this->lines)) {
            return;
        }

        $linesToRender = \min($height, \count($this->lines));

        for ($i = 0; $i < $linesToRender; $i++) {
            $line = $this->lines[$i];

            // Truncate or pad line to fit width
            $displayText = \mb_substr($line, 0, $width);
            $displayText = \str_pad($displayText, $width);

            $renderer->writeAt($x, $y + $i, $displayText, ColorScheme::$NORMAL_TEXT);
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        // This component doesn't handle input
        return false;
    }

    #[\Override]
    public function getMinSize(): array
    {
        return ['width' => 20, 'height' => \count($this->lines)];
    }
}
