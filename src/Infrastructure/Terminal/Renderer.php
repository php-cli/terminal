<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Terminal;

use Butschster\Commander\Infrastructure\Terminal\Driver\TerminalDriverInterface;
use Butschster\Commander\UI\Theme\ThemeContext;

/**
 * Double-buffered renderer to prevent flickering
 *
 * Uses two buffers (back and front) and only updates changed cells.
 * This minimizes terminal writes and eliminates visual artifacts.
 */
final class Renderer
{
    /** @var array<int, array<int, array{char: string, color: string}>> Back buffer */
    private array $backBuffer = [];

    /** @var array<int, array<int, array{char: string, color: string}>> Front buffer (currently displayed) */
    private array $frontBuffer = [];

    private int $width;
    private int $height;

    /** Current cursor position (for optimization) */
    private int $cursorX = 0;

    private int $cursorY = 0;

    /** Current color code (for optimization) */
    private string $currentColor = '';

    /** Performance metrics */
    private int $cellsUpdated = 0;

    public function __construct(
        private readonly TerminalManager $terminal,
        private readonly ThemeContext $themeContext,
        private readonly ?TerminalDriverInterface $driver = null,
    ) {
        $size = $terminal->getSize();
        $this->width = $size['width'];
        $this->height = $size['height'];

        $this->initBuffers();
    }

    /**
     * Get the theme context
     */
    public function getThemeContext(): ThemeContext
    {
        return $this->themeContext;
    }

    /**
     * Start a new frame (clear back buffer)
     */
    public function beginFrame(): void
    {
        $this->cellsUpdated = 0;

        // Clear back buffer with default background
        $emptyCell = ['char' => ' ', 'color' => $this->themeContext->getNormalText()];

        for ($y = 0; $y < $this->height; $y++) {
            $this->backBuffer[$y] = \array_fill(0, $this->width, $emptyCell);
        }
    }

    /**
     * Write text at specific position
     *
     * @param int $x Left position (0-indexed)
     * @param int $y Top position (0-indexed)
     * @param string $text Text to write
     * @param string $colorCode ANSI color code
     */
    public function writeAt(int $x, int $y, string $text, string $colorCode): void
    {
        if ($y < 0 || $y >= $this->height) {
            return;
        }

        $textLength = \mb_strlen($text);

        for ($i = 0; $i < $textLength; $i++) {
            $posX = $x + $i;

            if ($posX < 0 || $posX >= $this->width) {
                continue;
            }

            $char = \mb_substr($text, $i, 1);
            $this->backBuffer[$y][$posX] = [
                'char' => $char,
                'color' => $colorCode,
            ];
        }
    }

    /**
     * Draw a box with borders using box-drawing characters
     */
    public function drawBox(int $x, int $y, int $width, int $height, string $colorCode): void
    {
        if ($width < 2 || $height < 2) {
            return;
        }

        // Corners
        $this->writeAt($x, $y, '┌', $colorCode);
        $this->writeAt($x + $width - 1, $y, '┐', $colorCode);
        $this->writeAt($x, $y + $height - 1, '└', $colorCode);
        $this->writeAt($x + $width - 1, $y + $height - 1, '┘', $colorCode);

        // Horizontal edges
        $horizontal = \str_repeat('─', $width - 2);
        $this->writeAt($x + 1, $y, $horizontal, $colorCode);
        $this->writeAt($x + 1, $y + $height - 1, $horizontal, $colorCode);

        // Vertical edges
        for ($i = 1; $i < $height - 1; $i++) {
            $this->writeAt($x, $y + $i, '│', $colorCode);
            $this->writeAt($x + $width - 1, $y + $i, '│', $colorCode);
        }
    }

    /**
     * Fill rectangle with character
     */
    public function fillRect(int $x, int $y, int $width, int $height, string $char, string $colorCode): void
    {
        for ($row = 0; $row < $height; $row++) {
            $posY = $y + $row;

            if ($posY < 0 || $posY >= $this->height) {
                continue;
            }

            $line = \str_repeat($char, $width);
            $this->writeAt($x, $posY, $line, $colorCode);
        }
    }

    /**
     * Finish frame and flush changes to terminal
     */
    public function endFrame(): void
    {
        $output = '';

        for ($y = 0; $y < $this->height; $y++) {
            for ($x = 0; $x < $this->width; $x++) {
                $backCell = $this->backBuffer[$y][$x];
                $frontCell = $this->frontBuffer[$y][$x];

                // Skip unchanged cells
                if ($backCell['char'] === $frontCell['char'] &&
                    $backCell['color'] === $frontCell['color']) {
                    continue;
                }

                // Move cursor if needed
                if ($this->cursorX !== $x || $this->cursorY !== $y) {
                    $output .= $this->moveCursor($x, $y);
                    $this->cursorX = $x;
                    $this->cursorY = $y;
                }

                // Change color if needed
                if ($this->currentColor !== $backCell['color']) {
                    $output .= $backCell['color'];
                    $this->currentColor = $backCell['color'];
                }

                // Write character
                $output .= $backCell['char'];
                $this->cursorX++;

                // Update front buffer
                $this->frontBuffer[$y][$x] = $backCell;
                $this->cellsUpdated++;
            }
        }

        // Flush all changes at once
        if ($output !== '') {
            if ($this->driver !== null) {
                $this->driver->write($output);
            } else {
                echo $output;
                \flush();
            }
        }
    }

    /**
     * Clear entire screen and buffers
     */
    public function clear(): void
    {
        $this->terminal->clearScreen();
        $this->initBuffers();
        $this->cursorX = 0;
        $this->cursorY = 0;
        $this->currentColor = '';
    }

    /**
     * Invalidate front buffer to force full redraw on next frame
     *
     * This should be called when switching screens to ensure old content
     * doesn't remain visible in areas the new screen doesn't write to.
     */
    public function invalidate(): void
    {
        // Set front buffer to a different state to force redraw
        $emptyCell = ['char' => ' ', 'color' => ''];

        for ($y = 0; $y < $this->height; $y++) {
            $this->frontBuffer[$y] = \array_fill(0, $this->width, $emptyCell);
        }
    }

    /**
     * Get terminal size
     *
     * @return array{width: int, height: int}
     */
    public function getSize(): array
    {
        return ['width' => $this->width, 'height' => $this->height];
    }

    /**
     * Handle terminal resize
     */
    public function handleResize(): void
    {
        $size = $this->terminal->getSize();

        if ($size['width'] !== $this->width || $size['height'] !== $this->height) {
            $this->width = $size['width'];
            $this->height = $size['height'];

            $this->initBuffers();
            $this->terminal->clearScreen();
        }
    }

    /**
     * Initialize both buffers with empty cells
     */
    private function initBuffers(): void
    {
        $emptyCell = ['char' => ' ', 'color' => $this->themeContext->getNormalText()];

        for ($y = 0; $y < $this->height; $y++) {
            $this->backBuffer[$y] = \array_fill(0, $this->width, $emptyCell);
            // Initialize front buffer with DIFFERENT color to force initial redraw
            $this->frontBuffer[$y] = \array_fill(0, $this->width, ['char' => ' ', 'color' => '']);
        }
    }

    /**
     * Generate ANSI cursor movement sequence
     */
    private function moveCursor(int $x, int $y): string
    {
        // ANSI escape: ESC[{row};{col}H (1-indexed)
        return "\033[" . ($y + 1) . ';' . ($x + 1) . 'H';
    }
}
