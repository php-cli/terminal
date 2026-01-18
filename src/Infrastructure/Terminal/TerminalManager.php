<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Terminal;

use Butschster\Commander\Infrastructure\Terminal\Driver\TerminalDriverInterface;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Terminal management service
 * Handles terminal control operations: size detection, raw mode, cursor control
 */
final class TerminalManager
{
    private bool $rawModeEnabled = false;
    private ?string $originalTerminalSettings = null;

    public function __construct(
        private readonly ?TerminalDriverInterface $driver = null,
    ) {}

    /**
     * Get terminal size
     *
     * @return array{width: int, height: int}
     */
    public function getSize(): array
    {
        if ($this->driver !== null) {
            return $this->driver->getSize();
        }

        // Try to get size using stty
        $output = [];
        \exec('stty size 2>/dev/null', $output, $returnCode);

        if ($returnCode === 0 && !empty($output[0])) {
            [$height, $width] = \sscanf($output[0], '%d %d');
            if ($height && $width) {
                return ['width' => (int) $width, 'height' => (int) $height];
            }
        }

        // Fallback: try tput
        $width = (int) \exec('tput cols 2>/dev/null') ?: 80;
        $height = (int) \exec('tput lines 2>/dev/null') ?: 24;

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
        $this->originalTerminalSettings = \shell_exec('stty -g 2>/dev/null');

        // Enable raw mode: -icanon (no line buffering), -echo (no echo), -isig (no signals)
        \shell_exec('stty -icanon -echo -isig 2>/dev/null');

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
            \shell_exec("stty {$this->originalTerminalSettings} 2>/dev/null");
        }

        $this->rawModeEnabled = false;
    }

    /**
     * Clear entire screen
     */
    public function clearScreen(): void
    {
        $this->write("\033[2J\033[H");
    }

    /**
     * Hide cursor
     */
    public function hideCursor(): void
    {
        $this->write("\033[?25l");
    }

    /**
     * Show cursor
     */
    public function showCursor(): void
    {
        $this->write("\033[?25h");
    }

    /**
     * Enter alternate screen buffer
     * This preserves the current terminal content
     */
    public function enterAlternateScreen(): void
    {
        $this->write("\033[?1049h");
    }

    /**
     * Exit alternate screen buffer
     * This restores the previous terminal content
     */
    public function exitAlternateScreen(): void
    {
        $this->write("\033[?1049l");
    }

    /**
     * Move cursor to position (1-indexed)
     */
    public function moveCursor(int $x, int $y): void
    {
        $this->write("\033[{$y};{$x}H");
    }

    /**
     * Reset terminal colors and attributes
     */
    public function resetAttributes(): void
    {
        $this->write(ColorScheme::RESET);
    }

    /**
     * Write output to terminal (uses driver if available)
     */
    public function write(string $output): void
    {
        if ($this->driver !== null) {
            $this->driver->write($output);
            return;
        }

        echo $output;
        \flush();
    }

    /**
     * Initialize terminal for fullscreen application
     */
    public function initialize(): void
    {
        if ($this->driver !== null) {
            $this->driver->initialize();
            return;
        }

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
        if ($this->driver !== null) {
            $this->driver->cleanup();
            return;
        }

        $this->resetAttributes();
        $this->showCursor();
        $this->exitAlternateScreen();
        $this->disableRawMode();
    }
}
