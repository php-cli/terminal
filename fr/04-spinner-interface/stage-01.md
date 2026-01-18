# Stage 1: Refactor Spinner to Implement ComponentInterface

## Overview

Make the Spinner class extend AbstractComponent and implement ComponentInterface properly. This enables Spinner to be
used within layout containers (StackLayout, GridLayout, etc.) while preserving existing utility methods.

## Files

MODIFY:

- `src/UI/Component/Display/Spinner.php` - Extend AbstractComponent, implement proper render()

## Code References

- `src/UI/Component/Display/Spinner.php:1-179` - Current implementation (no interface)
- `src/UI/Component/AbstractComponent.php:1-155` - Base class to extend
- `src/UI/Component/ComponentInterface.php:1-56` - Interface requirements
- `src/Feature/ComposerManager/Tab/ScriptsTab.php:54,85-86,162-167` - Current usage pattern

## Implementation Details

### Current Spinner Signature Problem

```php
// Current (breaks ComponentInterface)
final class Spinner
{
    public function render(string $prefix = '', string $suffix = ''): string
    {
        return $prefix . $this->getCurrentFrame() . $suffix;
    }
}

// ComponentInterface requires
public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void;
```

### Required Changes

#### 1. Class Declaration

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Display;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Spinner Component
 *
 * Provides animated spinner for indicating loading/processing state.
 * Implements ComponentInterface for use in layouts while providing
 * utility methods for direct frame access.
 */
final class Spinner extends AbstractComponent
{
    // ... existing constants and properties

    private string $prefix = '';
    private string $suffix = '';
    private ?string $color = null;
```

#### 2. New render() Method (ComponentInterface)

```php
/**
 * Render spinner at specified position (ComponentInterface)
 */
public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
{
    $this->setBounds($x, $y, $width, $height);

    $frame = $this->getCurrentFrame();
    $text = $this->prefix . $frame . $this->suffix;

    // Truncate if needed
    if (\mb_strlen($text) > $width) {
        $text = \mb_substr($text, 0, $width);
    }

    $color = $this->color ?? $renderer->getThemeContext()->getNormalText();
    $renderer->writeAt($x, $y, $text, $color);
}
```

#### 3. Rename Old render() to getFormattedText()

```php
/**
 * Get current frame with optional prefix/suffix
 *
 * @deprecated Use render() for component rendering or getCurrentFrame() for raw access
 */
public function getFormattedText(string $prefix = '', string $suffix = ''): string
{
    return $prefix . $this->getCurrentFrame() . $suffix;
}

/**
 * @deprecated Use getFormattedText() instead
 */
public function renderText(string $prefix = '', string $suffix = ''): string
{
    return $this->getFormattedText($prefix, $suffix);
}
```

#### 4. Add Prefix/Suffix Setters

```php
/**
 * Set prefix text displayed before spinner frame
 */
public function setPrefix(string $prefix): self
{
    $this->prefix = $prefix;
    return $this;
}

/**
 * Set suffix text displayed after spinner frame
 */
public function setSuffix(string $suffix): self
{
    $this->suffix = $suffix;
    return $this;
}

/**
 * Set spinner color
 */
public function setColor(?string $color): self
{
    $this->color = $color;
    return $this;
}
```

#### 5. Implement getMinSize()

```php
public function getMinSize(): array
{
    $frameWidth = \max(\array_map(\mb_strlen(...), $this->frames));
    return [
        'width' => \mb_strlen($this->prefix) + $frameWidth + \mb_strlen($this->suffix),
        'height' => 1,
    ];
}
```

#### 6. Override handleInput() (No-op)

```php
/**
 * Spinners don't handle input
 */
public function handleInput(string $key): bool
{
    return false;
}
```

#### 7. Override update() for Animation

```php
/**
 * Update spinner state (advance frame if interval elapsed)
 */
#[\Override]
public function update(): void
{
    parent::update();

    if (!$this->running) {
        return;
    }

    $now = \microtime(true);
    if ($now - $this->lastUpdate >= $this->interval) {
        $this->currentFrame = ($this->currentFrame + 1) % \count($this->frames);
        $this->lastUpdate = $now;
    }
}
```

### Full Modified Class Structure

```php
final class Spinner extends AbstractComponent
{
    // Constants (unchanged)
    public const string STYLE_BRAILLE = 'braille';
    // ... other styles

    private const array FRAMES = [...];

    // Properties
    private array $frames;
    private int $currentFrame = 0;
    private float $lastUpdate = 0;
    private bool $running = false;
    private string $prefix = '';
    private string $suffix = '';
    private ?string $color = null;

    public function __construct(string $style = self::STYLE_BRAILLE, private float $interval = 0.1)

    // Static factories (unchanged)
    public static function create(string $style, float $interval): self
    public static function createAndStart(string $style, float $interval): self

    // State methods (unchanged)
    public function start(): void
    public function stop(): void
    public function isRunning(): bool
    public function reset(): void

    // Frame access methods (unchanged)
    public function getCurrentFrame(): string
    public function getFrame(int $index): string
    public function getFrameCount(): int

    // Interval methods (unchanged)
    public function setInterval(float $interval): void
    public function getInterval(): float

    // NEW: ComponentInterface implementation
    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    public function handleInput(string $key): bool
    public function getMinSize(): array
    #[\Override] public function update(): void

    // NEW: Configuration
    public function setPrefix(string $prefix): self
    public function setSuffix(string $suffix): self
    public function setColor(?string $color): self

    // RENAMED: Old render() method
    public function getFormattedText(string $prefix = '', string $suffix = ''): string
    /** @deprecated */ public function renderText(string $prefix, string $suffix): string
}
```

## Breaking Changes

| Old API                        | New API                                       | Migration      |
|--------------------------------|-----------------------------------------------|----------------|
| `$spinner->render('[ ', ' ]')` | `$spinner->getFormattedText('[ ', ' ]')`      | Find & replace |
| N/A                            | `$spinner->render($renderer, $x, $y, $w, $h)` | New capability |

## Definition of Done

- [ ] Spinner extends AbstractComponent
- [ ] Spinner implements ComponentInterface::render(Renderer, x, y, width, height)
- [ ] Old render() method renamed to getFormattedText()
- [ ] Deprecated renderText() alias added for backward compatibility
- [ ] setPrefix(), setSuffix(), setColor() fluent setters added
- [ ] getMinSize() returns correct dimensions based on frame width
- [ ] handleInput() returns false (spinners don't handle input)
- [ ] update() properly calls parent and advances frame
- [ ] All existing public methods preserved (start, stop, getCurrentFrame, etc.)
- [ ] PHPStan/Psalm passes with no new errors

## Dependencies

**Requires**: None
**Enables**: Stage 2 (update usages)
