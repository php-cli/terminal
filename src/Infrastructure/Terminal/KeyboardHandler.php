<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Terminal;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;
use Butschster\Commander\Infrastructure\Keyboard\Mapping\KeyMappingRegistry;
use Butschster\Commander\Infrastructure\Terminal\Driver\TerminalDriverInterface;

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

    public function __construct(
        private readonly KeyMappingRegistry $mappings = new KeyMappingRegistry(),
        private readonly ?TerminalDriverInterface $driver = null,
    ) {
        $this->stdin = STDIN;
    }

    /**
     * Get the key mapping registry.
     *
     * Allows adding custom key mappings for terminal-specific sequences.
     */
    public function getMappings(): KeyMappingRegistry
    {
        return $this->mappings;
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
        $char = $this->readChar();

        if ($char === null) {
            return null;
        }

        // Check if it's an escape sequence
        if ($char === "\033") {
            return $this->readEscapeSequence();
        }

        // Check for known control characters using registry
        $mapping = $this->mappings->findBySequence($char);
        if ($mapping !== null) {
            return $mapping->toKeyName();
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
        if ($this->driver !== null) {
            return $this->driver->hasInput();
        }

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
     * Parse raw key string to Key enum.
     *
     * @param string $rawKey Raw key from getKey() like 'UP', 'CTRL_C', 'F12'
     * @return Key|null Key enum or null if not mappable
     */
    public function parseToKey(string $rawKey): ?Key
    {
        // Direct enum match (UP, DOWN, F1, ENTER, etc.)
        $key = Key::tryFrom($rawKey);
        if ($key !== null) {
            return $key;
        }

        // Handle CTRL_X format - extract the base key
        if (\str_starts_with($rawKey, 'CTRL_')) {
            $keyPart = \substr($rawKey, 5);
            // Try direct match (CTRL_UP -> UP, CTRL_LEFT -> LEFT)
            $key = Key::tryFrom($keyPart);
            if ($key !== null) {
                return $key;
            }
            // Try as single letter (CTRL_C -> C)
            if (\strlen($keyPart) === 1 && \ctype_alpha($keyPart)) {
                return Key::tryFrom(\strtoupper($keyPart));
            }
        }

        // Handle single letter keys
        if (\strlen($rawKey) === 1 && \ctype_alpha($rawKey)) {
            return Key::tryFrom(\strtoupper($rawKey));
        }

        // Handle single digit keys
        if (\strlen($rawKey) === 1 && \ctype_digit($rawKey)) {
            return Key::tryFrom($rawKey);
        }

        return null;
    }

    /**
     * Parse raw key string to KeyCombination.
     *
     * @param string $rawKey Raw key from getKey()
     * @return KeyCombination|null Combination or null if not parseable
     */
    public function parseToCombination(string $rawKey): ?KeyCombination
    {
        // Handle CTRL_X format
        if (\str_starts_with($rawKey, 'CTRL_')) {
            $keyPart = \substr($rawKey, 5);

            // Try direct match (CTRL_UP, CTRL_LEFT, etc.)
            $key = Key::tryFrom($keyPart);
            if ($key !== null) {
                return KeyCombination::ctrl($key);
            }

            // Try as single letter (CTRL_C, CTRL_Q, etc.)
            if (\strlen($keyPart) === 1 && \ctype_alpha($keyPart)) {
                $key = Key::tryFrom(\strtoupper($keyPart));
                if ($key !== null) {
                    return KeyCombination::ctrl($key);
                }
            }

            return null;
        }

        // Handle space character
        if ($rawKey === ' ') {
            return KeyCombination::key(Key::SPACE);
        }

        // Direct key match (F1, UP, ENTER, etc.)
        $key = Key::tryFrom($rawKey);
        if ($key !== null) {
            return KeyCombination::key($key);
        }

        // Single letter
        if (\strlen($rawKey) === 1 && \ctype_alpha($rawKey)) {
            $key = Key::tryFrom(\strtoupper($rawKey));
            if ($key !== null) {
                return KeyCombination::key($key);
            }
        }

        // Single digit
        if (\strlen($rawKey) === 1 && \ctype_digit($rawKey)) {
            $key = Key::tryFrom($rawKey);
            if ($key !== null) {
                return KeyCombination::key($key);
            }
        }

        return null;
    }

    /**
     * Get next key as KeyCombination (non-blocking).
     *
     * @return KeyCombination|null Combination or null if no input
     */
    public function getKeyCombination(): ?KeyCombination
    {
        $raw = $this->getKey();
        if ($raw === null) {
            return null;
        }

        return $this->parseToCombination($raw);
    }

    /**
     * Read a single character from input (uses driver if available)
     */
    private function readChar(): ?string
    {
        if ($this->driver !== null) {
            return $this->driver->readInput();
        }

        if (!$this->nonBlockingEnabled) {
            $this->enableNonBlocking();
        }

        $char = \fread($this->stdin, 1);

        if ($char === false || $char === '') {
            return null;
        }

        return $char;
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
            if ($this->driver !== null) {
                // For virtual driver, check hasInput directly
                if (!$this->driver->hasInput()) {
                    // Small delay for escape sequence timing
                    \usleep(1000);
                    if (!$this->driver->hasInput()) {
                        break;
                    }
                }
            } else {
                $read = [$this->stdin];
                $write = null;
                $except = null;

                $ready = \stream_select($read, $write, $except, 0, $timeout);

                if ($ready === false || $ready === 0) {
                    break;
                }
            }

            $char = $this->readCharDirect();

            if ($char === null) {
                break;
            }

            $sequence .= $char;

            // Check if we have a complete known sequence after each character
            $mapping = $this->mappings->findBySequence($sequence);
            if ($mapping !== null) {
                return $mapping->toKeyName();
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
        $mapping = $this->mappings->findBySequence($sequence);
        if ($mapping !== null) {
            return $mapping->toKeyName();
        }

        // If we got ESC + characters but no match, return for debugging
        if (\strlen($sequence) > 1) {
            return 'UNKNOWN_' . \bin2hex(\substr($sequence, 1));
        }

        // Return ESCAPE if just ESC key
        return 'ESCAPE';
    }

    /**
     * Read a single character directly (bypasses blocking check for escape sequences)
     */
    private function readCharDirect(): ?string
    {
        if ($this->driver !== null) {
            return $this->driver->readInput();
        }

        $char = \fread($this->stdin, 1);

        if ($char === false || $char === '') {
            return null;
        }

        return $char;
    }
}
