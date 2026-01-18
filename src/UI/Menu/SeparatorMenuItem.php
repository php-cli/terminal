<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Menu;

/**
 * Visual separator between menu items
 */
final readonly class SeparatorMenuItem extends AbstractMenuItem
{
    private const string SEPARATOR_LABEL = '─────────';

    public function __construct()
    {
        parent::__construct(self::SEPARATOR_LABEL, null);
    }

    public static function create(): self
    {
        return new self();
    }

    #[\Override]
    public function isSeparator(): bool
    {
        return true;
    }

    #[\Override]
    public function getHotkey(): ?string
    {
        return null;
    }
}
