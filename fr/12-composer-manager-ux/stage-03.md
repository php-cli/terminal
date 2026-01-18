# Stage 3: Update Functionality

## Overview

Implement the actual package update functionality that the UI currently promises but doesn't deliver.

## Prerequisites

- Stage 1 completed (shortcut label fixed)
- Stage 2 completed (loading states available)

## Tasks

### 3.1 Create Confirmation Modal Component

**Create file**: `src/Feature/ComposerManager/Component/ConfirmationModal.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Component;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\ComponentInterface;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Modal dialog for confirming actions.
 */
final class ConfirmationModal implements ComponentInterface
{
    private bool $visible = false;
    private string $title = '';
    private string $message = '';
    private int $selectedButton = 0; // 0 = Yes, 1 = No

    /** @var callable|null */
    private $onConfirm = null;

    /** @var callable|null */
    private $onCancel = null;

    public function show(string $title, string $message): void
    {
        $this->title = $title;
        $this->message = $message;
        $this->visible = true;
        $this->selectedButton = 1; // Default to No for safety
    }

    public function hide(): void
    {
        $this->visible = false;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function onConfirm(callable $callback): self
    {
        $this->onConfirm = $callback;
        return $this;
    }

    public function onCancel(callable $callback): self
    {
        $this->onCancel = $callback;
        return $this;
    }

    #[\Override]
    public function render(Renderer $renderer, int $x, int $y, ?int $width, ?int $height): void
    {
        if (!$this->visible || $width === null || $height === null) {
            return;
        }

        // Calculate modal dimensions
        $modalWidth = \min(60, $width - 10);
        $modalHeight = 7;
        $modalX = $x + (int) (($width - $modalWidth) / 2);
        $modalY = $y + (int) (($height - $modalHeight) / 2);

        // Draw overlay (dim background)
        $dimColor = ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_BRIGHT_BLACK);
        for ($row = $y; $row < $y + $height; $row++) {
            $renderer->writeAt($x, $row, \str_repeat(' ', $width), $dimColor);
        }

        // Draw modal box
        $borderColor = ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_WHITE);
        $contentColor = ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_WHITE);

        // Top border with title
        $titleText = " {$this->title} ";
        $borderLeft = \str_repeat('─', (int) (($modalWidth - 2 - \mb_strlen($titleText)) / 2));
        $borderRight = \str_repeat('─', $modalWidth - 2 - \mb_strlen($borderLeft) - \mb_strlen($titleText));
        $renderer->writeAt($modalX, $modalY, '┌' . $borderLeft . $titleText . $borderRight . '┐', $borderColor);

        // Content lines
        for ($i = 1; $i < $modalHeight - 1; $i++) {
            $renderer->writeAt($modalX, $modalY + $i, '│' . \str_repeat(' ', $modalWidth - 2) . '│', $contentColor);
        }

        // Message (centered)
        $msgX = $modalX + 1 + (int) (($modalWidth - 2 - \mb_strlen($this->message)) / 2);
        $renderer->writeAt($msgX, $modalY + 2, $this->message, $contentColor);

        // Buttons
        $yesLabel = $this->selectedButton === 0 ? '[ Yes ]' : '  Yes  ';
        $noLabel = $this->selectedButton === 1 ? '[ No ]' : '  No  ';

        $yesColor = $this->selectedButton === 0
            ? ColorScheme::combine(ColorScheme::BG_WHITE, ColorScheme::FG_BLACK, ColorScheme::BOLD)
            : $contentColor;
        $noColor = $this->selectedButton === 1
            ? ColorScheme::combine(ColorScheme::BG_WHITE, ColorScheme::FG_BLACK, ColorScheme::BOLD)
            : $contentColor;

        $buttonsWidth = \mb_strlen($yesLabel) + 4 + \mb_strlen($noLabel);
        $buttonsX = $modalX + 1 + (int) (($modalWidth - 2 - $buttonsWidth) / 2);

        $renderer->writeAt($buttonsX, $modalY + 4, $yesLabel, $yesColor);
        $renderer->writeAt($buttonsX + \mb_strlen($yesLabel) + 4, $modalY + 4, $noLabel, $noColor);

        // Bottom border
        $renderer->writeAt($modalX, $modalY + $modalHeight - 1, '└' . \str_repeat('─', $modalWidth - 2) . '┘', $borderColor);
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        if (!$this->visible) {
            return false;
        }

        $input = KeyInput::from($key);

        // Left/Right to switch buttons
        if ($input->is(Key::LEFT) || $input->is(Key::RIGHT) || $input->is(Key::TAB)) {
            $this->selectedButton = $this->selectedButton === 0 ? 1 : 0;
            return true;
        }

        // Enter to confirm selection
        if ($input->is(Key::ENTER)) {
            $this->hide();
            if ($this->selectedButton === 0 && $this->onConfirm !== null) {
                ($this->onConfirm)();
            } elseif ($this->selectedButton === 1 && $this->onCancel !== null) {
                ($this->onCancel)();
            }
            return true;
        }

        // Escape to cancel
        if ($input->is(Key::ESCAPE)) {
            $this->hide();
            if ($this->onCancel !== null) {
                ($this->onCancel)();
            }
            return true;
        }

        // Y key for yes
        if (\strtolower($key) === 'y') {
            $this->hide();
            if ($this->onConfirm !== null) {
                ($this->onConfirm)();
            }
            return true;
        }

        // N key for no
        if (\strtolower($key) === 'n') {
            $this->hide();
            if ($this->onCancel !== null) {
                ($this->onCancel)();
            }
            return true;
        }

        return true; // Consume all input when modal is visible
    }

    #[\Override]
    public function setFocused(bool $focused): void
    {
        // Modal is always focused when visible
    }

    #[\Override]
    public function isFocused(): bool
    {
        return $this->visible;
    }

    #[\Override]
    public function update(): void
    {
        // No animation needed
    }

    #[\Override]
    public function getMinSize(): array
    {
        return ['width' => 60, 'height' => 7];
    }
}
```

---

### 3.2 Wire Enter Key in OutdatedPackagesTab to Update Flow

**File**: `src/Feature/ComposerManager/Tab/OutdatedPackagesTab.php`

**Add properties** (around line 37):
```php
private ConfirmationModal $confirmModal;
private ?string $packageToUpdate = null;
private bool $isUpdating = false;
```

**Update constructor**:
```php
public function __construct(
    private readonly ComposerService $composerService,
) {
    $this->loadingState = new LoadingState();
    $this->confirmModal = new ConfirmationModal();
    $this->initializeComponents();
}
```

**Update the table onSelect callback** (replace lines 184-187):
```php
$table->onSelect(function (array $row, int $index): void {
    $this->promptUpdatePackage($row['name']);
});
```

**Add new method**:
```php
private function promptUpdatePackage(string $packageName): void
{
    $this->packageToUpdate = $packageName;

    $this->confirmModal
        ->onConfirm(function () use ($packageName): void {
            $this->performPackageUpdate($packageName);
        })
        ->onCancel(function (): void {
            $this->packageToUpdate = null;
        });

    $this->confirmModal->show(
        'Update Package',
        "Update {$packageName} to latest version?"
    );
}
```

**Update the shortcut back to 'Update'**:
```php
public function getShortcuts(): array
{
    return [
        'Tab' => 'Switch Panel',
        'Enter' => 'Update',  // Now accurate!
        'Ctrl+R' => 'Refresh',
    ];
}
```

---

### 3.3 Implement performPackageUpdate() with Real-Time Output

**File**: `src/Feature/ComposerManager/Tab/OutdatedPackagesTab.php`

**Add method**:
```php
private function performPackageUpdate(string $packageName): void
{
    $this->isUpdating = true;
    $this->packageToUpdate = $packageName;

    // Switch focus to right panel to show output
    $this->focusedPanelIndex = 1;
    $this->updateFocus();

    // Show initial status
    $this->detailsDisplay->setText("Updating {$packageName}...\n\n");
    $this->rightPanel->setTitle("Updating: {$packageName}");

    // Show alert
    $this->showUpdateAlert();

    try {
        $composerBinary = ComposerBinaryLocator::find();
        if ($composerBinary === null) {
            throw new \RuntimeException('Composer binary not found');
        }

        // Create process
        $this->runningProcess = new Process(
            [$composerBinary, 'update', $packageName, '--with-dependencies'],
            \getcwd(),
            null,
            null,
            null, // No timeout
        );

        // Start process
        $this->runningProcess->start();

        // Note: Output will be read in update() method for real-time display
    } catch (\Throwable $e) {
        $this->handleUpdateError($e);
    }
}

private function showUpdateAlert(): void
{
    // Reuse Alert component pattern from ScriptsTab
    $this->statusAlert = Alert::info('UPDATING...');
}

private function handleUpdateError(\Throwable $e): void
{
    $this->detailsDisplay->appendText("\n❌ ERROR\n" . \str_repeat('─', 50) . "\n");
    $this->detailsDisplay->appendText($e->getMessage() . "\n");
    $this->detailsDisplay->appendText("\nPress Enter to try again.");

    $this->statusAlert = Alert::error('FAILED');
    $this->isUpdating = false;
    $this->runningProcess = null;
    $this->packageToUpdate = null;
}
```

**Add update() method to handle process output** (similar to ScriptsTab):
```php
#[\Override]
public function update(): void
{
    parent::update();
    $this->loadingState->update();

    if ($this->isUpdating && $this->runningProcess !== null) {
        $this->updateProcessOutput();
    }

    // Auto-hide alert if expired
    if ($this->statusAlert !== null && $this->statusAlert->isExpired()) {
        $this->statusAlert = null;
    }
}

private function updateProcessOutput(): void
{
    if ($this->runningProcess === null) {
        return;
    }

    // Read incremental output
    $output = $this->runningProcess->getIncrementalOutput();
    $errorOutput = $this->runningProcess->getIncrementalErrorOutput();

    if ($output !== '') {
        $this->detailsDisplay->appendText($this->stripAnsiCodes($output));
    }

    if ($errorOutput !== '') {
        $this->detailsDisplay->appendText($this->stripAnsiCodes($errorOutput));
    }

    // Check if process finished
    if (!$this->runningProcess->isRunning()) {
        $this->handleUpdateCompletion();
    }
}

private function handleUpdateCompletion(): void
{
    if ($this->runningProcess === null) {
        return;
    }

    $exitCode = $this->runningProcess->getExitCode();

    $this->detailsDisplay->appendText("\n" . \str_repeat('─', 50) . "\n");

    if ($exitCode === 0) {
        $this->detailsDisplay->appendText("✅ Package updated successfully!\n");
        $this->statusAlert = Alert::success('UPDATED');

        // Invalidate caches so data refreshes
        $this->composerService->clearCache();
        $this->dataLoaded = false;
    } else {
        $this->detailsDisplay->appendText("❌ Update failed (exit code: {$exitCode})\n");
        $this->statusAlert = Alert::error('FAILED');
    }

    $this->detailsDisplay->appendText("\nPress Ctrl+R to refresh the list.");

    $this->isUpdating = false;
    $this->runningProcess = null;
    $this->packageToUpdate = null;
}

private function stripAnsiCodes(string $output): string
{
    $output = (string) \preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $output);
    $output = (string) \preg_replace('/\x1b\][^\x07]*\x07/', '', $output);
    $output = (string) \preg_replace('/\x1b[><=]/', '', $output);
    return $output;
}
```

---

### 3.4 Handle Update Errors Gracefully

Already handled in 3.3 with `handleUpdateError()` method.

**Additional error cases to handle**:

```php
// In handleInput(), add Ctrl+C cancellation during update
if ($this->isUpdating && $input->isCtrl(Key::C)) {
    $this->cancelUpdate();
    return true;
}

private function cancelUpdate(): void
{
    if ($this->runningProcess === null) {
        return;
    }

    try {
        $this->runningProcess->stop(3, SIGTERM);

        $this->detailsDisplay->appendText("\n" . \str_repeat('─', 50) . "\n");
        $this->detailsDisplay->appendText("⚠️ Update cancelled by user\n");
        $this->statusAlert = Alert::warning('CANCELLED');
    } catch (\Throwable $e) {
        $this->detailsDisplay->appendText("\n\n❌ Error cancelling: {$e->getMessage()}\n");
        $this->statusAlert = Alert::error('ERROR');
    } finally {
        $this->isUpdating = false;
        $this->runningProcess = null;
        $this->packageToUpdate = null;
    }
}
```

**Update shortcuts to show Ctrl+C during update**:
```php
public function getShortcuts(): array
{
    if ($this->isUpdating) {
        return [
            'Ctrl+C' => 'Cancel',
        ];
    }

    return [
        'Tab' => 'Switch Panel',
        'Enter' => 'Update',
        'Ctrl+R' => 'Refresh',
    ];
}
```

---

### 3.5 Invalidate Caches After Successful Update

Already handled in 3.3's `handleUpdateCompletion()`:

```php
// Invalidate caches so data refreshes
$this->composerService->clearCache();
$this->dataLoaded = false;
```

---

### 3.6 Write Integration Tests for Update Flow

**Create file**: `tests/Integration/Feature/ComposerManager/UpdatePackageFlowTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Feature\ComposerManager;

use Butschster\Commander\Feature\ComposerManager\Component\ConfirmationModal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfirmationModal::class)]
final class ConfirmationModalTest extends TestCase
{
    #[Test]
    public function show_makes_modal_visible(): void
    {
        $modal = new ConfirmationModal();

        $modal->show('Title', 'Message');

        self::assertTrue($modal->isVisible());
    }

    #[Test]
    public function hide_makes_modal_invisible(): void
    {
        $modal = new ConfirmationModal();
        $modal->show('Title', 'Message');

        $modal->hide();

        self::assertFalse($modal->isVisible());
    }

    #[Test]
    public function enter_on_yes_calls_onConfirm(): void
    {
        $modal = new ConfirmationModal();
        $confirmed = false;

        $modal->onConfirm(function () use (&$confirmed): void {
            $confirmed = true;
        });

        $modal->show('Title', 'Message');

        // Navigate to Yes (it defaults to No)
        $modal->handleInput("\e[D"); // Left arrow
        $modal->handleInput("\r");   // Enter

        self::assertTrue($confirmed);
        self::assertFalse($modal->isVisible());
    }

    #[Test]
    public function escape_calls_onCancel(): void
    {
        $modal = new ConfirmationModal();
        $cancelled = false;

        $modal->onCancel(function () use (&$cancelled): void {
            $cancelled = true;
        });

        $modal->show('Title', 'Message');
        $modal->handleInput("\e");   // Escape

        self::assertTrue($cancelled);
        self::assertFalse($modal->isVisible());
    }

    #[Test]
    public function y_key_confirms(): void
    {
        $modal = new ConfirmationModal();
        $confirmed = false;

        $modal->onConfirm(function () use (&$confirmed): void {
            $confirmed = true;
        });

        $modal->show('Title', 'Message');
        $modal->handleInput('y');

        self::assertTrue($confirmed);
    }

    #[Test]
    public function n_key_cancels(): void
    {
        $modal = new ConfirmationModal();
        $cancelled = false;

        $modal->onCancel(function () use (&$cancelled): void {
            $cancelled = true;
        });

        $modal->show('Title', 'Message');
        $modal->handleInput('n');

        self::assertTrue($cancelled);
    }
}
```

---

## Verification Steps

1. **Run tests**:
   ```bash
   composer test
   vendor/bin/phpunit --filter=ConfirmationModalTest
   ```

2. **Manual verification**:
   - Open Composer Manager
   - Switch to Outdated tab
   - Press Enter on an outdated package
   - Confirm modal appears with Yes/No buttons
   - Press Left/Right to switch between buttons
   - Press Y to confirm (should start update)
   - Observe real-time output
   - After completion, verify cache is cleared

3. **Test cancellation**:
   - Start an update
   - Press Ctrl+C during update
   - Verify process is cancelled

---

## Acceptance Criteria

- [ ] Confirmation modal appears before update
- [ ] Y/N keys and arrows work for confirmation
- [ ] Update process shows real-time output
- [ ] Ctrl+C cancels running update
- [ ] Cache invalidated after successful update
- [ ] Error messages displayed on failure
- [ ] Integration tests pass
