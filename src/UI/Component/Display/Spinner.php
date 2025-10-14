<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Display;

/**
 * Spinner Component
 *
 * Provides animated spinner for indicating loading/processing state.
 * Updates automatically when update() is called regularly (e.g., in event loop).
 *
 * Available styles:
 * - BRAILLE: ⠋ ⠙ ⠹ ⠸ ⠼ ⠴ ⠦ ⠧ ⠇ ⠏
 * - DOTS: ⠋ ⠙ ⠹ ⠸ ⠼ ⠴ ⠦ ⠧ ⠇ ⠏
 * - LINE: - \ | /
 * - ARROW: ← ↖ ↑ ↗ → ↘ ↓ ↙
 * - DOTS_BOUNCE: ⠁ ⠂ ⠄ ⠂
 * - CIRCLE: ◐ ◓ ◑ ◒
 * - SQUARE: ◰ ◳ ◲ ◱
 * - CLOCK: 🕐 🕑 🕒 🕓 🕔 🕕 🕖 🕗 🕘 🕙 🕚 🕛
 */
final class Spinner
{
    public const string STYLE_BRAILLE = 'braille';
    public const string STYLE_DOTS = 'dots';
    public const string STYLE_LINE = 'line';
    public const string STYLE_ARROW = 'arrow';
    public const string STYLE_DOTS_BOUNCE = 'dots_bounce';
    public const string STYLE_CIRCLE = 'circle';
    public const string STYLE_SQUARE = 'square';
    public const string STYLE_CLOCK = 'clock';
    private const array FRAMES = [
        self::STYLE_BRAILLE => ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'],
        self::STYLE_DOTS => ['⣾', '⣽', '⣻', '⢿', '⡿', '⣟', '⣯', '⣷'],
        self::STYLE_LINE => ['-', '\\', '|', '/'],
        self::STYLE_ARROW => ['←', '↖', '↑', '↗', '→', '↘', '↓', '↙'],
        self::STYLE_DOTS_BOUNCE => ['⠁', '⠂', '⠄', '⡀', '⢀', '⠠', '⠐', '⠈'],
        self::STYLE_CIRCLE => ['◐', '◓', '◑', '◒'],
        self::STYLE_SQUARE => ['◰', '◳', '◲', '◱'],
        self::STYLE_CLOCK => ['🕐', '🕑', '🕒', '🕓', '🕔', '🕕', '🕖', '🕗', '🕘', '🕙', '🕚', '🕛'],
    ];

    private array $frames;
    private int $currentFrame = 0;
    private float $lastUpdate = 0;
    private bool $running = false;

    /**
     * @param string $style Spinner style (use STYLE_* constants)
     * @param float $interval Update interval in seconds (default: 0.1 = 100ms)
     */
    public function __construct(
        string $style = self::STYLE_BRAILLE,
        private float $interval = 0.1,
    ) {
        $this->frames = self::FRAMES[$style] ?? self::FRAMES[self::STYLE_BRAILLE];
        $this->lastUpdate = \microtime(true);
    }

    /**
     * Create spinner with specific style
     */
    public static function create(string $style = self::STYLE_BRAILLE, float $interval = 0.1): self
    {
        return new self($style, $interval);
    }

    /**
     * Create and start spinner in one call
     */
    public static function createAndStart(string $style = self::STYLE_BRAILLE, float $interval = 0.1): self
    {
        $spinner = new self($style, $interval);
        $spinner->start();
        return $spinner;
    }

    /**
     * Start the spinner
     */
    public function start(): void
    {
        $this->running = true;
        $this->currentFrame = 0;
        $this->lastUpdate = \microtime(true);
    }

    /**
     * Stop the spinner
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Check if spinner is running
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Reset spinner to first frame
     */
    public function reset(): void
    {
        $this->currentFrame = 0;
        $this->lastUpdate = \microtime(true);
    }

    /**
     * Update spinner state (advance frame if interval elapsed)
     * Should be called in the main update loop
     */
    public function update(): void
    {
        if (!$this->running) {
            return;
        }

        $now = \microtime(true);
        if ($now - $this->lastUpdate >= $this->interval) {
            $this->currentFrame = ($this->currentFrame + 1) % \count($this->frames);
            $this->lastUpdate = $now;
        }
    }

    /**
     * Get current frame character
     */
    public function getCurrentFrame(): string
    {
        return $this->frames[$this->currentFrame];
    }

    /**
     * Get current frame with optional prefix/suffix
     */
    public function render(string $prefix = '', string $suffix = ''): string
    {
        return $prefix . $this->getCurrentFrame() . $suffix;
    }

    /**
     * Get spinner frame at specific index
     */
    public function getFrame(int $index): string
    {
        $index = $index % \count($this->frames);
        return $this->frames[$index];
    }

    /**
     * Get total number of frames
     */
    public function getFrameCount(): int
    {
        return \count($this->frames);
    }

    /**
     * Set update interval
     */
    public function setInterval(float $interval): void
    {
        $this->interval = $interval;
    }

    /**
     * Get update interval
     */
    public function getInterval(): float
    {
        return $this->interval;
    }
}
