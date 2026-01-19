<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\ComposerManager\Component;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Display\Spinner;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Full-panel loading state overlay with spinner and message.
 *
 * Use this to show feedback during slow operations like:
 * - composer outdated (3-10 seconds)
 * - composer audit (2-5 seconds)
 */
final class LoadingState
{
    private readonly Spinner $spinner;
    private ?string $message = null;
    private bool $isLoading = false;

    public function __construct()
    {
        $this->spinner = new Spinner(Spinner::STYLE_BRAILLE, 0.1);
    }

    /**
     * Start loading state with a message.
     */
    public function start(string $message): void
    {
        $this->message = $message;
        $this->isLoading = true;
        $this->spinner->start();
    }

    /**
     * Stop loading state.
     */
    public function stop(): void
    {
        $this->isLoading = false;
        $this->message = null;
        $this->spinner->stop();
    }

    /**
     * Check if currently loading.
     */
    public function isLoading(): bool
    {
        return $this->isLoading;
    }

    /**
     * Update spinner animation (call in update() loop).
     */
    public function update(): void
    {
        if ($this->isLoading) {
            $this->spinner->update();
        }
    }

    /**
     * Render the loading overlay centered in the given area.
     */
    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        if (!$this->isLoading || $this->message === null) {
            return;
        }

        // Calculate center position
        $spinnerFrame = $this->spinner->getCurrentFrame();
        $text = $spinnerFrame . ' ' . $this->message;
        $textWidth = \mb_strlen($text);

        // Draw semi-transparent overlay effect (dim the area)
        $dimColor = ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_BRIGHT_BLACK);
        for ($row = $y; $row < $y + $height; $row++) {
            $renderer->writeAt($x, $row, \str_repeat(' ', $width), $dimColor);
        }

        // Draw loading message box
        $boxWidth = $textWidth + 4;
        $boxX = $x + (int) (($width - $boxWidth) / 2);
        $centerY = $y + (int) ($height / 2);
        $boxY = $centerY - 1;

        $boxColor = ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_WHITE, ColorScheme::BOLD);

        // Box top
        $renderer->writeAt($boxX, $boxY, '┌' . \str_repeat('─', $boxWidth - 2) . '┐', $boxColor);
        // Box middle with text
        $renderer->writeAt($boxX, $boxY + 1, '│ ' . $text . ' │', $boxColor);
        // Box bottom
        $renderer->writeAt($boxX, $boxY + 2, '└' . \str_repeat('─', $boxWidth - 2) . '┘', $boxColor);
    }
}
