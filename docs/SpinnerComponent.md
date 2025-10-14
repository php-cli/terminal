# Spinner Component Documentation

## Overview

The `Spinner` component provides animated spinners for indicating loading or processing states in terminal UIs. It offers multiple visual styles and is designed to be lightweight and easy to integrate.

## Features

- **Multiple Styles** - 8 different spinner animations to choose from
- **Configurable Speed** - Adjustable update interval
- **Simple API** - Start, stop, reset, and update methods
- **Standalone** - No rendering dependencies, just returns characters
- **Lightweight** - Minimal memory footprint

## Available Styles

### STYLE_BRAILLE (default)
```
⠋ ⠙ ⠹ ⠸ ⠼ ⠴ ⠦ ⠧ ⠇ ⠏
```
Smooth braille dots animation. Recommended for most use cases.

### STYLE_DOTS
```
⣾ ⣽ ⣻ ⢿ ⡿ ⣟ ⣯ ⣷
```
Circular dots animation with more density.

### STYLE_LINE
```
- \ | /
```
Classic ASCII line spinner. Best for minimal terminals.

### STYLE_ARROW
```
← ↖ ↑ ↗ → ↘ ↓ ↙
```
Rotating arrow animation.

### STYLE_DOTS_BOUNCE
```
⠁ ⠂ ⠄ ⡀ ⢀ ⠠ ⠐ ⠈
```
Bouncing dots pattern.

### STYLE_CIRCLE
```
◐ ◓ ◑ ◒
```
Rotating circle quarters.

### STYLE_SQUARE
```
◰ ◳ ◲ ◱
```
Rotating square corners.

### STYLE_CLOCK
```
🕐 🕑 🕒 🕓 🕔 🕕 🕖 🕗 🕘 🕙 🕚 🕛
```
Clock face animation (emoji, may not render in all terminals).

---

## Quick Start

### Basic Usage

```php
use Butschster\Commander\UI\Component\Display\Spinner;

// Create spinner
$spinner = new Spinner(Spinner::STYLE_BRAILLE, 0.1);

// Start animation
$spinner->start();

// In your update loop (called every frame)
public function update(): void
{
    $spinner->update();
    
    // Get current frame
    $frame = $spinner->getCurrentFrame();
    
    // Use in UI
    $this->statusBar->setText("$frame Loading...");
}

// Stop when done
$spinner->stop();
```

### Factory Methods

```php
// Create with defaults
$spinner = Spinner::create();

// Create and start in one call
$spinner = Spinner::createAndStart(Spinner::STYLE_DOTS, 0.15);
```

---

## API Reference

### Constructor

```php
public function __construct(
    string $style = Spinner::STYLE_BRAILLE,
    float $interval = 0.1
)
```

**Parameters:**
- `$style` - Spinner style (use `Spinner::STYLE_*` constants)
- `$interval` - Update interval in seconds (default: 0.1 = 100ms)

### Core Methods

#### `start(): void`
Start the spinner animation.

```php
$spinner->start();
```

#### `stop(): void`
Stop the spinner animation.

```php
$spinner->stop();
```

#### `reset(): void`
Reset spinner to first frame.

```php
$spinner->reset();
```

#### `update(): void`
Update spinner state. Call this in your main update loop.

```php
// In your component's update() method
public function update(): void
{
    if ($this->isLoading) {
        $this->spinner->update();
    }
}
```

#### `isRunning(): bool`
Check if spinner is currently running.

```php
if ($spinner->isRunning()) {
    // Show spinner
}
```

### Frame Access

#### `getCurrentFrame(): string`
Get the current frame character.

```php
$frame = $spinner->getCurrentFrame(); // "⠋"
```

#### `render(string $prefix = '', string $suffix = ''): string`
Get current frame with optional prefix/suffix.

```php
$text = $spinner->render('', ' Loading...'); // "⠋ Loading..."
$text = $spinner->render('[', ']');          // "[⠋]"
```

#### `getFrame(int $index): string`
Get frame at specific index (wraps around).

```php
$firstFrame = $spinner->getFrame(0);
$secondFrame = $spinner->getFrame(1);
```

#### `getFrameCount(): int`
Get total number of frames in animation.

```php
$count = $spinner->getFrameCount(); // 10 for STYLE_BRAILLE
```

### Configuration

#### `setInterval(float $interval): void`
Set update interval in seconds.

```php
$spinner->setInterval(0.05); // Update every 50ms (faster)
```

#### `getInterval(): float`
Get current update interval.

```php
$interval = $spinner->getInterval(); // 0.1
```

---

## Usage Patterns

### Pattern 1: Status Messages

```php
class MyTab extends AbstractTab
{
    private Spinner $spinner;
    private bool $isLoading = false;
    private TextDisplay $display;
    
    public function __construct()
    {
        $this->spinner = Spinner::createAndStart();
        $this->display = new TextDisplay();
    }
    
    public function update(): void
    {
        if ($this->isLoading) {
            $this->spinner->update();
            $frame = $this->spinner->getCurrentFrame();
            $this->display->setText("$frame Loading data...");
        }
    }
    
    private function startLoading(): void
    {
        $this->isLoading = true;
        $this->spinner->start();
    }
    
    private function stopLoading(): void
    {
        $this->isLoading = false;
        $this->spinner->stop();
        $this->display->setText("✅ Data loaded!");
    }
}
```

### Pattern 2: Alert Integration

```php
class ScriptsTab extends AbstractTab
{
    private Spinner $spinner;
    private ?Alert $statusAlert = null;
    
    public function __construct()
    {
        $this->spinner = new Spinner(Spinner::STYLE_BRAILLE, 0.1);
    }
    
    public function update(): void
    {
        if ($this->isExecuting) {
            $this->spinner->update();
            $frame = $this->spinner->getCurrentFrame();
            $this->statusAlert = Alert::info("$frame EXECUTING...");
        }
    }
    
    private function startExecution(): void
    {
        $this->spinner->start();
        // ... start process
    }
    
    private function finishExecution(bool $success): void
    {
        $this->spinner->stop();
        
        if ($success) {
            $this->statusAlert = Alert::success('SUCCESS');
        } else {
            $this->statusAlert = Alert::error('FAILED');
        }
    }
}
```

### Pattern 3: Panel Title

```php
public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
{
    // Update panel title with spinner
    if ($this->isProcessing) {
        $frame = $this->spinner->getCurrentFrame();
        $this->panel->setTitle("$frame Processing: {$this->taskName}");
    }
    
    $this->panel->render($renderer, $x, $y, $width, $height);
}
```

### Pattern 4: Multiple Spinners

```php
class ComplexScreen implements ScreenInterface
{
    private Spinner $uploadSpinner;
    private Spinner $downloadSpinner;
    
    public function __construct()
    {
        // Different styles for different operations
        $this->uploadSpinner = new Spinner(Spinner::STYLE_ARROW);
        $this->downloadSpinner = new Spinner(Spinner::STYLE_CIRCLE);
    }
    
    public function update(): void
    {
        if ($this->isUploading) {
            $this->uploadSpinner->update();
            $frame = $this->uploadSpinner->getCurrentFrame();
            $this->uploadLabel->setText("$frame Uploading...");
        }
        
        if ($this->isDownloading) {
            $this->downloadSpinner->update();
            $frame = $this->downloadSpinner->getCurrentFrame();
            $this->downloadLabel->setText("$frame Downloading...");
        }
    }
}
```

### Pattern 5: Conditional Styling

```php
class AdaptiveSpinner
{
    private Spinner $spinner;
    
    public function __construct()
    {
        // Detect terminal capabilities
        $style = $this->supportsUnicode() 
            ? Spinner::STYLE_BRAILLE 
            : Spinner::STYLE_LINE;
            
        $this->spinner = new Spinner($style);
    }
    
    private function supportsUnicode(): bool
    {
        $encoding = mb_internal_encoding();
        return stripos($encoding, 'utf') !== false;
    }
}
```

---

## Performance Considerations

### Update Frequency

The spinner only advances frames when `update()` is called AND the interval has elapsed:

```php
// At 30 FPS (33.3ms per frame) with 100ms interval
// Spinner updates every 3 frames (100ms / 33.3ms ≈ 3)

$spinner = new Spinner(Spinner::STYLE_BRAILLE, 0.1); // 100ms interval

// Main loop at 30 FPS
while (true) {
    $spinner->update(); // Only advances every ~3 calls
    usleep(33333); // 30 FPS
}
```

### Memory Usage

Each spinner instance uses minimal memory:
- Frame array: ~100 bytes (10 frames × ~10 bytes per UTF-8 char)
- State variables: ~50 bytes
- **Total: ~150 bytes per spinner**

### Optimization Tips

1. **Reuse spinners** - Create once, start/stop as needed
   ```php
   // ✅ Good: Reuse
   $this->spinner->start();
   // ... do work ...
   $this->spinner->stop();
   
   // ❌ Bad: Recreate
   $spinner = new Spinner();
   ```

2. **Adjust interval based on FPS**
   ```php
   // For 60 FPS (16.6ms/frame), use 100ms interval (6 frames)
   $spinner = new Spinner(Spinner::STYLE_BRAILLE, 0.1);
   
   // For 30 FPS (33.3ms/frame), use 150ms interval (4.5 frames)
   $spinner = new Spinner(Spinner::STYLE_BRAILLE, 0.15);
   ```

3. **Stop when not visible**
   ```php
   public function onDeactivate(): void
   {
       $this->spinner->stop(); // Stop when tab hidden
   }
   
   public function onActivate(): void
   {
       if ($this->isLoading) {
           $this->spinner->start(); // Resume if needed
       }
   }
   ```

---

## Style Selection Guide

| Use Case | Recommended Style | Reason |
|----------|------------------|---------|
| General loading | STYLE_BRAILLE | Smooth, professional, widely supported |
| Fast operations | STYLE_LINE | Simple, minimal, fast to render |
| Download/upload | STYLE_ARROW | Directional, suggests movement |
| Processing | STYLE_DOTS | Dense, suggests computation |
| Minimal terminal | STYLE_LINE | ASCII-only, no Unicode needed |
| Clock/timer | STYLE_CLOCK | Visual time representation |
| Compact spaces | STYLE_CIRCLE | Small footprint |

---

## Integration Examples

### With TextDisplay

```php
$spinner = Spinner::createAndStart();
$display = new TextDisplay();

public function update(): void
{
    $this->spinner->update();
    
    $lines = [
        "Status: " . $this->spinner->render('', ' Processing'),
        "Progress: 45%",
        "Time: 00:12:34"
    ];
    
    $display->setText(implode("\n", $lines));
}
```

### With TableComponent

```php
$spinner = new Spinner(Spinner::STYLE_DOTS);

new TableColumn(
    'status',
    'Status',
    15,
    TableColumn::ALIGN_CENTER,
    formatter: function($value, $row) {
        if ($row['isProcessing']) {
            return $this->spinner->getCurrentFrame() . ' Processing';
        }
        return $value;
    }
)
```

### With Modal

```php
$spinner = Spinner::createAndStart(Spinner::STYLE_BRAILLE);

$modal = new Modal(
    'Please Wait',
    $spinner->render('', ' Loading configuration...'),
    Modal::TYPE_INFO
);

// Update modal content every frame
public function update(): void
{
    $this->spinner->update();
    $content = $this->spinner->render('', ' Loading configuration...');
    $this->modal->setContent($content);
}
```

---

## Testing

### Unit Tests

```php
public function testSpinnerAdvancesFrames(): void
{
    $spinner = new Spinner(Spinner::STYLE_BRAILLE, 0.1);
    $spinner->start();
    
    $frame1 = $spinner->getCurrentFrame();
    
    // Simulate time passing
    usleep(150000); // 150ms
    $spinner->update();
    
    $frame2 = $spinner->getCurrentFrame();
    
    $this->assertNotEquals($frame1, $frame2);
}

public function testSpinnerWrapsAround(): void
{
    $spinner = new Spinner(Spinner::STYLE_LINE, 0.01);
    $spinner->start();
    
    $frameCount = $spinner->getFrameCount(); // 4 for LINE
    
    // Advance through all frames + 1
    for ($i = 0; $i <= $frameCount; $i++) {
        usleep(15000); // 15ms
        $spinner->update();
    }
    
    // Should wrap back to first frame
    $this->assertEquals($spinner->getFrame(0), $spinner->getCurrentFrame());
}
```

---

## Troubleshooting

### Issue: Spinner not animating

**Cause:** Forgetting to call `start()` or `update()`

**Solution:**
```php
// ✅ Correct
$spinner->start();
// In update loop:
$spinner->update();

// ❌ Wrong - forgot to start
$spinner->update(); // Won't animate
```

### Issue: Spinner too fast/slow

**Cause:** Interval doesn't match frame rate

**Solution:** Adjust interval based on FPS:
```php
// For 30 FPS (33ms/frame)
$spinner->setInterval(0.1); // Updates every 3 frames

// For 60 FPS (16ms/frame)
$spinner->setInterval(0.1); // Updates every 6 frames
```

### Issue: Unicode characters not showing

**Cause:** Terminal doesn't support UTF-8

**Solution:** Use ASCII-only style:
```php
$spinner = new Spinner(Spinner::STYLE_LINE); // ASCII: - \ | /
```

### Issue: Spinner shows same frame

**Cause:** Not calling `update()` in loop

**Solution:** Call `update()` every frame:
```php
public function update(): void
{
    parent::update();
    
    if ($this->isProcessing) {
        $this->spinner->update(); // Important!
    }
}
```

---

## Best Practices

### ✅ Do

- **Start/stop appropriately** - Start when operation begins, stop when done
- **Reuse instances** - Create once, reuse for multiple operations
- **Match interval to FPS** - Avoid updating every frame if not needed
- **Use semantic styles** - Choose style that matches operation type
- **Test in target terminal** - Verify Unicode support

### ❌ Don't

- **Don't create in render()** - Create in constructor/initialization
- **Don't forget to stop** - Always stop after operation completes
- **Don't mix with static text** - Update entire message, not just spinner
- **Don't rely on timing** - Use state checks, not elapsed time
- **Don't use clock emojis everywhere** - May not render in all terminals

---

## Summary

The Spinner component provides a simple, efficient way to add loading animations to terminal UIs:

✅ **8 visual styles** - From minimal ASCII to rich Unicode  
✅ **Configurable timing** - Adjust speed to match your FPS  
✅ **Simple API** - Start, stop, update, render  
✅ **Lightweight** - ~150 bytes per instance  
✅ **Framework agnostic** - Just returns strings, no rendering dependencies  
✅ **Well tested** - Proven in production use (ScriptsTab, etc.)  

Use spinners whenever you need to indicate:
- Background processing
- Network operations
- File I/O
- Long-running commands
- Asynchronous tasks

The spinner automatically handles frame timing and wrapping, so you just call `update()` in your main loop and display the current frame wherever needed.
