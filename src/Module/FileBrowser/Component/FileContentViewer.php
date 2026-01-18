<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\FileBrowser\Component;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * File content viewer with line numbers and scrolling
 *
 * Supports both vertical and horizontal scrolling for files with long lines.
 */
final class FileContentViewer extends AbstractComponent
{
    /** @var array<string> */
    private array $lines = [];

    private int $scrollOffset = 0;
    private int $horizontalOffset = 0;
    private int $visibleLines = 0;
    private int $contentWidth = 0;
    private int $maxLineLength = 0;

    /**
     * Set file content
     */
    public function setContent(string $content): void
    {
        // Normalize line endings: CRLF -> LF, CR -> LF
        $normalized = \str_replace(["\r\n", "\r"], "\n", $content);
        $this->lines = \explode("\n", $normalized);
        $this->scrollOffset = 0;
        $this->horizontalOffset = 0;

        // Calculate max line length for horizontal scrolling
        $this->maxLineLength = 0;
        foreach ($this->lines as $line) {
            $length = \mb_strlen($line);
            if ($length > $this->maxLineLength) {
                $this->maxLineLength = $length;
            }
        }
    }

    /**
     * Clear content
     */
    public function clear(): void
    {
        $this->lines = [];
        $this->scrollOffset = 0;
        $this->horizontalOffset = 0;
        $this->maxLineLength = 0;
    }

    #[\Override]
    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);

        if (empty($this->lines)) {
            return;
        }

        // Reserve space for scrollbars if needed
        $hasVerticalScrollbar = \count($this->lines) > $height;
        $scrollbarWidth = $hasVerticalScrollbar ? 1 : 0;

        // Calculate line number width (e.g., "1234 │ " = 7 chars for 4-digit numbers)
        $maxLineNumber = \count($this->lines);
        $lineNumberWidth = \strlen((string) $maxLineNumber) + 3; // number + " │ "

        // Content width = total - line numbers - scrollbar
        $this->contentWidth = $width - $lineNumberWidth - $scrollbarWidth;

        // Check if horizontal scrolling is needed
        $hasHorizontalScrollbar = $this->maxLineLength > $this->contentWidth;

        // Reserve space for horizontal scrollbar at bottom
        $contentHeight = $hasHorizontalScrollbar ? $height - 1 : $height;
        $this->visibleLines = $contentHeight;

        $endIndex = \min(
            $this->scrollOffset + $contentHeight,
            \count($this->lines),
        );

        // Render lines with line numbers
        for ($i = $this->scrollOffset; $i < $endIndex; $i++) {
            $rowY = $y + ($i - $this->scrollOffset);
            $line = $this->lines[$i];

            // Format line number with separator (right-aligned)
            $lineNumber = \str_pad((string) ($i + 1), $lineNumberWidth - 3, ' ', STR_PAD_LEFT) . ' │ ';

            // Apply horizontal offset and truncate to fit available space
            $lineLength = \mb_strlen($line);
            if ($this->horizontalOffset < $lineLength) {
                $contentText = \mb_substr($line, $this->horizontalOffset, $this->contentWidth);
            } else {
                $contentText = '';
            }
            $contentText = \str_pad($contentText, $this->contentWidth);

            // Combine line number and content
            $displayText = $lineNumber . $contentText;

            $renderer->writeAt($x, $rowY, $displayText, ColorScheme::$NORMAL_TEXT);
        }

        // Draw vertical scrollbar if needed
        if ($hasVerticalScrollbar) {
            $this->drawVerticalScrollbar($renderer, $x + $width - 1, $y, $contentHeight);
        }

        // Draw horizontal scrollbar if needed
        if ($hasHorizontalScrollbar) {
            $this->drawHorizontalScrollbar($renderer, $x + $lineNumberWidth, $y + $contentHeight, $this->contentWidth);
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        if (!$this->isFocused() || empty($this->lines)) {
            return false;
        }

        $input = KeyInput::from($key);
        $horizontalStep = 10; // Characters to scroll horizontally per keypress

        return match (true) {
            // Vertical scrolling
            $input->is(Key::UP) => $this->scrollOffset > 0 ? --$this->scrollOffset !== null : true,
            $input->is(Key::DOWN) => $this->scrollOffset < \count($this->lines) - $this->visibleLines ? ++$this->scrollOffset !== null : true,
            $input->is(Key::PAGE_UP) => ($this->scrollOffset = \max(0, $this->scrollOffset - $this->visibleLines)) !== null,
            $input->is(Key::PAGE_DOWN) => ($this->scrollOffset = \min(\max(0, \count($this->lines) - $this->visibleLines), $this->scrollOffset + $this->visibleLines)) !== null,
            $input->is(Key::HOME) => $this->goToStart(),
            $input->is(Key::END) => $this->goToEnd(),
            // Horizontal scrolling
            $input->is(Key::LEFT) => $this->scrollLeft($horizontalStep),
            $input->is(Key::RIGHT) => $this->scrollRight($horizontalStep),
            default => false,
        };
    }

    /**
     * Scroll left by specified amount
     */
    private function scrollLeft(int $amount): bool
    {
        if ($this->horizontalOffset > 0) {
            $this->horizontalOffset = \max(0, $this->horizontalOffset - $amount);
        }
        return true;
    }

    /**
     * Scroll right by specified amount
     */
    private function scrollRight(int $amount): bool
    {
        $maxOffset = \max(0, $this->maxLineLength - $this->contentWidth);
        if ($this->horizontalOffset < $maxOffset) {
            $this->horizontalOffset = \min($maxOffset, $this->horizontalOffset + $amount);
        }
        return true;
    }

    /**
     * Go to start (top-left)
     */
    private function goToStart(): bool
    {
        $this->scrollOffset = 0;
        $this->horizontalOffset = 0;
        return true;
    }

    /**
     * Go to end (bottom of file)
     */
    private function goToEnd(): bool
    {
        $this->scrollOffset = \max(0, \count($this->lines) - $this->visibleLines);
        return true;
    }

    #[\Override]
    public function getMinSize(): array
    {
        return ['width' => 40, 'height' => 10];
    }

    /**
     * Draw vertical scrollbar indicator
     */
    private function drawVerticalScrollbar(Renderer $renderer, int $x, int $y, int $height): void
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
            $renderer->writeAt($x, $y + $i, $char, ColorScheme::$SCROLLBAR);
        }
    }

    /**
     * Draw horizontal scrollbar indicator
     */
    private function drawHorizontalScrollbar(Renderer $renderer, int $x, int $y, int $width): void
    {
        if ($this->maxLineLength <= $this->contentWidth) {
            return;
        }

        // Calculate thumb size and position
        $thumbWidth = \max(1, (int) ($width * $this->contentWidth / $this->maxLineLength));
        $maxOffset = $this->maxLineLength - $this->contentWidth;
        $thumbPosition = $maxOffset > 0 ? (int) ($width * $this->horizontalOffset / $this->maxLineLength) : 0;

        $scrollbar = '';
        for ($i = 0; $i < $width; $i++) {
            $scrollbar .= ($i >= $thumbPosition && $i < $thumbPosition + $thumbWidth) ? '█' : '░';
        }

        $renderer->writeAt($x, $y, $scrollbar, ColorScheme::$SCROLLBAR);
    }
}
