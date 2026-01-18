<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Keyboard;

/**
 * Immutable value object wrapping raw keyboard input.
 *
 * Provides convenient methods for checking key types and combinations
 * without repeatedly parsing the raw string.
 *
 * @example
 * $input = new KeyInput('CTRL_R');
 * $input->isCtrl(Key::R);     // true
 * $input->is(Key::R);         // true (ignores modifiers)
 * $input->hasCtrl();          // true
 *
 * @example
 * $input = new KeyInput('UP');
 * $input->is(Key::UP);        // true
 * $input->isNavigation();     // true
 */
final readonly class KeyInput
{
    private ?Key $key;
    private bool $hasCtrl;
    private bool $hasAlt;
    private bool $hasShift;

    public function __construct(
        public string $raw,
    ) {
        $this->hasCtrl = \str_starts_with($raw, 'CTRL_');
        $this->hasAlt = \str_contains($raw, 'ALT_');
        $this->hasShift = \str_contains($raw, 'SHIFT_');
        $this->key = Key::tryFromRaw($raw);
    }

    /**
     * Create from raw key string.
     */
    public static function from(string $raw): self
    {
        return new self($raw);
    }

    /**
     * Get the base key (without modifiers).
     */
    public function key(): ?Key
    {
        return $this->key;
    }

    /**
     * Check if this is a specific key (ignoring modifiers).
     */
    public function is(Key $key): bool
    {
        return $this->key === $key;
    }

    /**
     * Check if this is exactly the specified key with no modifiers.
     */
    public function isExactly(Key $key): bool
    {
        return $this->key === $key && !$this->hasModifiers();
    }

    /**
     * Check if this is Ctrl+Key.
     */
    public function isCtrl(Key $key): bool
    {
        return $this->hasCtrl && $this->key === $key;
    }

    /**
     * Check if this is Alt+Key.
     */
    public function isAlt(Key $key): bool
    {
        return $this->hasAlt && $this->key === $key;
    }

    /**
     * Check if this is Shift+Key.
     */
    public function isShift(Key $key): bool
    {
        return $this->hasShift && $this->key === $key;
    }

    /**
     * Check if this is Ctrl+Shift+Key.
     */
    public function isCtrlShift(Key $key): bool
    {
        return $this->hasCtrl && $this->hasShift && $this->key === $key;
    }

    /**
     * Check if this matches a KeyCombination.
     */
    public function matches(KeyCombination $combination): bool
    {
        return $combination->matches($this->raw);
    }

    /**
     * Check if any modifier is present.
     */
    public function hasModifiers(): bool
    {
        return $this->hasCtrl || $this->hasAlt || $this->hasShift;
    }

    /**
     * Check if Ctrl modifier is present.
     */
    public function hasCtrl(): bool
    {
        return $this->hasCtrl;
    }

    /**
     * Check if Alt modifier is present.
     */
    public function hasAlt(): bool
    {
        return $this->hasAlt;
    }

    /**
     * Check if Shift modifier is present.
     */
    public function hasShift(): bool
    {
        return $this->hasShift;
    }

    /**
     * Check if key is navigation (arrows, home, end, page up/down).
     */
    public function isNavigation(): bool
    {
        return $this->key?->isNavigation() ?? false;
    }

    /**
     * Check if key is a function key (F1-F12).
     */
    public function isFunctionKey(): bool
    {
        return $this->key?->isFunctionKey() ?? false;
    }

    /**
     * Check if key is a letter (A-Z).
     */
    public function isLetter(): bool
    {
        return $this->key?->isLetter() ?? false;
    }

    /**
     * Check if key is a digit (0-9).
     */
    public function isDigit(): bool
    {
        return $this->key?->isDigit() ?? false;
    }

    /**
     * Check if this is a printable character (no modifiers, single char).
     */
    public function isPrintable(): bool
    {
        return !$this->hasModifiers()
            && \mb_strlen($this->raw) === 1
            && \ord($this->raw) >= 32
            && \ord($this->raw) < 127;
    }

    /**
     * Get as printable character (or null if not printable).
     */
    public function char(): ?string
    {
        return $this->isPrintable() ? $this->raw : null;
    }

    /**
     * Check if this is the space key (as literal ' ' character).
     */
    public function isSpace(): bool
    {
        return $this->raw === ' ' || $this->key === Key::SPACE;
    }

    /**
     * Check if this is any of the specified keys.
     */
    public function isAnyOf(Key ...$keys): bool
    {
        return $this->key !== null && \in_array($this->key, $keys, true);
    }
}
