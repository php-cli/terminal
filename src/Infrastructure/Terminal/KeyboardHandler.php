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
    /** Key code mappings */
    private const array KEY_MAPPINGS = [
        // Arrow keys
        "\033[A" => 'UP',
        "\033[B" => 'DOWN',
        "\033[C" => 'RIGHT',
        "\033[D" => 'LEFT',

        // Function keys
        // F1-F4 can have different sequences depending on terminal
        "\033OP" => 'F1',    // xterm
        "\033[11~" => 'F1',  // linux console
        "\033OQ" => 'F2',    // xterm
        "\033[12~" => 'F2',  // linux console
        "\033OR" => 'F3',    // xterm
        "\033[13~" => 'F3',  // linux console
        "\033OS" => 'F4',    // xterm
        "\033[14~" => 'F4',  // linux console
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

        // IMPORTANT: Enter keys must be checked BEFORE Ctrl combinations
        // because \n (line feed) is the same as CTRL_J
        "\n" => 'ENTER',      // Line feed (LF) - Unix/Mac
        "\r" => 'ENTER',      // Carriage return (CR) - Old Mac
        "\r\n" => 'ENTER',    // CRLF - Windows
        
        // Other special keys
        "\t" => 'TAB',
        "\033" => 'ESCAPE',
        "\177" => 'BACKSPACE',
        "\010" => 'BACKSPACE',

        // Ctrl combinations (Ctrl+letter sends ASCII code 1-26)
        // Note: Some overlap with special keys above
        "\001" => 'CTRL_A',
        "\002" => 'CTRL_B',
        "\003" => 'CTRL_C',
        "\004" => 'CTRL_D',
        "\005" => 'CTRL_E',
        "\006" => 'CTRL_F',
        "\007" => 'CTRL_G',
        // "\010" => 'CTRL_H', // Commented out - same as backspace
        // "\011" => 'CTRL_I', // Commented out - same as tab
        // "\012" => 'CTRL_J', // Commented out - same as line feed (ENTER)
        "\013" => 'CTRL_K',
        "\014" => 'CTRL_L',
        // "\015" => 'CTRL_M', // Commented out - same as carriage return (ENTER)
        "\016" => 'CTRL_N',
        "\017" => 'CTRL_O',
        "\020" => 'CTRL_P',
        "\021" => 'CTRL_Q',
        "\022" => 'CTRL_R',
        "\023" => 'CTRL_S',
        "\024" => 'CTRL_T',
        "\025" => 'CTRL_U',
        "\026" => 'CTRL_V',
        "\027" => 'CTRL_W',
        "\030" => 'CTRL_X',
        "\031" => 'CTRL_Y',
        "\032" => 'CTRL_Z',
    ];

    /** @var resource */
    private $stdin;

    private bool $nonBlockingEnabled = false;

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

        \stream_set_blocking($this->stdin, false);
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

        \stream_set_blocking($this->stdin, true);
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

        $char = \fread($this->stdin, 1);

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

            $ready = \stream_select($read, $write, $except, $seconds, $microseconds);

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

        return \stream_select($read, $write, $except, 0, 0) > 0;
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

    /**
     * Read complete escape sequence
     */
    private function readEscapeSequence(): string
    {
        $sequence = "\033";
        $maxLength = 10; // Max escape sequence length
        $timeout = 100000; // 100ms timeout in microseconds

        for ($i = 0; $i < $maxLength; $i++) {
            // Check if data is available
            $read = [$this->stdin];
            $write = null;
            $except = null;

            $ready = \stream_select($read, $write, $except, 0, $timeout);

            if ($ready === false || $ready === 0) {
                break;
            }

            $char = \fread($this->stdin, 1);

            if ($char === false || $char === '') {
                break;
            }

            $sequence .= $char;

            // Check if we have a complete known sequence after each character
            foreach (self::KEY_MAPPINGS as $knownSequence => $keyName) {
                if ($sequence === $knownSequence) {
                    return $keyName;
                }
            }

            // Special handling for ESC O sequences (F1-F4 in xterm mode)
            // ESC O needs one more character: ESC O P, ESC O Q, etc.
            if (\strlen($sequence) === 2 && $char === 'O') {
                // Continue reading one more character
                continue;
            }

            // For ESC [ sequences, continue until we hit a letter or ~
            if (\strlen($sequence) >= 2 && $sequence[1] === '[') {
                // Continue if we have digits or semicolons (CSI parameters)
                if (\ctype_digit($char) || $char === ';') {
                    continue;
                }
                // Stop if we hit ~ or a letter (end of CSI sequence)
                if ($char === '~' || \ctype_alpha($char)) {
                    break;
                }
            }

            // For ESC O sequences, after getting O, read one more letter and stop
            if (\strlen($sequence) === 3 && $sequence[1] === 'O' && \ctype_alpha($char)) {
                break;
            }

            // If we have ESC followed by a single letter (not O or [), we're done
            if (\strlen($sequence) === 2 && \ctype_alpha($char) && $char !== 'O' && $char !== '[') {
                break;
            }
        }

        // Final check for known sequences
        foreach (self::KEY_MAPPINGS as $knownSequence => $keyName) {
            if ($sequence === $knownSequence) {
                return $keyName;
            }
        }

        // If we got ESC + characters but no match, return for debugging
        if (\strlen($sequence) > 1) {
            return 'UNKNOWN_' . \bin2hex(\substr($sequence, 1));
        }

        // Return ESCAPE if just ESC key
        return 'ESCAPE';
    }
}
