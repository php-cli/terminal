<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Component;

use Butschster\Commander\UI\Component\Display\Text\TextComponent;

/**
 * Warning box component for displaying alerts
 */
final class WarningBox extends TextComponent
{
    private string $icon = '⚠️';
    private int $indent = 3;

    public function __construct(
        private readonly string $message,
        private readonly ?string $subMessage = null,
    ) {}

    /**
     * Set the warning icon
     */
    public function icon(string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * Set the indent for the submessage
     */
    public function messageIndent(int $indent): self
    {
        $this->indent = $indent;
        return $this;
    }

    protected function render(): string
    {
        $lines = [
            '',
            $this->icon . '  WARNING: ' . $this->message,
        ];

        if ($this->subMessage !== null) {
            $lines[] = \str_repeat(' ', $this->indent) . $this->subMessage;
        }

        return \implode("\n", $lines);
    }
}
