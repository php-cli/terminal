<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Terminal;

/**
 * Keyboard input handler
 *
 * Reads and parses keyboard input including special keys (arrows, function keys)
 * Works in non-blocking mode to avoid blocking the main event loop
 */
final class KeyboardHandler
{
    /** @var resource */
    private $stdin;

    private bool $nonBlockingEnabled = false;

    /** Key code mappings */
    private const KEY_MAPPINGS = [
        // Arrow keys
        "\033[A" => 'UP',
        "\033[B" => 'DOWN',
        "\033[C" => 'RIGHT',
        "\033[D" => 'LEFT',

        // Function keys
        "\033OP" => 'F1',
        "\033OQ" => 'F2',
        "\033OR" => 'F3',
        "\033OS" => 'F4',
        "\033[15~" => 'F5',
        "\033[17~" => 'F6',
        "\033[18~" => 'F7',
        "\033[19~" => 'F8',
        "\033[20~" => 'F9',
        "\033[21~" => 'F10',
        "\033[23~" => 'F11',
        "\033[24~" => 'F12',

        // Special keys
        "\033[1~" => 'HOME',
        "\033[4~" => 'END',
        "\033[5~" => 'PAGE_UP',
        "\033[6~" => 'PAGE_DOWN',
        "\033[2~" => 'INSERT',
        "\033[3~" => 'DELETE',

        // Control sequences
        "\n" => 'ENTER',
        "\r" => 'ENTER',
        "\t" => 'TAB',
        "\033" => 'ESCAPE',
        "\177" => 'BACKSPACE',
        "\010" => 'BACKSPACE',

        // Ctrl combinations
        "\003" => 'CTRL_C',
        "\004" => 'CTRL_D',
        "\032" => 'CTRL_Z',
    ];

    public function __construct()
    {
        $this->stdin = STDIN;
    }

    /**
     * Enable non-blocking mode for STDIN
     */
    public function enableNonBlocking(): void
    {
        if ($this->nonBlockingEnabled) {
            return;
        }

        stream_set_blocking($this->stdin, false);
        $this->nonBlockingEnabled = true;
    }

    /**
     * Disable non-blocking mode
     */
    public function disableNonBlocking(): void
    {
        if (!$this->nonBlockingEnabled) {
            return;
        }

        stream_set_blocking($this->stdin, true);
        $this->nonBlockingEnabled = false;
    }

    /**
     * Get next key press (non-blocking)
     *
     * @return string|null Key code or null if no input available
     */
    public function getKey(): ?string
    {
        if (!$this->nonBlockingEnabled) {
            $this->enableNonBlocking();
        }

        $char = fread($this->stdin, 1);

        if ($char === false || $char === '') {
            return null;
        }

        // Check if it's an escape sequence
        if ($char === "\033") {
            return $this->readEscapeSequence();
        }

        // Check for known control characters
        foreach (self::KEY_MAPPINGS as $sequence => $keyName) {
            if ($char === $sequence) {
                return $keyName;
            }
        }

        // Return the character as-is for regular keys
        return $char;
    }

    /**
     * Read complete escape sequence
     */
    private function readEscapeSequence(): string
    {
        $sequence = "\033";
        $maxLength = 10; // Max escape sequence length
        $timeout = 10000; // 10ms timeout in microseconds

        for ($i = 0; $i < $maxLength; $i++) {
            // Check if data is available
            $read = [$this->stdin];
            $write = null;
            $except = null;

            $ready = stream_select($read, $write, $except, 0, $timeout);

            if ($ready === false || $ready === 0) {
                break;
            }

            $char = fread($this->stdin, 1);

            if ($char === false || $char === '') {
                break;
            }

            $sequence .= $char;

            // Check if we have a complete known sequence
            foreach (self::KEY_MAPPINGS as $knownSequence => $keyName) {
                if ($sequence === $knownSequence) {
                    return $keyName;
                }
            }

            // If sequence ends with a letter or tilde, it's likely complete
            if (ctype_alpha($char) || $char === '~') {
                break;
            }
        }

        // Return ESCAPE if no match found
        return 'ESCAPE';
    }

    /**
     * Wait for next key press (blocking)
     *
     * @param int $timeoutMs Timeout in milliseconds (0 = no timeout)
     * @return string|null Key code or null on timeout
     */
    public function waitForKey(int $timeoutMs = 0): ?string
    {
        $wasNonBlocking = $this->nonBlockingEnabled;

        if ($wasNonBlocking) {
            $this->disableNonBlocking();
        }

        if ($timeoutMs > 0) {
            $read = [$this->stdin];
            $write = null;
            $except = null;

            $seconds = (int) ($timeoutMs / 1000);
            $microseconds = ($timeoutMs % 1000) * 1000;

            $ready = stream_select($read, $write, $except, $seconds, $microseconds);

            if ($ready === false || $ready === 0) {
                if ($wasNonBlocking) {
                    $this->enableNonBlocking();
                }
                return null;
            }
        }

        $key = $this->getKey();

        if ($wasNonBlocking) {
            $this->enableNonBlocking();
        }

        return $key;
    }

    /**
     * Check if input is available
     */
    public function hasInput(): bool
    {
        $read = [$this->stdin];
        $write = null;
        $except = null;

        return stream_select($read, $write, $except, 0, 0) > 0;
    }

    /**
     * Flush input buffer
     */
    public function flush(): void
    {
        while ($this->getKey() !== null) {
            // Read and discard all pending input
        }
    }
}
