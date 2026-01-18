<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Display;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;

/**
 * Text display component with scrolling support
 */
final class TextDisplay extends AbstractComponent
{
    /** @var array<string> */
    private array $lines = [];

    private int $scrollOffset = 0;
    private int $visibleLines = 0;
    private bool $autoScroll = true;
    private readonly Scrollbar $scrollbar;

    /**
     * @param string|\Stringable $text Initial text content
     */
    public function __construct(string|\Stringable $text = '')
    {
        $this->scrollbar = new Scrollbar();

        if ($text !== '') {
            $this->setText($text);
        }
    }

    /**
     * Set text content
     *
     * @param string|\Stringable $text Text content or Stringable component
     */
    public function setText(string|\Stringable $text): void
    {
        $textStr = (string) $text;
        $this->lines = \explode("\n", $textStr);

        // Auto-scroll to bottom if enabled
        if ($this->autoScroll) {
            $this->scrollToBottom();
        }
    }

    /**
     * Get current text content
     */
    public function getText(): string
    {
        return \implode("\n", $this->lines);
    }

    /**
     * Append text to content
     *
     * @param string|\Stringable $text Text content or Stringable component
     */
    public function appendText(string|\Stringable $text): void
    {
        $textStr = (string) $text;
        $newLines = \explode("\n", $textStr);

        if (!empty($this->lines) && !empty($newLines)) {
            // Append first new line to last existing line
            $this->lines[\count($this->lines) - 1] .= \array_shift($newLines);
        }

        $this->lines = \array_merge($this->lines, $newLines);

        // Auto-scroll to bottom if enabled
        if ($this->autoScroll) {
            $this->scrollToBottom();
        }
    }

    /**
     * Clear text content
     */
    public function clear(): void
    {
        $this->lines = [];
        $this->scrollOffset = 0;
    }

    /**
     * Enable/disable auto-scroll to bottom
     */
    public function setAutoScroll(bool $autoScroll): void
    {
        $this->autoScroll = $autoScroll;
    }

    /**
     * Scroll to bottom
     */
    public function scrollToBottom(): void
    {
        if ($this->visibleLines > 0) {
            $this->scrollOffset = \max(0, \count($this->lines) - $this->visibleLines);
        }
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);
        $this->visibleLines = $height;
        $theme = $renderer->getThemeContext();

        if (empty($this->lines)) {
            return;
        }

        // Check if scrollbar is needed and reserve space for it
        $needsScrollbar = Scrollbar::needsScrollbar(\count($this->lines), $this->visibleLines);
        $contentWidth = $needsScrollbar ? $width - 1 : $width;

        // Calculate visible range
        $endIndex = \min(
            $this->scrollOffset + $this->visibleLines,
            \count($this->lines),
        );

        // Render lines
        for ($i = $this->scrollOffset; $i < $endIndex; $i++) {
            $rowY = $y + ($i - $this->scrollOffset);
            $line = $this->lines[$i];

            // Word wrap if line is too long (using content width)
            $wrappedLines = $this->wrapLine($line, $contentWidth);

            foreach ($wrappedLines as $wrappedLine) {
                if ($rowY >= $y + $height) {
                    break;
                }

                $renderer->writeAt(
                    $x,
                    $rowY,
                    $wrappedLine,
                    $theme->getNormalText(),
                );

                $rowY++;
            }
        }

        // Draw scrollbar if needed
        if ($needsScrollbar) {
            $this->scrollbar->render(
                $renderer,
                x: $x + $contentWidth,
                y: $y,
                height: $height,
                theme: $theme,
                totalItems: \count($this->lines),
                visibleItems: $this->visibleLines,
                scrollOffset: $this->scrollOffset,
            );
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        if (!$this->isFocused()) {
            return false;
        }

        $input = KeyInput::from($key);
        $maxOffset = \max(0, \count($this->lines) - $this->visibleLines);

        return match (true) {
            $input->is(Key::UP) => $this->scrollOffset > 0
                ? (--$this->scrollOffset !== null) && ($this->autoScroll = false) === false
                : true,
            $input->is(Key::DOWN) => $this->scrollOffset < $maxOffset
                ? ++$this->scrollOffset !== null
                : true,
            $input->is(Key::PAGE_UP) => ($this->scrollOffset = \max(0, $this->scrollOffset - $this->visibleLines)) !== null
                && ($this->autoScroll = false) === false,
            $input->is(Key::PAGE_DOWN) => ($this->scrollOffset = \min($maxOffset, $this->scrollOffset + $this->visibleLines)) !== null,
            $input->is(Key::HOME) => ($this->scrollOffset = 0) !== null
                && ($this->autoScroll = false) === false,
            $input->is(Key::END) => $this->scrollToBottom() === null
                && ($this->autoScroll = true),
            default => false,
        };
    }

    #[\Override]
    public function getMinSize(): array
    {
        return ['width' => 20, 'height' => 5];
    }

    /**
     * Wrap a line to fit within width
     *
     * @return array<string>
     */
    private function wrapLine(string $line, int $width): array
    {
        if (\mb_strlen($line) <= $width) {
            return [\str_pad($line, $width)];
        }

        $wrapped = [];
        $remaining = $line;

        while (\mb_strlen($remaining) > 0) {
            $chunk = \mb_substr($remaining, 0, $width);
            $wrapped[] = \str_pad($chunk, $width);
            $remaining = \mb_substr($remaining, $width);
        }

        return $wrapped;
    }
}
