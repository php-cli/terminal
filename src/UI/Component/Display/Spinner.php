<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Display;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;

/**
 * Spinner Component
 *
 * Provides animated spinner for indicating loading/processing state.
 * Implements ComponentInterface for use in layouts while providing
 * utility methods for direct frame access.
 *
 * Updates automatically when update() is called regularly (e.g., in event loop).
 *
 * Available styles:
 * - BRAILLE: ⠋ ⠙ ⠹ ⠸ ⠼ ⠴ ⠦ ⠧ ⠇ ⠏
 * - DOTS: ⣾ ⣽ ⣻ ⢿ ⡿ ⣟ ⣯ ⣷
 * - LINE: - \ | /
 * - ARROW: ← ↖ ↑ ↗ → ↘ ↓ ↙
 * - DOTS_BOUNCE: ⠁ ⠂ ⠄ ⡀ ⢀ ⠠ ⠐ ⠈
 * - CIRCLE: ◐ ◓ ◑ ◒
 * - SQUARE: ◰ ◳ ◲ ◱
 * - CLOCK: 🕐 🕑 🕒 🕓 🕔 🕕 🕖 🕗 🕘 🕙 🕚 🕛
 */
final class Spinner extends AbstractComponent
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
    private string $prefix = '';
    private string $suffix = '';
    private ?string $color = null;

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
     * Render spinner at specified position (ComponentInterface)
     */
    #[\Override]
    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);

        $frame = $this->getCurrentFrame();
        $text = $this->prefix . $frame . $this->suffix;

        // Truncate if needed
        if (\mb_strlen($text) > $width) {
            $text = \mb_substr($text, 0, $width);
        }

        $color = $this->color ?? $renderer->getThemeContext()->getNormalText();
        $renderer->writeAt($x, $y, $text, $color);
    }

    /**
     * Spinners don't handle input
     */
    #[\Override]
    public function handleInput(string $key): bool
    {
        return false;
    }

    /**
     * Update spinner state (advance frame if interval elapsed)
     * Should be called in the main update loop
     */
    #[\Override]
    public function update(): void
    {
        parent::update();

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
     * Get component's minimum size based on frame width and prefix/suffix
     */
    #[\Override]
    public function getMinSize(): array
    {
        $maxFrameWidth = \max(\array_map(\mb_strlen(...), $this->frames));
        return [
            'width' => \mb_strlen($this->prefix) + $maxFrameWidth + \mb_strlen($this->suffix),
            'height' => 1,
        ];
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
     * Get current frame character
     */
    public function getCurrentFrame(): string
    {
        return $this->frames[$this->currentFrame];
    }

    /**
     * Get current frame with optional prefix/suffix
     *
     * Use this method when you need the spinner text for string interpolation.
     * For rendering in layouts, use render() instead.
     */
    public function getFormattedText(string $prefix = '', string $suffix = ''): string
    {
        return $prefix . $this->getCurrentFrame() . $suffix;
    }

    /**
     * @deprecated Use getFormattedText() instead
     */
    public function renderText(string $prefix = '', string $suffix = ''): string
    {
        return $this->getFormattedText($prefix, $suffix);
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

    /**
     * Set prefix text displayed before spinner frame
     */
    public function setPrefix(string $prefix): self
    {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     * Get prefix text
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Set suffix text displayed after spinner frame
     */
    public function setSuffix(string $suffix): self
    {
        $this->suffix = $suffix;
        return $this;
    }

    /**
     * Get suffix text
     */
    public function getSuffix(): string
    {
        return $this->suffix;
    }

    /**
     * Set spinner color (ANSI escape sequence)
     */
    public function setColor(?string $color): self
    {
        $this->color = $color;
        return $this;
    }

    /**
     * Get spinner color
     */
    public function getColor(): ?string
    {
        return $this->color;
    }
}
