<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\FileBrowser\Component;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * File content viewer with line numbers and scrolling
 *
 * Now with proper width management to prevent rendering artifacts.
 */
final class FileContentViewer extends AbstractComponent
{
    /** @var array<string> */
    private array $lines = [];

    private int $scrollOffset = 0;
    private int $visibleLines = 0;

    /**
     * Set file content
     */
    public function setContent(string $content): void
    {
        // Normalize line endings: CRLF -> LF, CR -> LF
        $normalized = \str_replace(["\r\n", "\r"], "\n", $content);
        $this->lines = \explode("\n", $normalized);
        $this->scrollOffset = 0;
    }

    /**
     * Clear content
     */
    public function clear(): void
    {
        $this->lines = [];
        $this->scrollOffset = 0;
    }

    #[\Override]
    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);
        $this->visibleLines = $height;

        if (empty($this->lines)) {
            return;
        }

        // Reserve space for scrollbar if needed
        $hasScrollbar = \count($this->lines) > $height;
        $scrollbarWidth = $hasScrollbar ? 1 : 0;

        // Calculate line number width (e.g., "1234 │ " = 7 chars for 4-digit numbers)
        $maxLineNumber = \count($this->lines);
        $lineNumberWidth = \strlen((string) $maxLineNumber) + 3; // number + " │ "

        // Content width = total - line numbers - scrollbar
        $contentWidth = $width - $lineNumberWidth - $scrollbarWidth;

        $endIndex = \min(
            $this->scrollOffset + $height,
            \count($this->lines),
        );

        // Render lines with line numbers
        for ($i = $this->scrollOffset; $i < $endIndex; $i++) {
            $rowY = $y + ($i - $this->scrollOffset);
            $line = $this->lines[$i];

            // Format line number with separator (right-aligned)
            $lineNumber = \str_pad((string) ($i + 1), $lineNumberWidth - 3, ' ', STR_PAD_LEFT) . ' │ ';

            // Truncate content to fit available space
            $contentText = \mb_substr($line, 0, $contentWidth);
            $contentText = \str_pad($contentText, $contentWidth);

            // Combine line number and content
            $displayText = $lineNumber . $contentText;

            $renderer->writeAt($x, $rowY, $displayText, ColorScheme::$NORMAL_TEXT);
        }

        // Draw scrollbar if needed
        if ($hasScrollbar) {
            $this->drawScrollbar($renderer, $x + $width - 1, $y, $height);
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        if (!$this->isFocused() || empty($this->lines)) {
            return false;
        }

        $input = KeyInput::from($key);

        return match (true) {
            $input->is(Key::UP) => $this->scrollOffset > 0 ? --$this->scrollOffset !== null : true,
            $input->is(Key::DOWN) => $this->scrollOffset < \count($this->lines) - $this->visibleLines ? ++$this->scrollOffset !== null : true,
            $input->is(Key::PAGE_UP) => ($this->scrollOffset = \max(0, $this->scrollOffset - $this->visibleLines)) !== null,
            $input->is(Key::PAGE_DOWN) => ($this->scrollOffset = \min(\max(0, \count($this->lines) - $this->visibleLines), $this->scrollOffset + $this->visibleLines)) !== null,
            $input->is(Key::HOME) => ($this->scrollOffset = 0) !== null,
            $input->is(Key::END) => ($this->scrollOffset = \max(0, \count($this->lines) - $this->visibleLines)) !== null,
            default => false,
        };
    }

    #[\Override]
    public function getMinSize(): array
    {
        return ['width' => 40, 'height' => 10];
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
            $renderer->writeAt($x, $y + $i, $char, ColorScheme::$SCROLLBAR);
        }
    }
}
