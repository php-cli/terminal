# Stage 2: Update Existing Usages and Add Tests

## Overview

Update all existing Spinner usages to use the new API and create comprehensive tests for the refactored component. This
ensures backward compatibility and documents the new capabilities.

## Files

MODIFY:

- `src/Feature/ComposerManager/Tab/ScriptsTab.php` - Update Spinner usage pattern

CREATE:

- `tests/Unit/Component/Display/SpinnerTest.php` - Unit tests for Spinner component

## Code References

- `src/Feature/ComposerManager/Tab/ScriptsTab.php:54,85-86,162-167` - Current Spinner usage
- `src/UI/Component/Display/Spinner.php` - Refactored component (from Stage 1)
- `tests/TerminalTestCase.php:1-490` - Test base class with assertions

## Implementation Details

### Part A: Update ScriptsTab Usage

#### Current Usage Pattern (lines 54, 85-86, 162-167)

```php
// Line 54: Creation
private readonly Spinner $spinner;
$this->spinner = new Spinner(Spinner::STYLE_BRAILLE, 0.1);

// Lines 85-86: Getting frame for string interpolation
$spinnerFrame = $this->spinner->getCurrentFrame();
$this->rightPanel->setTitle("$spinnerFrame Running: {$this->selectedScript}");

// Lines 162-167: Update and get frame
$this->spinner->update();
$spinnerFrame = $this->spinner->getCurrentFrame();
$this->showAlert(Alert::info("$spinnerFrame EXECUTING..."));
```

#### Updated Usage Pattern

The current usage in ScriptsTab is actually fine since it uses `getCurrentFrame()` directly for string interpolation. No
changes required to ScriptsTab because:

1. `getCurrentFrame()` is preserved unchanged
2. `update()` signature is preserved (just adds parent::update() call)
3. `start()` and `stop()` are preserved unchanged

However, if future code wants to use Spinner in a layout:

```php
// New capability: Use in layouts
$layout = new StackLayout(Direction::HORIZONTAL);
$layout->addChild($spinner, size: 3);
$layout->addChild(new TextComponent('Loading...'));

// Or with configured prefix/suffix
$spinner->setPrefix('[ ')->setSuffix(' ] Loading...');
$spinner->render($renderer, $x, $y, $width, $height);
```

### Part B: Create SpinnerTest

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Component\Display;

use Butschster\Commander\UI\Component\Display\Spinner;
use Tests\TerminalTestCase;

final class SpinnerTest extends TerminalTestCase
{
    // === State Tests ===

    public function test_starts_stopped_by_default(): void
    {
        $spinner = new Spinner();

        $this->assertFalse($spinner->isRunning());
    }

    public function test_start_enables_running_state(): void
    {
        $spinner = new Spinner();

        $spinner->start();

        $this->assertTrue($spinner->isRunning());
    }

    public function test_stop_disables_running_state(): void
    {
        $spinner = Spinner::createAndStart();

        $spinner->stop();

        $this->assertFalse($spinner->isRunning());
    }

    public function test_reset_returns_to_first_frame(): void
    {
        $spinner = Spinner::createAndStart();

        // Advance a few frames
        for ($i = 0; $i < 5; $i++) {
            $spinner->update();
            \usleep(110000); // 110ms
        }

        $firstFrame = $spinner->getFrame(0);
        $spinner->reset();

        $this->assertSame($firstFrame, $spinner->getCurrentFrame());
    }

    // === Frame Tests ===

    public function test_get_current_frame_returns_first_frame_initially(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE);

        $this->assertSame('-', $spinner->getCurrentFrame());
    }

    public function test_get_frame_at_index(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE);

        $this->assertSame('-', $spinner->getFrame(0));
        $this->assertSame('\\', $spinner->getFrame(1));
        $this->assertSame('|', $spinner->getFrame(2));
        $this->assertSame('/', $spinner->getFrame(3));
    }

    public function test_get_frame_count(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE);

        $this->assertSame(4, $spinner->getFrameCount());
    }

    public function test_frame_index_wraps_around(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE); // 4 frames

        $this->assertSame($spinner->getFrame(0), $spinner->getFrame(4));
        $this->assertSame($spinner->getFrame(1), $spinner->getFrame(5));
    }

    // === Animation Tests ===

    public function test_update_advances_frame_after_interval(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE, 0.05); // 50ms interval
        $spinner->start();

        $firstFrame = $spinner->getCurrentFrame();

        // Wait for interval to pass
        \usleep(60000); // 60ms
        $spinner->update();

        $this->assertNotSame($firstFrame, $spinner->getCurrentFrame());
    }

    public function test_update_does_not_advance_when_stopped(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE, 0.01);
        // Don't start

        $firstFrame = $spinner->getCurrentFrame();

        \usleep(20000); // 20ms
        $spinner->update();

        $this->assertSame($firstFrame, $spinner->getCurrentFrame());
    }

    public function test_update_does_not_advance_before_interval(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE, 1.0); // 1 second interval
        $spinner->start();

        $firstFrame = $spinner->getCurrentFrame();

        // Immediate update (well before interval)
        $spinner->update();

        $this->assertSame($firstFrame, $spinner->getCurrentFrame());
    }

    // === Interval Tests ===

    public function test_set_and_get_interval(): void
    {
        $spinner = new Spinner();

        $spinner->setInterval(0.5);

        $this->assertSame(0.5, $spinner->getInterval());
    }

    // === Style Tests ===

    public function test_braille_style_frames(): void
    {
        $spinner = new Spinner(Spinner::STYLE_BRAILLE);

        $this->assertSame(10, $spinner->getFrameCount());
        $this->assertSame('⠋', $spinner->getFrame(0));
    }

    public function test_dots_style_frames(): void
    {
        $spinner = new Spinner(Spinner::STYLE_DOTS);

        $this->assertSame(8, $spinner->getFrameCount());
        $this->assertSame('⣾', $spinner->getFrame(0));
    }

    public function test_arrow_style_frames(): void
    {
        $spinner = new Spinner(Spinner::STYLE_ARROW);

        $this->assertSame(8, $spinner->getFrameCount());
        $this->assertSame('←', $spinner->getFrame(0));
    }

    public function test_circle_style_frames(): void
    {
        $spinner = new Spinner(Spinner::STYLE_CIRCLE);

        $this->assertSame(4, $spinner->getFrameCount());
        $this->assertSame('◐', $spinner->getFrame(0));
    }

    public function test_invalid_style_defaults_to_braille(): void
    {
        $spinner = new Spinner('invalid_style');

        $this->assertSame(10, $spinner->getFrameCount()); // Braille has 10 frames
    }

    // === Factory Tests ===

    public function test_create_returns_stopped_spinner(): void
    {
        $spinner = Spinner::create(Spinner::STYLE_LINE);

        $this->assertFalse($spinner->isRunning());
    }

    public function test_create_and_start_returns_running_spinner(): void
    {
        $spinner = Spinner::createAndStart(Spinner::STYLE_LINE);

        $this->assertTrue($spinner->isRunning());
    }

    // === ComponentInterface Tests ===

    public function test_renders_at_position(): void
    {
        $this->terminal()->setSize(80, 24);

        $spinner = new Spinner(Spinner::STYLE_LINE);

        $this->runApp($this->createSpinnerScreen($spinner));

        // Line style starts with '-'
        $this->assertScreenContains('-');
    }

    public function test_renders_with_prefix_and_suffix(): void
    {
        $this->terminal()->setSize(80, 24);

        $spinner = new Spinner(Spinner::STYLE_LINE);
        $spinner->setPrefix('[ ')->setSuffix(' ]');

        $this->runApp($this->createSpinnerScreen($spinner));

        $this->assertScreenContains('[ - ]');
    }

    public function test_handle_input_returns_false(): void
    {
        $spinner = new Spinner();

        $this->assertFalse($spinner->handleInput('ENTER'));
        $this->assertFalse($spinner->handleInput('UP'));
        $this->assertFalse($spinner->handleInput('a'));
    }

    public function test_get_min_size_returns_frame_width(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE);

        $size = $spinner->getMinSize();

        $this->assertSame(1, $size['width']); // '-' is 1 char
        $this->assertSame(1, $size['height']);
    }

    public function test_get_min_size_includes_prefix_suffix(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE);
        $spinner->setPrefix('[ ')->setSuffix(' ]');

        $size = $spinner->getMinSize();

        // '[ ' (2) + '-' (1) + ' ]' (2) = 5
        $this->assertSame(5, $size['width']);
        $this->assertSame(1, $size['height']);
    }

    // === Formatted Text Tests (Legacy API) ===

    public function test_get_formatted_text_returns_frame_with_affixes(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE);

        $text = $spinner->getFormattedText('[ ', ' ]');

        $this->assertSame('[ - ]', $text);
    }

    public function test_get_formatted_text_with_empty_affixes(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE);

        $text = $spinner->getFormattedText();

        $this->assertSame('-', $text);
    }

    // === Helper Methods ===

    private function createSpinnerScreen(Spinner $spinner): \Butschster\Commander\UI\Screen\ScreenInterface
    {
        return new class($spinner) implements \Butschster\Commander\UI\Screen\ScreenInterface {
            public function __construct(private Spinner $spinner) {}

            public function render(
                \Butschster\Commander\Infrastructure\Terminal\Renderer $renderer,
                int $x = 0,
                int $y = 0,
                ?int $width = null,
                ?int $height = null,
            ): void {
                $this->spinner->render($renderer, $x + 5, $y + 5, 20, 1);
            }

            public function handleInput(string $key): bool { return false; }
            public function onActivate(): void {}
            public function onDeactivate(): void {}
            public function update(): void { $this->spinner->update(); }
            public function getTitle(): string { return 'Spinner Test'; }
        };
    }
}
```

### Test Categories Summary

| Category           | Test Count | Description                                 |
|--------------------|------------|---------------------------------------------|
| State              | 4          | start/stop/reset/isRunning                  |
| Frame              | 4          | getCurrentFrame/getFrame/getFrameCount/wrap |
| Animation          | 3          | update timing behavior                      |
| Interval           | 1          | setInterval/getInterval                     |
| Style              | 5          | Different spinner styles                    |
| Factory            | 2          | create/createAndStart                       |
| ComponentInterface | 5          | render/handleInput/getMinSize               |
| Legacy API         | 2          | getFormattedText                            |
| **Total**          | **26**     |                                             |

## Definition of Done

- [ ] ScriptsTab.php reviewed - no changes needed (uses getCurrentFrame directly)
- [ ] SpinnerTest.php created with 26+ test methods
- [ ] All tests pass with `vendor/bin/phpunit --filter=SpinnerTest`
- [ ] Tests cover all spinner styles (braille, dots, line, arrow, circle, square, clock)
- [ ] Tests cover animation timing behavior
- [ ] Tests cover ComponentInterface methods (render, handleInput, getMinSize)
- [ ] Tests cover legacy API (getFormattedText)
- [ ] No regressions in existing tests

## Dependencies

**Requires**: Stage 1 (Spinner refactoring)
**Enables**: Usage in layout containers (StackLayout, GridLayout, etc.)
