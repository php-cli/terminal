<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Display;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Theme\ColorScheme;

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

    /**
     * @param string $text Initial text content
     */
    public function __construct(string $text = '')
    {
        if ($text !== '') {
            $this->setText($text);
        }
    }

    /**
     * Set text content
     */
    public function setText(string $text): void
    {
        $this->lines = \explode("\n", $text);

        // Auto-scroll to bottom if enabled
        if ($this->autoScroll) {
            $this->scrollToBottom();
        }
    }

    /**
     * Append text to content
     */
    public function appendText(string $text): void
    {
        $newLines = \explode("\n", $text);

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

        if (empty($this->lines)) {
            return;
        }

        // Calculate visible range
        $endIndex = \min(
            $this->scrollOffset + $this->visibleLines,
            \count($this->lines),
        );

        // Render lines
        for ($i = $this->scrollOffset; $i < $endIndex; $i++) {
            $rowY = $y + ($i - $this->scrollOffset);
            $line = $this->lines[$i];

            // Word wrap if line is too long
            $wrappedLines = $this->wrapLine($line, $width);

            foreach ($wrappedLines as $wrappedLine) {
                if ($rowY >= $y + $height) {
                    break;
                }

                $renderer->writeAt(
                    $x,
                    $rowY,
                    $wrappedLine,
                    ColorScheme::NORMAL_TEXT,
                );

                $rowY++;
            }
        }

        // Draw scrollbar if needed
        if (\count($this->lines) > $this->visibleLines) {
            $this->drawScrollbar($renderer, $x + $width - 1, $y, $height);
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        if (!$this->isFocused()) {
            return false;
        }

        switch ($key) {
            case 'UP':
                if ($this->scrollOffset > 0) {
                    $this->scrollOffset--;
                    $this->autoScroll = false; // Disable auto-scroll when manually scrolling
                }
                return true;

            case 'DOWN':
                if ($this->scrollOffset < \count($this->lines) - $this->visibleLines) {
                    $this->scrollOffset++;
                }
                return true;

            case 'PAGE_UP':
                $this->scrollOffset = \max(0, $this->scrollOffset - $this->visibleLines);
                $this->autoScroll = false;
                return true;

            case 'PAGE_DOWN':
                $this->scrollOffset = \min(
                    \count($this->lines) - $this->visibleLines,
                    $this->scrollOffset + $this->visibleLines,
                );
                return true;

            case 'HOME':
                $this->scrollOffset = 0;
                $this->autoScroll = false;
                return true;

            case 'END':
                $this->scrollToBottom();
                $this->autoScroll = true;
                return true;
        }

        return false;
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

    /**
     * Draw scrollbar indicator
     */
    private function drawScrollbar(Renderer $renderer, int $x, int $y, int $height): void
    {
        $totalLines = \count($this->lines);

        if ($totalLines <= $this->visibleLines) {
            return;
        }

        // Calculate thumb size and position
        $thumbHeight = \max(1, (int) ($height * $this->visibleLines / $totalLines));
        $thumbPosition = (int) ($height * $this->scrollOffset / $totalLines);

        for ($i = 0; $i < $height; $i++) {
            $char = ($i >= $thumbPosition && $i < $thumbPosition + $thumbHeight) ? '█' : '░';
            $renderer->writeAt($x, $y + $i, $char, ColorScheme::SCROLLBAR);
        }
    }
}
