<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Component;

use Butschster\Commander\UI\Component\Display\Text\TextComponent;

/**
 * Header component for package information with decorative border
 */
final class PackageHeader extends TextComponent
{
    private string $borderChar = '═';
    private string $cornerChar = '║';
    private int $width = 58;

    public function __construct(
        private readonly string $title,
    ) {}

    /**
     * Set border style
     */
    public function border(string $char = '═', string $corner = '║'): self
    {
        $this->borderChar = $char;
        $this->cornerChar = $corner;
        return $this;
    }

    /**
     * Set header width
     */
    public function width(int $width): self
    {
        $this->width = $width;
        return $this;
    }

    protected function render(): string
    {
        $innerWidth = $this->width - 4; // Account for corners and padding
        $paddedTitle = \str_pad($this->title, $innerWidth);

        return \implode("\n", [
            '╔' . \str_repeat($this->borderChar, $this->width) . '╗',
            $this->cornerChar . '  ' . $paddedTitle . '  ' . $this->cornerChar,
            '╚' . \str_repeat($this->borderChar, $this->width) . '╝',
        ]);
    }
}
