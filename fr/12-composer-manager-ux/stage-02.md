# Stage 2: Loading States

## Overview

Add visual feedback during slow operations to prevent users thinking the application froze.

## Prerequisites

- Stage 1 completed (ComposerBinaryLocator extracted)

## Tasks

### 2.1 Create LoadingState Component

**Create file**: `src/Feature/ComposerManager/Component/LoadingState.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Component;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Display\Spinner;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Full-panel loading state overlay with spinner and message.
 *
 * Use this to show feedback during slow operations like:
 * - composer outdated (3-10 seconds)
 * - composer audit (2-5 seconds)
 */
final class LoadingState
{
    private readonly Spinner $spinner;
    private ?string $message = null;
    private bool $isLoading = false;

    public function __construct()
    {
        $this->spinner = new Spinner(Spinner::STYLE_BRAILLE, 0.1);
    }

    /**
     * Start loading state with a message.
     */
    public function start(string $message): void
    {
        $this->message = $message;
        $this->isLoading = true;
        $this->spinner->start();
    }

    /**
     * Stop loading state.
     */
    public function stop(): void
    {
        $this->isLoading = false;
        $this->message = null;
        $this->spinner->stop();
    }

    /**
     * Check if currently loading.
     */
    public function isLoading(): bool
    {
        return $this->isLoading;
    }

    /**
     * Update spinner animation (call in update() loop).
     */
    public function update(): void
    {
        if ($this->isLoading) {
            $this->spinner->update();
        }
    }

    /**
     * Render the loading overlay centered in the given area.
     */
    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        if (!$this->isLoading || $this->message === null) {
            return;
        }

        // Calculate center position
        $spinnerFrame = $this->spinner->getCurrentFrame();
        $text = $spinnerFrame . ' ' . $this->message;
        $textWidth = \mb_strlen($text);

        $centerX = $x + (int) (($width - $textWidth) / 2);
        $centerY = $y + (int) ($height / 2);

        // Draw semi-transparent overlay effect (dim the area)
        $dimColor = ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_BRIGHT_BLACK);
        for ($row = $y; $row < $y + $height; $row++) {
            $renderer->writeAt($x, $row, \str_repeat(' ', $width), $dimColor);
        }

        // Draw loading message box
        $boxWidth = $textWidth + 4;
        $boxX = $x + (int) (($width - $boxWidth) / 2);
        $boxY = $centerY - 1;

        $boxColor = ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_WHITE, ColorScheme::BOLD);

        // Box top
        $renderer->writeAt($boxX, $boxY, '┌' . \str_repeat('─', $boxWidth - 2) . '┐', $boxColor);
        // Box middle with text
        $renderer->writeAt($boxX, $boxY + 1, '│ ' . $text . ' │', $boxColor);
        // Box bottom
        $renderer->writeAt($boxX, $boxY + 2, '└' . \str_repeat('─', $boxWidth - 2) . '┘', $boxColor);
    }
}
```

---

### 2.2 Add Loading State to OutdatedPackagesTab

**File**: `src/Feature/ComposerManager/Tab/OutdatedPackagesTab.php`

**Add property** (around line 37):
```php
private LoadingState $loadingState;
```

**Update constructor** (around line 41-43):
```php
public function __construct(
    private readonly ComposerService $composerService,
) {
    $this->loadingState = new LoadingState();
    $this->initializeComponents();
}
```

**Update render()** (around line 62-66):
```php
#[\Override]
public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
{
    $this->setBounds($x, $y, $width, $height);
    $this->layout->render($renderer, $x, $y, $width, $height);

    // Render loading overlay if active
    if ($this->loadingState->isLoading()) {
        $this->loadingState->render($renderer, $x, $y, $width, $height);
    }
}
```

**Update handleInput()** to block during loading (around line 69):
```php
#[\Override]
public function handleInput(string $key): bool
{
    // Block input during loading
    if ($this->loadingState->isLoading()) {
        return true;
    }

    $input = KeyInput::from($key);
    // ... rest of method
}
```

**Update loadData()** with loading state (around line 192):
```php
private function loadData(): void
{
    $this->loadingState->start('Checking for outdated packages...');

    try {
        $this->packages = \array_map(static fn(OutdatedPackageInfo $pkg)
            => [
                // ... existing mapping code
            ], $this->composerService->getOutdatedPackages());

        $this->table->setRows($this->packages);

        // Update panel title
        $count = \count($this->packages);
        $this->leftPanel->setTitle("Outdated Packages ($count)");

        // Show first package or empty state
        if (!empty($this->packages)) {
            $this->selectedPackageName = $this->packages[0]['name'];
            $this->showPackageDetails($this->packages[0]);
        } else {
            $this->detailsDisplay->setText("[OK] All packages are up to date!");
        }
    } finally {
        $this->loadingState->stop();
    }
}
```

**Add update() method**:
```php
#[\Override]
public function update(): void
{
    parent::update();
    $this->loadingState->update();
}
```

---

### 2.3 Add Loading State to SecurityAuditTab

**File**: `src/Feature/ComposerManager/Tab/SecurityAuditTab.php`

**Apply same pattern as 2.2**:

1. Add `LoadingState $loadingState` property
2. Initialize in constructor
3. Render overlay in render()
4. Block input during loading
5. Wrap loadData() with start/stop
6. Add update() method

**loadData() message**: `'Running security audit...'`

---

### 2.4 Add Loading State to Refresh Operations

**File**: `src/Feature/ComposerManager/Tab/InstalledPackagesTab.php`

The Installed tab loads data differently (synchronous Composer API call), but we should still show feedback during Ctrl+R refresh since it also fetches outdated data.

**Add property and initialize**:
```php
private LoadingState $loadingState;
private bool $dataLoaded = false;

public function __construct(
    private readonly ComposerService $composerService,
    private readonly ScreenManager $screenManager,
) {
    $this->loadingState = new LoadingState();
    $this->initializeComponents();
}
```

**Update handleInput() for Ctrl+R**:
```php
if ($input->isCtrl(Key::R)) {
    $this->loadingState->start('Refreshing packages...');
    try {
        $this->composerService->clearCache();
        $this->dataLoaded = false;
        $this->loadData();
        $this->dataLoaded = true;
    } finally {
        $this->loadingState->stop();
    }
    return true;
}
```

**Add render overlay and update() method** as in 2.2.

---

### 2.5 Write Unit Tests for LoadingState Component

**Create file**: `tests/Unit/Feature/ComposerManager/Component/LoadingStateTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\ComposerManager\Component;

use Butschster\Commander\Feature\ComposerManager\Component\LoadingState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoadingState::class)]
final class LoadingStateTest extends TestCase
{
    #[Test]
    public function isLoading_returns_false_initially(): void
    {
        $loadingState = new LoadingState();

        self::assertFalse($loadingState->isLoading());
    }

    #[Test]
    public function start_sets_loading_to_true(): void
    {
        $loadingState = new LoadingState();

        $loadingState->start('Loading...');

        self::assertTrue($loadingState->isLoading());
    }

    #[Test]
    public function stop_sets_loading_to_false(): void
    {
        $loadingState = new LoadingState();
        $loadingState->start('Loading...');

        $loadingState->stop();

        self::assertFalse($loadingState->isLoading());
    }

    #[Test]
    public function update_does_not_throw_when_not_loading(): void
    {
        $loadingState = new LoadingState();

        // Should not throw
        $loadingState->update();

        self::assertFalse($loadingState->isLoading());
    }

    #[Test]
    public function update_does_not_throw_when_loading(): void
    {
        $loadingState = new LoadingState();
        $loadingState->start('Test');

        // Should not throw
        $loadingState->update();

        self::assertTrue($loadingState->isLoading());
    }
}
```

---

## Verification Steps

1. **Run tests**:
   ```bash
   composer test
   vendor/bin/phpunit --filter=LoadingStateTest
   ```

2. **Manual verification**:
   - Open Composer Manager
   - Switch to Outdated tab - should see loading indicator
   - Switch to Security tab - should see loading indicator
   - Switch to Installed tab, press Ctrl+R - should see loading indicator
   - During loading, key presses should be blocked

3. **Code style**:
   ```bash
   composer cs-fix
   composer psalm
   ```

---

## Acceptance Criteria

- [ ] LoadingState component created with spinner
- [ ] OutdatedPackagesTab shows loading during data fetch
- [ ] SecurityAuditTab shows loading during audit
- [ ] InstalledPackagesTab shows loading during refresh
- [ ] Input is blocked during loading state
- [ ] Unit tests for LoadingState pass
- [ ] No visual glitches or flickering
