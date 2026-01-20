# Feature: Fix Spinner Component Interface

## Overview

The `Spinner` class has `render()` and `update()` methods but doesn't implement `ComponentInterface`, making it
incompatible with the component hierarchy. This creates confusion and prevents using Spinner in layout components.

**Problem**: Current `render(string $prefix, string $suffix): string` conflicts with
`ComponentInterface::render(Renderer, x, y, width, height): void`

**Solution**: Extend `AbstractComponent`, rename old `render()` to `getFormattedText()`, implement proper component
rendering.

## Stage Dependencies

```
Stage 1 (Refactor Spinner) → Stage 2 (Unit Tests) → Stage 3 (E2E Tests)
```

## Development Progress

### Stage 1: Refactor Spinner to Implement ComponentInterface

See: [stage-01.md](stage-01.md)

- [x] Substep 1.1: Make Spinner extend AbstractComponent
- [x] Substep 1.2: Implement proper `render(Renderer, x, y, width, height)` method
- [x] Substep 1.3: Rename old `render()` to `getFormattedText()` with deprecated alias
- [x] Substep 1.4: Add `setPrefix()`, `setSuffix()`, `setColor()` fluent setters
- [x] Substep 1.5: Add `getMinSize()` returning frame width + prefix/suffix
- [x] Substep 1.6: Override `handleInput()` to return false (spinners don't handle input)
- [x] Substep 1.7: Override `update()` to call parent and advance frame

**Notes**: Also added getPrefix(), getSuffix(), getColor() getters for completeness.
**Status**: Complete
**Completed**: All substeps

---

### Stage 2: Update Existing Usages and Add Tests

See: [stage-02.md](stage-02.md)

- [x] Substep 2.1: Review ScriptsTab.php usage (uses `getCurrentFrame()` - no changes needed)
- [x] Substep 2.2: Create `tests/Unit/Component/Display/SpinnerTest.php`
- [x] Substep 2.3: Test state methods (start/stop/reset/isRunning)
- [x] Substep 2.4: Test frame methods (getCurrentFrame/getFrame/getFrameCount)
- [x] Substep 2.5: Test animation timing (update advances frame after interval)
- [x] Substep 2.6: Test all spinner styles (braille, dots, line, arrow, circle, square, clock)
- [x] Substep 2.7: Test ComponentInterface methods (render, handleInput, getMinSize)
- [x] Substep 2.8: Test legacy API (getFormattedText)

**Notes**: 61 unit tests created covering all functionality including edge cases, fluent setters, AbstractComponent inheritance, animation cycles, and interval edge cases.
**Status**: Complete
**Completed**: All substeps

---

### Stage 3: E2E Tests - Spinner in Real Application Workflows

See: [stage-03.md](stage-03.md)

- [x] Substep 3.1: Create `tests/E2E/Scenario/SpinnerWorkflowScenarioTest.php`
- [x] Substep 3.2: Test spinner animation during loading (frame changes over time)
- [x] Substep 3.3: Test spinner stops after async operation completes
- [x] Substep 3.4: Test spinner renders in horizontal stack layout
- [x] Substep 3.5: Test spinner with custom color
- [x] Substep 3.6: Test start/stop/reset state cycle
- [x] Substep 3.7: Test all 8 spinner styles render correctly (data provider)

**Notes**: 24 E2E tests created covering animation workflows, multiple spinners, different screen sizes, progress simulation, visibility toggle, error states, and all 8 spinner styles (via data provider).
**Status**: Complete
**Completed**: All substeps

---

## Codebase References

- `src/UI/Component/Display/Spinner.php:1-296` - Refactored implementation (extends AbstractComponent)
- `src/UI/Component/AbstractComponent.php:1-155` - Base class
- `src/UI/Component/ComponentInterface.php:1-56` - Interface
- `src/Feature/ComposerManager/Tab/ScriptsTab.php:54,85-86,162-167` - Usage (uses getCurrentFrame - unchanged)
- `tests/Unit/Component/Display/SpinnerTest.php` - 61 unit tests
- `tests/E2E/Scenario/SpinnerWorkflowScenarioTest.php` - 24 E2E tests

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
