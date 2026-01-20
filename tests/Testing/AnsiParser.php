<?php

declare(strict_types=1);

namespace Tests\Testing;

/**
 * Parses ANSI terminal output into a screen buffer.
 *
 * Handles:
 * - Cursor movement (CSI H, CSI A/B/C/D)
 * - Colors (CSI ...m)
 * - Clear operations (CSI 2J, CSI K)
 * - Text output
 */
final class AnsiParser
{
    /** @var array<int, array<int, array{char: string, color: string}>> */
    private array $buffer = [];

    private int $cursorX = 0;
    private int $cursorY = 0;
    private string $currentColor = '';

    public function __construct(
        private readonly int $width,
        private readonly int $height,
    ) {
        $this->initBuffer();
    }

    public function parse(string $output): ScreenCapture
    {
        $this->initBuffer();
        $this->cursorX = 0;
        $this->cursorY = 0;
        $this->currentColor = '';

        $length = \strlen($output);
        $i = 0;

        while ($i < $length) {
            $char = $output[$i];

            // Check for escape sequence
            if ($char === "\033" && isset($output[$i + 1]) && $output[$i + 1] === '[') {
                $i = $this->parseEscapeSequence($output, $i);
                continue;
            }

            // Skip other escape sequences (ESC followed by something else)
            if ($char === "\033") {
                $i++;
                // Skip until we find a letter or run out of input
                while ($i < $length && !\ctype_alpha($output[$i])) {
                    $i++;
                }
                if ($i < $length) {
                    $i++; // Skip the final letter
                }
                continue;
            }

            // Handle special characters
            if ($char === "\n") {
                $this->cursorY++;
                $this->cursorX = 0;
                $i++;
                continue;
            }

            if ($char === "\r") {
                $this->cursorX = 0;
                $i++;
                continue;
            }

            // Skip other control characters
            if (\ord($char) < 32 && $char !== "\t") {
                $i++;
                continue;
            }

            // Tab - move to next 8-column boundary
            if ($char === "\t") {
                $this->cursorX = (int) ((\floor($this->cursorX / 8) + 1) * 8);
                $i++;
                continue;
            }

            // Regular character - write to buffer
            $this->writeChar($char);
            $i++;
        }

        return new ScreenCapture($this->buffer, $this->width, $this->height);
    }

    private function parseEscapeSequence(string $output, int $start): int
    {
        // Skip ESC[
        $i = $start + 2;
        $params = '';
        $length = \strlen($output);

        // Collect parameters
        while ($i < $length) {
            $char = $output[$i];

            if (\ctype_digit($char) || $char === ';' || $char === '?') {
                $params .= $char;
                $i++;
                continue;
            }

            // Found command character
            $this->executeCommand($char, $params);
            return $i + 1;
        }

        return $i;
    }

    private function executeCommand(string $cmd, string $params): void
    {
        // Strip leading ? for private sequences
        $params = \ltrim($params, '?');
        $parts = $params !== '' ? \explode(';', $params) : [];

        switch ($cmd) {
            case 'H': // Cursor position
            case 'f':
                $row = isset($parts[0]) && $parts[0] !== '' ? (int) $parts[0] : 1;
                $col = isset($parts[1]) && $parts[1] !== '' ? (int) $parts[1] : 1;
                $this->cursorY = \max(0, \min($this->height - 1, $row - 1));
                $this->cursorX = \max(0, \min($this->width - 1, $col - 1));
                break;

            case 'A': // Cursor up
                $n = isset($parts[0]) && $parts[0] !== '' ? (int) $parts[0] : 1;
                $this->cursorY = \max(0, $this->cursorY - $n);
                break;

            case 'B': // Cursor down
                $n = isset($parts[0]) && $parts[0] !== '' ? (int) $parts[0] : 1;
                $this->cursorY = \min($this->height - 1, $this->cursorY + $n);
                break;

            case 'C': // Cursor forward
                $n = isset($parts[0]) && $parts[0] !== '' ? (int) $parts[0] : 1;
                $this->cursorX = \min($this->width - 1, $this->cursorX + $n);
                break;

            case 'D': // Cursor back
                $n = isset($parts[0]) && $parts[0] !== '' ? (int) $parts[0] : 1;
                $this->cursorX = \max(0, $this->cursorX - $n);
                break;

            case 'J': // Clear screen
                $mode = isset($parts[0]) ? (int) $parts[0] : 0;
                if ($mode === 2) {
                    $this->initBuffer();
                    $this->cursorX = 0;
                    $this->cursorY = 0;
                }
                break;

            case 'K': // Clear line
                $mode = isset($parts[0]) ? (int) $parts[0] : 0;
                $this->clearLine($mode);
                break;

            case 'm': // SGR (colors/attributes)
                $this->currentColor = "\033[{$params}m";
                break;

                // Ignore other sequences (cursor show/hide, alternate screen, etc.)
            case 'h':
            case 'l':
                break;
        }
    }

    private function writeChar(string $char): void
    {
        if ($this->cursorY >= 0 && $this->cursorY < $this->height &&
            $this->cursorX >= 0 && $this->cursorX < $this->width) {

            $this->buffer[$this->cursorY][$this->cursorX] = [
                'char' => $char,
                'color' => $this->currentColor,
            ];
        }

        $this->cursorX++;

        // Wrap at end of line
        if ($this->cursorX >= $this->width) {
            $this->cursorX = 0;
            $this->cursorY++;
        }
    }

    private function clearLine(int $mode): void
    {
        if ($this->cursorY < 0 || $this->cursorY >= $this->height) {
            return;
        }

        $emptyCell = ['char' => ' ', 'color' => ''];

        switch ($mode) {
            case 0: // Clear from cursor to end
                for ($x = $this->cursorX; $x < $this->width; $x++) {
                    $this->buffer[$this->cursorY][$x] = $emptyCell;
                }
                break;
            case 1: // Clear from start to cursor
                for ($x = 0; $x <= $this->cursorX; $x++) {
                    $this->buffer[$this->cursorY][$x] = $emptyCell;
                }
                break;
            case 2: // Clear entire line
                $this->buffer[$this->cursorY] = \array_fill(0, $this->width, $emptyCell);
                break;
        }
    }

    private function initBuffer(): void
    {
        $emptyCell = ['char' => ' ', 'color' => ''];

        for ($y = 0; $y < $this->height; $y++) {
            $this->buffer[$y] = \array_fill(0, $this->width, $emptyCell);
        }
    }
}
