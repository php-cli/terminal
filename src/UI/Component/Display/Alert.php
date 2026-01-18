<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Display;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\ComponentInterface;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Alert Component
 *
 * Displays messages with different severity levels:
 * - success (green background)
 * - error (red background)
 * - warning (yellow background)
 * - info (blue background)
 */
final class Alert implements ComponentInterface
{
    public const string TYPE_SUCCESS = 'success';
    public const string TYPE_ERROR = 'error';
    public const string TYPE_WARNING = 'warning';
    public const string TYPE_INFO = 'info';

    private bool $focused = false;
    private int $x = 0;
    private int $y = 0;
    private int $width = 0;
    private int $height = 0;
    private ?float $shownAt = null;
    private float $autoHideAfter = 3.0;

    public function __construct(
        private string $message,
        private string $type = self::TYPE_INFO,
        ?float $autoHideAfter = null,
    ) {
        $this->shownAt = \microtime(true);
        if ($autoHideAfter !== null) {
            $this->autoHideAfter = $autoHideAfter;
        }
    }

    public static function success(string $message): self
    {
        return new self($message, self::TYPE_SUCCESS);
    }

    public static function error(string $message): self
    {
        return new self($message, self::TYPE_ERROR);
    }

    public static function warning(string $message): self
    {
        return new self($message, self::TYPE_WARNING);
    }

    public static function info(string $message): self
    {
        return new self($message, self::TYPE_INFO);
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    #[\Override]
    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->x = $x;
        $this->y = $y;
        $this->width = $width;
        $this->height = $height;

        // Get color scheme based on type
        $colorScheme = $this->getColorScheme();

        // Get icon based on type
        $icon = $this->getIcon();

        // Format message with padding
        $messageText = " {$icon} {$this->message} ";
        $messageLength = \mb_strlen($messageText);

        // Center the message if it's shorter than width
        $padding = $width > $messageLength ? (int) (($width - $messageLength) / 2) : 0;
        $centeredMessage = \str_repeat(' ', $padding) . $messageText . \str_repeat(
            ' ',
            $width - $messageLength - $padding,
        );

        // Truncate if message is too long
        if ($messageLength > $width) {
            $centeredMessage = \mb_substr($messageText, 0, $width - 3) . '...';
        }

        // Render the alert
        $renderer->writeAt($x, $y, $centeredMessage, $colorScheme);
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        // Alerts don't handle input
        return false;
    }

    #[\Override]
    public function setFocused(bool $focused): void
    {
        $this->focused = $focused;
    }

    #[\Override]
    public function isFocused(): bool
    {
        return $this->focused;
    }

    #[\Override]
    public function update(): void
    {
        // Auto-hide timer is checked externally via isExpired()
    }

    /**
     * Check if the alert should be hidden (expired)
     */
    public function isExpired(): bool
    {
        if ($this->shownAt === null) {
            return false;
        }

        return (\microtime(true) - $this->shownAt) >= $this->autoHideAfter;
    }

    /**
     * Reset the timer (show alert again)
     */
    public function resetTimer(): void
    {
        $this->shownAt = \microtime(true);
    }

    /**
     * Set auto-hide duration in seconds (0 = never hide)
     */
    public function setAutoHideAfter(float $seconds): void
    {
        $this->autoHideAfter = $seconds;
    }

    #[\Override]
    public function getMinSize(): array
    {
        // Alert needs minimum width for message + icon + padding
        // Height is always 1 line
        $messageLength = \mb_strlen($this->message) + 6; // +6 for icon, spaces, padding
        return ['width' => \max(20, $messageLength), 'height' => 1];
    }

    private function getColorScheme(): string
    {
        return match ($this->type) {
            self::TYPE_SUCCESS => ColorScheme::combine(ColorScheme::BG_GREEN, ColorScheme::FG_BLACK, ColorScheme::BOLD),
            self::TYPE_ERROR => ColorScheme::combine(ColorScheme::BG_RED, ColorScheme::FG_WHITE, ColorScheme::BOLD),
            self::TYPE_WARNING => ColorScheme::combine(
                ColorScheme::BG_YELLOW,
                ColorScheme::FG_BLACK,
                ColorScheme::BOLD,
            ),
            self::TYPE_INFO => ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_WHITE, ColorScheme::BOLD),
            default => ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_WHITE, ColorScheme::BOLD),
        };
    }

    private function getIcon(): string
    {
        return match ($this->type) {
            self::TYPE_SUCCESS => '✅',
            self::TYPE_ERROR => '❌',
            self::TYPE_WARNING => '⚠️',
            self::TYPE_INFO => 'ℹ️',
            default => 'ℹ️',
        };
    }
}
