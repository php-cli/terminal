# Feature: Fix Spinner Component Interface

## Overview

The `Spinner` class has `render()` and `update()` methods but doesn't implement `ComponentInterface`, making it
incompatible with the component hierarchy. This creates confusion and prevents using Spinner in layout components.

## Stage Dependencies

```
Stage 1 (Refactor Spinner) → Stage 2 (Update Usages)
```

## Development Progress

### Stage 1: Refactor Spinner to Implement ComponentInterface

- [ ] Substep 1.1: Make Spinner extend AbstractComponent
- [ ] Substep 1.2: Implement proper `render(Renderer, x, y, width, height)` method
- [ ] Substep 1.3: Keep existing utility methods (`getCurrentFrame()`, `start()`, `stop()`)
- [ ] Substep 1.4: Add `getMinSize()` returning appropriate dimensions
- [ ] Substep 1.5: Ensure `handleInput()` returns false (spinners don't handle input)

**Notes**:
**Status**: Not Started
**Completed**:

---

### Stage 2: Update Existing Usages and Add Tests

- [ ] Substep 2.1: Search codebase for Spinner usages
- [ ] Substep 2.2: Update any direct `render()` calls to use component pattern
- [ ] Substep 2.3: Create `SpinnerTest.php` with rendering and state tests
- [ ] Substep 2.4: Document Spinner usage in layouts

**Notes**:
**Status**: Not Started
**Completed**:

---

## Codebase References

- `src/UI/Component/Display/Spinner.php:1-150` - Current implementation
- `src/UI/Component/AbstractComponent.php` - Base class to extend
- `src/UI/Component/ComponentInterface.php` - Interface to implement
- `docs/SpinnerComponent.md` - Existing documentation

## Current vs Proposed Implementation

### Current Spinner (Problematic)

```php
final class Spinner  // No interface!
{
    public function render(string $prefix = '', string $suffix = ''): string
    {
        return $prefix . $this->getCurrentFrame() . $suffix;
    }
    
    public function update(): void { /* ... */ }
}
```

### Proposed Spinner (Fixed)

```php
final class Spinner extends AbstractComponent
{
    // Keep existing static factory methods
    public static function create(string $style = self::STYLE_BRAILLE): self
    public static function createAndStart(string $style = self::STYLE_BRAILLE): self
    
    // Implement ComponentInterface::render()
    public function render(
        Renderer $renderer, 
        int $x, 
        int $y, 
        int $width, 
        int $height
    ): void {
        $this->setBounds($x, $y, $width, $height);
        
        $frame = $this->getCurrentFrame();
        $text = $this->prefix . $frame . $this->suffix;
        
        // Center or align based on configuration
        $renderer->writeAt($x, $y, $text, $this->color ?? ColorScheme::$NORMAL_TEXT);
    }
    
    // Keep existing methods as utilities
    public function getCurrentFrame(): string
    public function start(): void
    public function stop(): void
    public function isRunning(): bool
    
    // Renamed from render() to avoid confusion
    public function getFormattedText(string $prefix = '', string $suffix = ''): string
    {
        return $prefix . $this->getCurrentFrame() . $suffix;
    }
    
    // ComponentInterface requirements
    public function handleInput(string $key): bool
    {
        return false; // Spinners don't handle input
    }
    
    public function getMinSize(): array
    {
        $frameWidth = \mb_strlen($this->getCurrentFrame());
        return [
            'width' => $frameWidth + \mb_strlen($this->prefix) + \mb_strlen($this->suffix),
            'height' => 1,
        ];
    }
}
```

### Breaking Change Mitigation

The current `render(prefix, suffix)` method signature conflicts with `ComponentInterface::render()`. Options:

1. **Rename old method** to `getFormattedText()` (recommended)
2. **Add BC alias** via `__call()` magic method (complex)
3. **Breaking change** with clear deprecation notice

## Usage After Fix

```php
// In a layout (new capability)
$stack = new StackLayout(Direction::HORIZONTAL);
$stack->addChild($spinner, size: 3);
$stack->addChild(new TextDisplay('Loading...'));

// Direct rendering (still works)
$spinner->render($renderer, $x, $y, $width, $height);

// Get text representation (renamed method)
$text = $spinner->getFormattedText('[', '] Loading');
```

## Usage Instructions

⚠️ Keep this checklist updated:

- Mark completed substeps immediately with [x]
- Add notes about deviations or challenges
- Document decisions differing from plan
- Update status when starting/completing stages
- This is a breaking change - note migration in CHANGELOG
