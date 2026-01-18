<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Component;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\ComponentInterface;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Inline search filter for package tables.
 *
 * Activation: Press / or Ctrl+F
 * Deactivation: Press Escape or Enter
 * Filter: As you type
 */
final class SearchFilter implements ComponentInterface
{
    private string $query = '';
    private bool $isActive = false;
    private bool $focused = false;
    private int $cursorPosition = 0;

    /** @var callable(string): void */
    private $onFilter;

    /**
     * @param callable(string): void $onFilter Called when query changes
     */
    public function __construct(callable $onFilter)
    {
        $this->onFilter = $onFilter;
    }

    /**
     * Activate search mode.
     */
    public function activate(): void
    {
        $this->isActive = true;
        $this->cursorPosition = \mb_strlen($this->query);
    }

    /**
     * Deactivate search mode.
     */
    public function deactivate(): void
    {
        $this->isActive = false;
    }

    /**
     * Clear the search query.
     */
    public function clear(): void
    {
        $this->query = '';
        $this->cursorPosition = 0;
        ($this->onFilter)('');
    }

    /**
     * Check if search is active (accepting input).
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Get current search query.
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * Check if there's an active filter.
     */
    public function hasFilter(): bool
    {
        return $this->query !== '';
    }

    #[\Override]
    public function render(Renderer $renderer, int $x, int $y, ?int $width, ?int $height): void
    {
        if ($width === null) {
            $width = 30;
        }

        $bgColor = $this->isActive
            ? ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_WHITE)
            : ColorScheme::$NORMAL_TEXT;

        // Render search box
        $label = $this->isActive ? '/' : ($this->query !== '' ? '/' : ' ');
        $displayQuery = $this->query;

        // Truncate if too long
        $maxQueryLen = $width - 4; // "/ " + space for cursor
        if (\mb_strlen($displayQuery) > $maxQueryLen) {
            $displayQuery = '...' . \mb_substr($displayQuery, -($maxQueryLen - 3));
        }

        $text = $label . ' ' . $displayQuery;

        // Pad to width
        $text = \str_pad($text, $width);

        $renderer->writeAt($x, $y, $text, $bgColor);

        // Draw cursor when active
        if ($this->isActive) {
            $cursorX = $x + 2 + \min($this->cursorPosition, $maxQueryLen);
            $cursorColor = ColorScheme::combine(ColorScheme::BG_WHITE, ColorScheme::FG_BLACK);
            $cursorChar = $this->cursorPosition < \mb_strlen($this->query)
                ? \mb_substr($this->query, $this->cursorPosition, 1)
                : ' ';
            $renderer->writeAt($cursorX, $y, $cursorChar, $cursorColor);
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        if (!$this->isActive) {
            return false;
        }

        $input = KeyInput::from($key);

        // Escape to cancel/clear
        if ($input->is(Key::ESCAPE)) {
            if ($this->query !== '') {
                $this->clear();
            } else {
                $this->deactivate();
            }
            return true;
        }

        // Enter to confirm and deactivate
        if ($input->is(Key::ENTER)) {
            $this->deactivate();
            return true;
        }

        // Backspace
        if ($input->is(Key::BACKSPACE)) {
            if ($this->cursorPosition > 0) {
                $this->query = \mb_substr($this->query, 0, $this->cursorPosition - 1)
                    . \mb_substr($this->query, $this->cursorPosition);
                $this->cursorPosition--;
                ($this->onFilter)($this->query);
            }
            return true;
        }

        // Delete
        if ($input->is(Key::DELETE)) {
            if ($this->cursorPosition < \mb_strlen($this->query)) {
                $this->query = \mb_substr($this->query, 0, $this->cursorPosition)
                    . \mb_substr($this->query, $this->cursorPosition + 1);
                ($this->onFilter)($this->query);
            }
            return true;
        }

        // Left arrow
        if ($input->is(Key::LEFT)) {
            if ($this->cursorPosition > 0) {
                $this->cursorPosition--;
            }
            return true;
        }

        // Right arrow
        if ($input->is(Key::RIGHT)) {
            if ($this->cursorPosition < \mb_strlen($this->query)) {
                $this->cursorPosition++;
            }
            return true;
        }

        // Home
        if ($input->is(Key::HOME)) {
            $this->cursorPosition = 0;
            return true;
        }

        // End
        if ($input->is(Key::END)) {
            $this->cursorPosition = \mb_strlen($this->query);
            return true;
        }

        // Ctrl+U to clear
        if ($input->isCtrl(Key::U)) {
            $this->clear();
            return true;
        }

        // Regular character input
        if (\mb_strlen($key) === 1 && \ord($key) >= 32) {
            $this->query = \mb_substr($this->query, 0, $this->cursorPosition)
                . $key
                . \mb_substr($this->query, $this->cursorPosition);
            $this->cursorPosition++;
            ($this->onFilter)($this->query);
            return true;
        }

        return true; // Consume all input when active
    }

    #[\Override]
    public function setFocused(bool $focused): void
    {
        $this->focused = $focused;
    }

    #[\Override]
    public function isFocused(): bool
    {
        return $this->focused || $this->isActive;
    }

    #[\Override]
    public function update(): void
    {
        // No animation
    }

    #[\Override]
    public function getMinSize(): array
    {
        return ['width' => 20, 'height' => 1];
    }
}
