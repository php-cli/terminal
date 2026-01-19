<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\Git\Component;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Component\Display\Scrollbar;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Diff Viewer Component
 *
 * Displays git diff output with syntax highlighting:
 * - Green for added lines (+)
 * - Red for removed lines (-)
 * - Cyan for diff headers (@@)
 * - Yellow for file headers (--- / +++)
 *
 * Supports scrolling with arrow keys, Page Up/Down, Home/End.
 */
final class DiffViewer extends AbstractComponent
{
    /** @var array<string> */
    private array $lines = [];

    private int $scrollOffset = 0;
    private int $visibleLines = 0;
    private bool $isDiff = true;
    private readonly Scrollbar $scrollbar;

    public function __construct()
    {
        $this->scrollbar = new Scrollbar();
    }

    /**
     * Set diff content
     *
     * @param string $content Diff or plain text content
     * @param bool $isDiff Whether to apply diff highlighting
     */
    public function setContent(string $content, bool $isDiff = true): void
    {
        $this->isDiff = $isDiff;
        $this->lines = \explode("\n", $content);
        $this->scrollOffset = 0;
    }

    /**
     * Get current content
     */
    public function getContent(): string
    {
        return \implode("\n", $this->lines);
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
        $theme = $renderer->getThemeContext();

        if (empty($this->lines)) {
            $renderer->writeAt($x, $y, 'No content', $theme->getMutedText());
            return;
        }

        // Check if scrollbar is needed
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

            // Truncate line if too long
            $displayLine = \mb_strlen($line) > $contentWidth
                ? \mb_substr($line, 0, $contentWidth - 1) . '…'
                : \str_pad($line, $contentWidth);

            // Get color based on line type
            $color = $this->isDiff
                ? $this->getDiffLineColor($line)
                : $theme->getNormalText();

            $renderer->writeAt($x, $rowY, $displayLine, $color);
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

        if ($input->is(Key::UP) || $key === 'k') {
            if ($this->scrollOffset > 0) {
                --$this->scrollOffset;
            }
            return true;
        }

        if ($input->is(Key::DOWN) || $key === 'j') {
            if ($this->scrollOffset < $maxOffset) {
                ++$this->scrollOffset;
            }
            return true;
        }

        if ($input->is(Key::PAGE_UP)) {
            $this->scrollOffset = \max(0, $this->scrollOffset - $this->visibleLines);
            return true;
        }

        if ($input->is(Key::PAGE_DOWN)) {
            $this->scrollOffset = \min($maxOffset, $this->scrollOffset + $this->visibleLines);
            return true;
        }

        if ($input->is(Key::HOME) || $key === 'g') {
            $this->scrollOffset = 0;
            return true;
        }

        if ($input->is(Key::END) || $key === 'G') {
            $this->scrollOffset = $maxOffset;
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
     * Get color for a diff line based on its prefix
     */
    private function getDiffLineColor(string $line): string
    {
        if ($line === '') {
            return ColorScheme::$NORMAL_TEXT;
        }

        $firstChar = $line[0];
        $firstTwo = \strlen($line) >= 2 ? \substr($line, 0, 2) : '';
        $firstThree = \strlen($line) >= 3 ? \substr($line, 0, 3) : '';
        $firstFour = \strlen($line) >= 4 ? \substr($line, 0, 4) : '';

        // File headers
        if ($firstThree === '---' || $firstThree === '+++') {
            return ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_YELLOW, ColorScheme::BOLD);
        }

        // Hunk headers @@ -1,3 +1,4 @@
        if ($firstTwo === '@@') {
            return ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_CYAN, ColorScheme::BOLD);
        }

        // Diff command header
        if (\str_starts_with($line, 'diff ')) {
            return ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_WHITE, ColorScheme::BOLD);
        }

        // Index line
        if (\str_starts_with($line, 'index ')) {
            return ColorScheme::$MUTED_TEXT;
        }

        // Added lines
        if ($firstChar === '+') {
            return ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_GREEN);
        }

        // Removed lines
        if ($firstChar === '-') {
            return ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_RED);
        }

        // Binary file info
        if (\str_starts_with($line, 'Binary files')) {
            return ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_MAGENTA);
        }

        // Context lines (unchanged)
        return ColorScheme::$NORMAL_TEXT;
    }
}
