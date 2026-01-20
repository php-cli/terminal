<?php

declare(strict_types=1);

namespace Tests\Testing;

/**
 * Fluent builder for creating key input sequences.
 */
final class ScriptedKeySequence
{
    /** @var array<string|null> */
    private array $sequence = [];

    /**
     * Add a single key press.
     */
    public function press(string $key): self
    {
        $this->sequence[] = \strtoupper($key);
        return $this;
    }

    /**
     * Add Ctrl+key combination.
     */
    public function ctrl(string $key): self
    {
        $this->sequence[] = 'CTRL_' . \strtoupper($key);
        return $this;
    }

    /**
     * Add Alt+key combination.
     */
    public function alt(string $key): self
    {
        $this->sequence[] = 'ALT_' . \strtoupper($key);
        return $this;
    }

    /**
     * Add Shift+key combination.
     */
    public function shift(string $key): self
    {
        $this->sequence[] = 'SHIFT_' . \strtoupper($key);
        return $this;
    }

    /**
     * Type a string (each character becomes a key press).
     */
    public function type(string $text): self
    {
        foreach (\mb_str_split($text) as $char) {
            if ($char === ' ') {
                $this->sequence[] = ' ';
            } elseif ($char === "\n") {
                $this->sequence[] = 'ENTER';
            } elseif ($char === "\t") {
                $this->sequence[] = 'TAB';
            } else {
                $this->sequence[] = $char;
            }
        }
        return $this;
    }

    /**
     * Repeat a key N times.
     */
    public function repeat(string $key, int $times): self
    {
        $key = \strtoupper($key);
        for ($i = 0; $i < $times; $i++) {
            $this->sequence[] = $key;
        }
        return $this;
    }

    /**
     * Add a frame boundary (signals app to process all previous input).
     */
    public function frame(): self
    {
        $this->sequence[] = null;
        return $this;
    }

    /**
     * Add navigation keys.
     */
    public function up(int $times = 1): self
    {
        return $this->repeat('UP', $times);
    }

    public function down(int $times = 1): self
    {
        return $this->repeat('DOWN', $times);
    }

    public function left(int $times = 1): self
    {
        return $this->repeat('LEFT', $times);
    }

    public function right(int $times = 1): self
    {
        return $this->repeat('RIGHT', $times);
    }

    /**
     * Press Enter.
     */
    public function enter(): self
    {
        return $this->press('ENTER');
    }

    /**
     * Press Escape.
     */
    public function escape(): self
    {
        return $this->press('ESCAPE');
    }

    /**
     * Press Tab.
     */
    public function tab(int $times = 1): self
    {
        return $this->repeat('TAB', $times);
    }

    /**
     * Press a function key (F1-F12).
     */
    public function fn(int $number): self
    {
        if ($number < 1 || $number > 12) {
            throw new \InvalidArgumentException('Function key must be F1-F12');
        }
        return $this->press("F{$number}");
    }

    /**
     * Build the sequence array.
     *
     * @return array<string|null>
     */
    public function build(): array
    {
        return $this->sequence;
    }

    /**
     * Apply sequence directly to a virtual driver.
     */
    public function applyTo(VirtualTerminalDriver $driver): void
    {
        foreach ($this->sequence as $key) {
            if ($key === null) {
                $driver->queueFrameBoundary();
            } else {
                $driver->queueInput($key);
            }
        }
    }

    /**
     * Reset the sequence.
     */
    public function reset(): self
    {
        $this->sequence = [];
        return $this;
    }

    /**
     * Get current sequence length.
     */
    public function count(): int
    {
        return \count($this->sequence);
    }
}
