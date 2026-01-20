<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Keyboard\Mapping;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;
use Butschster\Commander\Infrastructure\Keyboard\Modifier;

/**
 * Immutable DTO mapping a raw byte sequence to a key combination.
 *
 * Represents the relationship between:
 * - Raw terminal input (escape sequences, control characters)
 * - Logical key representation (Key enum + modifiers)
 *
 * @example Arrow up: "\033[A" → Key::UP
 * @example Ctrl+C: "\003" → Key::C + Modifier::CTRL
 * @example F1 (xterm): "\033OP" → Key::F1
 */
final readonly class KeySequence
{
    /**
     * @param string $sequence Raw byte sequence (e.g., "\033[A", "\001")
     * @param Key $key The key this sequence represents
     * @param array<Modifier> $modifiers Active modifiers (Ctrl, Alt, Shift)
     * @param SequenceType $type Type of sequence for categorization
     * @param TerminalType $terminal Terminal compatibility
     * @param string|null $description Human-readable description
     */
    public function __construct(
        public string $sequence,
        public Key $key,
        public array $modifiers = [],
        public SequenceType $type = SequenceType::Special,
        public TerminalType $terminal = TerminalType::Common,
        public ?string $description = null,
    ) {}

    /**
     * Create an escape sequence mapping (arrows, function keys, etc.).
     */
    public static function escape(
        string $sequence,
        Key $key,
        array $modifiers = [],
        TerminalType $terminal = TerminalType::Common,
        ?string $description = null,
    ): self {
        return new self($sequence, $key, $modifiers, SequenceType::Escape, $terminal, $description);
    }

    /**
     * Create a control character mapping (Ctrl+letter).
     */
    public static function ctrl(string $sequence, Key $key, ?string $description = null): self
    {
        return new self($sequence, $key, [Modifier::CTRL], SequenceType::Control, description: $description);
    }

    /**
     * Create a special key mapping (Tab, Enter, etc.).
     */
    public static function special(string $sequence, Key $key, ?string $description = null): self
    {
        return new self($sequence, $key, [], SequenceType::Special, description: $description);
    }

    /**
     * Check if this mapping has a specific modifier.
     */
    public function hasModifier(Modifier $modifier): bool
    {
        return \in_array($modifier, $this->modifiers, true);
    }

    /**
     * Check if this has Ctrl modifier.
     */
    public function hasCtrl(): bool
    {
        return $this->hasModifier(Modifier::CTRL);
    }

    /**
     * Check if this has Alt modifier.
     */
    public function hasAlt(): bool
    {
        return $this->hasModifier(Modifier::ALT);
    }

    /**
     * Check if this has Shift modifier.
     */
    public function hasShift(): bool
    {
        return $this->hasModifier(Modifier::SHIFT);
    }

    /**
     * Get the output key name (e.g., "UP", "CTRL_C", "F1").
     *
     * This is the string format used by the rest of the application.
     */
    public function toKeyName(): string
    {
        $prefix = '';

        foreach ($this->modifiers as $modifier) {
            $prefix .= $modifier->value . '_';
        }

        return $prefix . $this->key->value;
    }

    /**
     * Convert to KeyCombination value object.
     */
    public function toCombination(): KeyCombination
    {
        return new KeyCombination(
            key: $this->key,
            ctrl: $this->hasCtrl(),
            alt: $this->hasAlt(),
            shift: $this->hasShift(),
        );
    }

    /**
     * Check if sequence matches raw input.
     */
    public function matches(string $input): bool
    {
        return $this->sequence === $input;
    }

    /**
     * Get sequence as hex string for debugging.
     */
    public function sequenceAsHex(): string
    {
        return \bin2hex($this->sequence);
    }
}
