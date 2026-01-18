<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Terminal\Driver;

/**
 * Real terminal driver using STDIN/STDOUT and shell commands.
 *
 * This is the production driver that interacts with the actual terminal.
 */
final class RealTerminalDriver implements TerminalDriverInterface
{
    /** @var resource */
    private $stdin;

    private bool $rawModeEnabled = false;
    private bool $nonBlockingEnabled = false;
    private ?string $originalTerminalSettings = null;

    public function __construct()
    {
        $this->stdin = STDIN;
    }

    public function getSize(): array
    {
        // Try stty first
        $output = [];
        \exec('stty size 2>/dev/null', $output, $returnCode);

        if ($returnCode === 0 && !empty($output[0])) {
            [$height, $width] = \sscanf($output[0], '%d %d');
            if ($height && $width) {
                return ['width' => (int) $width, 'height' => (int) $height];
            }
        }

        // Fallback to tput
        $width = (int) \exec('tput cols 2>/dev/null') ?: 80;
        $height = (int) \exec('tput lines 2>/dev/null') ?: 24;

        return ['width' => $width, 'height' => $height];
    }

    public function readInput(): ?string
    {
        if (!$this->nonBlockingEnabled) {
            \stream_set_blocking($this->stdin, false);
            $this->nonBlockingEnabled = true;
        }

        $char = \fread($this->stdin, 1);

        if ($char === false || $char === '') {
            return null;
        }

        return $char;
    }

    public function hasInput(): bool
    {
        $read = [$this->stdin];
        $write = null;
        $except = null;

        return \stream_select($read, $write, $except, 0, 0) > 0;
    }

    public function write(string $output): void
    {
        echo $output;
        \flush();
    }

    public function initialize(): void
    {
        $this->enableRawMode();
        $this->write("\033[?1049h"); // Enter alternate screen
        $this->write("\033[?25l");   // Hide cursor
        $this->write("\033[2J\033[H"); // Clear screen
    }

    public function cleanup(): void
    {
        $this->write("\033[0m");     // Reset attributes
        $this->write("\033[?25h");   // Show cursor
        $this->write("\033[?1049l"); // Exit alternate screen
        $this->disableRawMode();
    }

    public function isInteractive(): bool
    {
        return \function_exists('posix_isatty') && \posix_isatty($this->stdin);
    }

    private function enableRawMode(): void
    {
        if ($this->rawModeEnabled) {
            return;
        }

        $this->originalTerminalSettings = \shell_exec('stty -g 2>/dev/null');
        \shell_exec('stty -icanon -echo -isig 2>/dev/null');
        $this->rawModeEnabled = true;
    }

    private function disableRawMode(): void
    {
        if (!$this->rawModeEnabled) {
            return;
        }

        if ($this->originalTerminalSettings !== null) {
            \shell_exec("stty {$this->originalTerminalSettings} 2>/dev/null");
        }

        if ($this->nonBlockingEnabled) {
            \stream_set_blocking($this->stdin, true);
            $this->nonBlockingEnabled = false;
        }

        $this->rawModeEnabled = false;
    }
}
