<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\FileBrowser\Component;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * File content viewer with line numbers and scrolling
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
        $this->lines = \explode("\n", $content);
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

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);
        $this->visibleLines = $height;

        if (empty($this->lines)) {
            return;
        }

        $endIndex = \min(
            $this->scrollOffset + $height,
            \count($this->lines),
        );

        // Render lines with line numbers
        for ($i = $this->scrollOffset; $i < $endIndex; $i++) {
            $rowY = $y + ($i - $this->scrollOffset);
            $line = $this->lines[$i];

            // Line numbers: "   1 │ "
            $lineNumber = \str_pad((string) ($i + 1), 4, ' ', STR_PAD_LEFT) . ' │ ';

            // Combine line number and content
            $displayText = $lineNumber . $line;

            // Truncate or pad line to fit width
            $displayText = \mb_substr($displayText, 0, $width);
            $displayText = \str_pad($displayText, $width);

            $renderer->writeAt($x, $rowY, $displayText, ColorScheme::NORMAL_TEXT);
        }

        // Draw scrollbar if needed
        if (\count($this->lines) > $height) {
            $this->drawScrollbar($renderer, $x + $width - 1, $y, $height);
        }

        // Show scroll position indicator
        if (\count($this->lines) > $height) {
            $position = \sprintf(
                '%d-%d/%d',
                $this->scrollOffset + 1,
                \min($this->scrollOffset + $height, \count($this->lines)),
                \count($this->lines),
            );
            $renderer->writeAt(
                $x + $width - \mb_strlen($position) - 1,
                $y + $height - 1,
                $position,
                ColorScheme::combine(ColorScheme::BG_CYAN, ColorScheme::FG_BLACK),
            );
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        if (!$this->isFocused() || empty($this->lines)) {
            return false;
        }

        switch ($key) {
            case 'UP':
                if ($this->scrollOffset > 0) {
                    $this->scrollOffset--;
                }
                return true;

            case 'DOWN':
                if ($this->scrollOffset < \count($this->lines) - $this->visibleLines) {
                    $this->scrollOffset++;
                }
                return true;

            case 'PAGE_UP':
                $this->scrollOffset = \max(0, $this->scrollOffset - $this->visibleLines);
                return true;

            case 'PAGE_DOWN':
                $this->scrollOffset = \min(
                    \max(0, \count($this->lines) - $this->visibleLines),
                    $this->scrollOffset + $this->visibleLines,
                );
                return true;

            case 'HOME':
                $this->scrollOffset = 0;
                return true;

            case 'END':
                $this->scrollOffset = \max(0, \count($this->lines) - $this->visibleLines);
                return true;
        }

        return false;
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
            $renderer->writeAt($x, $y + $i, $char, ColorScheme::SCROLLBAR);
        }
    }
}
