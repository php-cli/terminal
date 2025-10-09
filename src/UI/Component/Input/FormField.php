<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Input;

use Butschster\Commander\Infrastructure\Terminal\Renderer;

/**
 * Base class for form fields
 */
abstract class FormField
{
    public function __construct(
        protected string $name,
        protected string $label,
        protected mixed $value = null,
        protected string $description = '',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }

    /**
     * Render the field and return height used
     */
    abstract public function render(Renderer $renderer, int $x, int $y, int $width, bool $focused): int;

    /**
     * Handle keyboard input
     */
    abstract public function handleInput(string $key): bool;
}
