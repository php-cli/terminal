<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Terminal;

use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Terminal management service
 * Handles terminal control operations: size detection, raw mode, cursor control
 */
final class TerminalManager
{
    private bool $rawModeEnabled = false;
    private ?string $originalTerminalSettings = null;

    /**
     * Get terminal size
     *
     * @return array{width: int, height: int}
     */
    public function getSize(): array
    {
        // Try to get size using stty
        $output = [];
        exec('stty size 2>/dev/null', $output, $returnCode);

        if ($returnCode === 0 && !empty($output[0])) {
            [$height, $width] = sscanf($output[0], '%d %d');
            if ($height && $width) {
                return ['width' => (int) $width, 'height' => (int) $height];
            }
        }

        // Fallback: try tput
        $width = (int) exec('tput cols 2>/dev/null') ?: 80;
        $height = (int) exec('tput lines 2>/dev/null') ?: 24;

        return ['width' => $width, 'height' => $height];
    }

    /**
     * Enable raw mode (disable canonical mode, echo, signals)
     */
    public function enableRawMode(): void
    {
        if ($this->rawModeEnabled) {
            return;
        }

        // Save original settings
        $this->originalTerminalSettings = shell_exec('stty -g 2>/dev/null');

        // Enable raw mode: -icanon (no line buffering), -echo (no echo), -isig (no signals)
        shell_exec('stty -icanon -echo -isig 2>/dev/null');

        $this->rawModeEnabled = true;
    }

    /**
     * Disable raw mode (restore original terminal settings)
     */
    public function disableRawMode(): void
    {
        if (!$this->rawModeEnabled) {
            return;
        }

        if ($this->originalTerminalSettings !== null) {
            shell_exec("stty {$this->originalTerminalSettings} 2>/dev/null");
        }

        $this->rawModeEnabled = false;
    }

    /**
     * Clear entire screen
     */
    public function clearScreen(): void
    {
        echo "\033[2J\033[H";
        flush();
    }

    /**
     * Hide cursor
     */
    public function hideCursor(): void
    {
        echo "\033[?25l";
        flush();
    }

    /**
     * Show cursor
     */
    public function showCursor(): void
    {
        echo "\033[?25h";
        flush();
    }

    /**
     * Enter alternate screen buffer
     * This preserves the current terminal content
     */
    public function enterAlternateScreen(): void
    {
        echo "\033[?1049h";
        flush();
    }

    /**
     * Exit alternate screen buffer
     * This restores the previous terminal content
     */
    public function exitAlternateScreen(): void
    {
        echo "\033[?1049l";
        flush();
    }

    /**
     * Move cursor to position (1-indexed)
     */
    public function moveCursor(int $x, int $y): void
    {
        echo "\033[{$y};{$x}H";
        flush();
    }

    /**
     * Reset terminal colors and attributes
     */
    public function resetAttributes(): void
    {
        echo ColorScheme::RESET;
        flush();
    }

    /**
     * Initialize terminal for fullscreen application
     */
    public function initialize(): void
    {
        $this->enableRawMode();
        $this->enterAlternateScreen();
        $this->hideCursor();
        $this->clearScreen();
    }

    /**
     * Cleanup and restore terminal to normal state
     */
    public function cleanup(): void
    {
        $this->resetAttributes();
        $this->showCursor();
        $this->exitAlternateScreen();
        $this->disableRawMode();
    }

    /**
     * Check if terminal supports colors
     */
    public function supportsColors(): bool
    {
        $term = getenv('TERM');
        return $term && (
                strpos($term, 'color') !== false ||
                strpos($term, '256color') !== false ||
                strpos($term, 'xterm') !== false
            );
    }
}
