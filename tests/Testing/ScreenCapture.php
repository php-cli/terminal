<?php

declare(strict_types=1);

namespace Tests\Testing;

/**
 * Captured screen state for test assertions.
 */
final readonly class ScreenCapture
{
    /**
     * @param array<int, array<int, array{char: string, color: string}>> $buffer
     */
    public function __construct(
        private array $buffer,
        private int $width,
        private int $height,
    ) {}

    /**
     * Get text at position.
     */
    public function getText(int $x, int $y, int $length): string
    {
        if ($y < 0 || $y >= $this->height) {
            return '';
        }

        $text = '';
        for ($i = 0; $i < $length; $i++) {
            $posX = $x + $i;
            if ($posX >= 0 && $posX < $this->width && isset($this->buffer[$y][$posX])) {
                $text .= $this->buffer[$y][$posX]['char'];
            }
        }

        return $text;
    }

    /**
     * Get entire line (trimmed).
     */
    public function getLine(int $y): string
    {
        if ($y < 0 || $y >= $this->height || !isset($this->buffer[$y])) {
            return '';
        }

        $line = '';
        foreach ($this->buffer[$y] as $cell) {
            $line .= $cell['char'];
        }

        return \rtrim($line);
    }

    /**
     * Get line with trailing spaces trimmed (alias for getLine).
     */
    public function getLineTrimmed(int $y): string
    {
        return $this->getLine($y);
    }

    /**
     * Get line without trimming.
     */
    public function getLineRaw(int $y): string
    {
        if ($y < 0 || $y >= $this->height || !isset($this->buffer[$y])) {
            return '';
        }

        $line = '';
        foreach ($this->buffer[$y] as $cell) {
            $line .= $cell['char'];
        }

        return $line;
    }

    /**
     * Check if screen contains text anywhere.
     */
    public function contains(string $text): bool
    {
        return $this->findText($text) !== null;
    }

    /**
     * Find text position on screen.
     *
     * @return array{x: int, y: int}|null
     */
    public function findText(string $text): ?array
    {
        for ($y = 0; $y < $this->height; $y++) {
            $line = $this->getLineRaw($y);
            $pos = \mb_strpos($line, $text);
            if ($pos !== false) {
                return ['x' => $pos, 'y' => $y];
            }
        }

        return null;
    }

    /**
     * Find all occurrences of text.
     *
     * @return array<array{x: int, y: int}>
     */
    public function findAllText(string $text): array
    {
        $results = [];
        $textLength = \mb_strlen($text);

        for ($y = 0; $y < $this->height; $y++) {
            $line = $this->getLineRaw($y);
            $offset = 0;

            while (($pos = \mb_strpos($line, $text, $offset)) !== false) {
                $results[] = ['x' => $pos, 'y' => $y];
                $offset = $pos + $textLength;
            }
        }

        return $results;
    }

    /**
     * Get rectangular region as array of lines.
     *
     * @return array<string>
     */
    public function getRegion(int $x, int $y, int $width, int $height): array
    {
        $lines = [];

        for ($row = 0; $row < $height; $row++) {
            $lines[] = $this->getText($x, $y + $row, $width);
        }

        return $lines;
    }

    /**
     * Check if position has specific color.
     */
    public function hasColorAt(int $x, int $y, string $expectedColor): bool
    {
        if ($y < 0 || $y >= $this->height || $x < 0 || $x >= $this->width) {
            return false;
        }

        return isset($this->buffer[$y][$x]) && $this->buffer[$y][$x]['color'] === $expectedColor;
    }

    /**
     * Get color at position.
     */
    public function getColorAt(int $x, int $y): string
    {
        if ($y < 0 || $y >= $this->height || $x < 0 || $x >= $this->width) {
            return '';
        }

        return $this->buffer[$y][$x]['color'] ?? '';
    }

    /**
     * Get screen dimensions.
     *
     * @return array{width: int, height: int}
     */
    public function getSize(): array
    {
        return ['width' => $this->width, 'height' => $this->height];
    }

    /**
     * Dump screen to string for debugging.
     */
    public function dump(): string
    {
        $lines = [];
        $lines[] = \str_repeat('─', $this->width + 2);

        for ($y = 0; $y < $this->height; $y++) {
            $lines[] = '│' . $this->getLineRaw($y) . '│';
        }

        $lines[] = \str_repeat('─', $this->width + 2);

        return \implode("\n", $lines);
    }

    /**
     * Dump with line numbers (useful for debugging).
     */
    public function dumpWithLineNumbers(): string
    {
        $output = "Screen ({$this->width}x{$this->height}):\n";
        $lineNumWidth = \strlen((string) $this->height);

        for ($y = 0; $y < $this->height; $y++) {
            $lineNum = \str_pad((string) $y, $lineNumWidth, ' ', STR_PAD_LEFT);
            $output .= "{$lineNum}│{$this->getLineRaw($y)}│\n";
        }

        return $output;
    }

    /**
     * Get all non-empty lines (trimmed).
     *
     * @return array<int, string> Line number => content
     */
    public function getNonEmptyLines(): array
    {
        $result = [];

        for ($y = 0; $y < $this->height; $y++) {
            $line = $this->getLineTrimmed($y);
            if ($line !== '') {
                $result[$y] = $line;
            }
        }

        return $result;
    }

    /**
     * Get raw buffer for advanced inspection.
     *
     * @return array<int, array<int, array{char: string, color: string}>>
     */
    public function getBuffer(): array
    {
        return $this->buffer;
    }
}
