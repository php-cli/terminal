<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Keyboard;

/**
 * Value object representing a key combination (key + optional modifiers).
 *
 * Immutable and provides factory methods for common patterns.
 */
readonly class KeyCombination implements \Stringable
{
    public function __construct(
        public Key $key,
        public bool $ctrl = false,
        public bool $alt = false,
        public bool $shift = false,
    ) {}

    /**
     * Create a combination with just the key (no modifiers).
     */
    public static function key(Key $key): self
    {
        return new self($key);
    }

    /**
     * Create a Ctrl+Key combination.
     */
    public static function ctrl(Key $key): self
    {
        return new self($key, ctrl: true);
    }

    /**
     * Create an Alt+Key combination.
     */
    public static function alt(Key $key): self
    {
        return new self($key, alt: true);
    }

    /**
     * Create a Shift+Key combination.
     */
    public static function shift(Key $key): self
    {
        return new self($key, shift: true);
    }

    /**
     * Create a Ctrl+Shift+Key combination.
     */
    public static function ctrlShift(Key $key): self
    {
        return new self($key, ctrl: true, shift: true);
    }

    /**
     * Create a Ctrl+Alt+Key combination.
     */
    public static function ctrlAlt(Key $key): self
    {
        return new self($key, ctrl: true, alt: true);
    }

    /**
     * Parse a human-readable string like "Ctrl+C", "F12", "Ctrl+Shift+A".
     *
     * @throws \InvalidArgumentException If the string cannot be parsed
     */
    public static function fromString(string $combo): self
    {
        $combo = \trim($combo);
        if ($combo === '') {
            throw new \InvalidArgumentException('Key combination string cannot be empty');
        }

        $parts = \array_map(trim(...), \explode('+', $combo));
        $ctrl = false;
        $alt = false;
        $shift = false;
        $keyPart = null;

        foreach ($parts as $part) {
            $upper = \strtoupper($part);
            if ($upper === 'CTRL' || $upper === 'CONTROL') {
                $ctrl = true;
            } elseif ($upper === 'ALT') {
                $alt = true;
            } elseif ($upper === 'SHIFT') {
                $shift = true;
            } else {
                $keyPart = $upper;
            }
        }

        if ($keyPart === null) {
            throw new \InvalidArgumentException("No key found in combination: {$combo}");
        }

        // Try to resolve the key
        $key = self::resolveKey($keyPart);
        if ($key === null) {
            throw new \InvalidArgumentException("Unknown key: {$keyPart}");
        }

        return new self($key, $ctrl, $alt, $shift);
    }

    /**
     * Check if this combination matches a raw KeyboardHandler output string.
     */
    public function matches(string $rawKey): bool
    {
        return $this->toRawKey() === $rawKey;
    }

    /**
     * Convert to the raw string format used by KeyboardHandler.
     *
     * Examples: "CTRL_C", "F12", "UP", "CTRL_LEFT", " " (space)
     */
    public function toRawKey(): string
    {
        $prefix = '';

        if ($this->ctrl) {
            $prefix .= 'CTRL_';
        }
        if ($this->alt) {
            $prefix .= 'ALT_';
        }
        if ($this->shift) {
            $prefix .= 'SHIFT_';
        }

        // Special case: SPACE is returned as literal ' ' from KeyboardHandler
        if ($this->key === Key::SPACE && $prefix === '') {
            return ' ';
        }

        return $prefix . $this->key->value;
    }

    /**
     * Check if this combination has any modifiers.
     */
    public function hasModifiers(): bool
    {
        return $this->ctrl || $this->alt || $this->shift;
    }

    /**
     * Get a human-readable representation like "Ctrl+C", "F12", "Ctrl+Shift+A".
     */
    #[\Override]
    public function __toString(): string
    {
        $parts = [];

        if ($this->ctrl) {
            $parts[] = 'Ctrl';
        }
        if ($this->alt) {
            $parts[] = 'Alt';
        }
        if ($this->shift) {
            $parts[] = 'Shift';
        }

        $parts[] = $this->formatKeyForDisplay();

        return \implode('+', $parts);
    }

    /**
     * Try to resolve a key part string to a Key enum.
     */
    private static function resolveKey(string $keyPart): ?Key
    {
        // Direct enum value match (F1, UP, ENTER, etc.)
        $key = Key::tryFrom($keyPart);
        if ($key !== null) {
            return $key;
        }

        // Single character - could be letter or digit
        if (\strlen($keyPart) === 1) {
            if (\ctype_alpha($keyPart)) {
                return Key::tryFrom(\strtoupper($keyPart));
            }
            if (\ctype_digit($keyPart)) {
                // Digits are stored as D0-D9 in enum, but value is just the digit
                return Key::tryFrom($keyPart);
            }
        }

        // Handle some aliases
        return match ($keyPart) {
            'PAGEUP', 'PGUP' => Key::PAGE_UP,
            'PAGEDOWN', 'PGDN' => Key::PAGE_DOWN,
            'ESC' => Key::ESCAPE,
            'DEL' => Key::DELETE,
            'INS' => Key::INSERT,
            'BS' => Key::BACKSPACE,
            'RETURN' => Key::ENTER,
            default => null,
        };
    }

    /**
     * Format the key for human-readable display.
     */
    private function formatKeyForDisplay(): string
    {
        // Function keys stay as-is
        if ($this->key->isFunctionKey()) {
            return $this->key->value;
        }

        // Single letters stay as-is
        if ($this->key->isLetter()) {
            return $this->key->value;
        }

        // Digits - just the number
        if ($this->key->isDigit()) {
            return $this->key->value;
        }

        // Special keys - title case
        return match ($this->key) {
            Key::PAGE_UP => 'Page Up',
            Key::PAGE_DOWN => 'Page Down',
            default => \ucfirst(\strtolower($this->key->value)),
        };
    }
}
