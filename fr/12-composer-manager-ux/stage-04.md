# Stage 4: Search Filter

## Overview

Add inline search/filter capability to package lists for improved usability in large projects.

## Prerequisites

- Stage 1 completed (standardized caching)
- Stages 2-3 optional but recommended

## Tasks

### 4.1 Create SearchFilter Component

**Create file**: `src/Feature/ComposerManager/Component/SearchFilter.php`

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
 * Inline search filter for package tables.
 *
 * Activation: Press / or Ctrl+F
 * Deactivation: Press Escape or Enter
 * Filter: As you type
 */
final class SearchFilter implements ComponentInterface
{
    private string $query = '';
    private bool $isActive = false;
    private bool $focused = false;
    private int $cursorPosition = 0;

    /** @var callable(string): void */
    private $onFilter;

    /**
     * @param callable(string): void $onFilter Called when query changes
     */
    public function __construct(callable $onFilter)
    {
        $this->onFilter = $onFilter;
    }

    /**
     * Activate search mode.
     */
    public function activate(): void
    {
        $this->isActive = true;
        $this->cursorPosition = \mb_strlen($this->query);
    }

    /**
     * Deactivate search mode.
     */
    public function deactivate(): void
    {
        $this->isActive = false;
    }

    /**
     * Clear the search query.
     */
    public function clear(): void
    {
        $this->query = '';
        $this->cursorPosition = 0;
        ($this->onFilter)('');
    }

    /**
     * Check if search is active (accepting input).
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Get current search query.
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * Check if there's an active filter.
     */
    public function hasFilter(): bool
    {
        return $this->query !== '';
    }

    #[\Override]
    public function render(Renderer $renderer, int $x, int $y, ?int $width, ?int $height): void
    {
        if ($width === null) {
            $width = 30;
        }

        $bgColor = $this->isActive
            ? ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_WHITE)
            : ColorScheme::$NORMAL_TEXT;

        // Render search box
        $label = $this->isActive ? '/' : ($this->query !== '' ? '/' : ' ');
        $displayQuery = $this->query;

        // Truncate if too long
        $maxQueryLen = $width - 4; // "/ " + space for cursor
        if (\mb_strlen($displayQuery) > $maxQueryLen) {
            $displayQuery = '...' . \mb_substr($displayQuery, -($maxQueryLen - 3));
        }

        $text = $label . ' ' . $displayQuery;

        // Pad to width
        $text = \str_pad($text, $width);

        $renderer->writeAt($x, $y, $text, $bgColor);

        // Draw cursor when active
        if ($this->isActive) {
            $cursorX = $x + 2 + \min($this->cursorPosition, $maxQueryLen);
            $cursorColor = ColorScheme::combine(ColorScheme::BG_WHITE, ColorScheme::FG_BLACK);
            $cursorChar = $this->cursorPosition < \mb_strlen($this->query)
                ? \mb_substr($this->query, $this->cursorPosition, 1)
                : ' ';
            $renderer->writeAt($cursorX, $y, $cursorChar, $cursorColor);
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        if (!$this->isActive) {
            return false;
        }

        $input = KeyInput::from($key);

        // Escape to cancel/clear
        if ($input->is(Key::ESCAPE)) {
            if ($this->query !== '') {
                $this->clear();
            } else {
                $this->deactivate();
            }
            return true;
        }

        // Enter to confirm and deactivate
        if ($input->is(Key::ENTER)) {
            $this->deactivate();
            return true;
        }

        // Backspace
        if ($input->is(Key::BACKSPACE)) {
            if ($this->cursorPosition > 0) {
                $this->query = \mb_substr($this->query, 0, $this->cursorPosition - 1)
                    . \mb_substr($this->query, $this->cursorPosition);
                $this->cursorPosition--;
                ($this->onFilter)($this->query);
            }
            return true;
        }

        // Delete
        if ($input->is(Key::DELETE)) {
            if ($this->cursorPosition < \mb_strlen($this->query)) {
                $this->query = \mb_substr($this->query, 0, $this->cursorPosition)
                    . \mb_substr($this->query, $this->cursorPosition + 1);
                ($this->onFilter)($this->query);
            }
            return true;
        }

        // Left arrow
        if ($input->is(Key::LEFT)) {
            if ($this->cursorPosition > 0) {
                $this->cursorPosition--;
            }
            return true;
        }

        // Right arrow
        if ($input->is(Key::RIGHT)) {
            if ($this->cursorPosition < \mb_strlen($this->query)) {
                $this->cursorPosition++;
            }
            return true;
        }

        // Home
        if ($input->is(Key::HOME)) {
            $this->cursorPosition = 0;
            return true;
        }

        // End
        if ($input->is(Key::END)) {
            $this->cursorPosition = \mb_strlen($this->query);
            return true;
        }

        // Ctrl+U to clear
        if ($input->isCtrl(Key::U)) {
            $this->clear();
            return true;
        }

        // Regular character input
        if (\mb_strlen($key) === 1 && \ord($key) >= 32) {
            $this->query = \mb_substr($this->query, 0, $this->cursorPosition)
                . $key
                . \mb_substr($this->query, $this->cursorPosition);
            $this->cursorPosition++;
            ($this->onFilter)($this->query);
            return true;
        }

        return true; // Consume all input when active
    }

    #[\Override]
    public function setFocused(bool $focused): void
    {
        $this->focused = $focused;
    }

    #[\Override]
    public function isFocused(): bool
    {
        return $this->focused || $this->isActive;
    }

    #[\Override]
    public function update(): void
    {
        // No animation
    }

    #[\Override]
    public function getMinSize(): array
    {
        return ['width' => 20, 'height' => 1];
    }
}
```

---

### 4.2 Add Search to InstalledPackagesTab

**File**: `src/Feature/ComposerManager/Tab/InstalledPackagesTab.php`

**Add properties**:
```php
private SearchFilter $searchFilter;
private array $allPackages = [];      // Unfiltered list
private array $filteredPackages = []; // Filtered list (shown in table)
```

**Update constructor**:
```php
public function __construct(
    private readonly ComposerService $composerService,
    private readonly ScreenManager $screenManager,
) {
    $this->loadingState = new LoadingState();
    $this->searchFilter = new SearchFilter($this->applyFilter(...));
    $this->initializeComponents();
}
```

**Add filter callback**:
```php
private function applyFilter(string $query): void
{
    if ($query === '') {
        $this->filteredPackages = $this->allPackages;
    } else {
        $query = \mb_strtolower($query);
        $this->filteredPackages = \array_filter(
            $this->allPackages,
            static fn(array $pkg) => \str_contains(\mb_strtolower($pkg['name']), $query)
                || \str_contains(\mb_strtolower($pkg['description'] ?? ''), $query)
        );
        $this->filteredPackages = \array_values($this->filteredPackages);
    }

    $this->table->setRows($this->filteredPackages);
    $this->updatePanelTitle();
}
```

**Update loadData()**:
```php
private function loadData(): void
{
    $this->allPackages = \array_map(static fn(PackageInfo $pkg)
        => [
            // ... existing mapping
        ], $this->composerService->getInstalledPackages());

    // Merge outdated info
    // ... existing code ...

    $this->filteredPackages = $this->allPackages;
    $this->table->setRows($this->filteredPackages);
    $this->updatePanelTitle();

    // ... rest of method
}

private function updatePanelTitle(): void
{
    $total = \count($this->allPackages);
    $filtered = \count($this->filteredPackages);
    $directCount = \count(\array_filter($this->allPackages, static fn($p) => $p['isDirect']));

    if ($this->searchFilter->hasFilter()) {
        $this->leftPanel->setTitle("Packages ({$filtered}/{$total} shown)");
    } else {
        $this->leftPanel->setTitle("Packages ({$total} total, {$directCount} direct)");
    }
}
```

**Update handleInput() for search activation**:
```php
#[\Override]
public function handleInput(string $key): bool
{
    // Handle search filter input first if active
    if ($this->searchFilter->isActive()) {
        return $this->searchFilter->handleInput($key);
    }

    $input = KeyInput::from($key);

    // Activate search with / or Ctrl+F
    if ($key === '/' || $input->isCtrl(Key::F)) {
        $this->searchFilter->activate();
        return true;
    }

    // Clear filter with Escape when filter is active but not in input mode
    if ($input->is(Key::ESCAPE) && $this->searchFilter->hasFilter()) {
        $this->searchFilter->clear();
        return true;
    }

    // ... rest of existing input handling
}
```

**Update render() to show search filter**:
```php
#[\Override]
public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
{
    $this->setBounds($x, $y, $width, $height);

    // Reserve space for search filter
    $searchHeight = 1;
    $contentHeight = $height - $searchHeight;

    // Render search filter at top
    $this->searchFilter->render($renderer, $x, $y, $width, 1);

    // Render layout below
    $this->layout->render($renderer, $x, $y + $searchHeight, $width, $contentHeight);

    // Loading overlay
    if ($this->loadingState->isLoading()) {
        $this->loadingState->render($renderer, $x, $y + $searchHeight, $width, $contentHeight);
    }
}
```

**Update shortcuts**:
```php
public function getShortcuts(): array
{
    if ($this->searchFilter->isActive()) {
        return [
            'Enter' => 'Confirm',
            'Esc' => 'Cancel',
            'Ctrl+U' => 'Clear',
        ];
    }

    $shortcuts = [
        '/' => 'Search',
        'Tab' => 'Switch Panel',
        'Enter' => 'Details',
        'Ctrl+R' => 'Refresh',
    ];

    if ($this->searchFilter->hasFilter()) {
        $shortcuts['Esc'] = 'Clear Filter';
    }

    return $shortcuts;
}
```

---

### 4.3 Add Search to OutdatedPackagesTab

Apply the same pattern as 4.2:

1. Add `SearchFilter` property
2. Initialize in constructor
3. Add `allPackages` and `filteredPackages` arrays
4. Add `applyFilter()` callback
5. Update `loadData()` to populate both arrays
6. Update `handleInput()` for / and Ctrl+F activation
7. Update `render()` to show filter
8. Update `getShortcuts()`

---

### 4.4 Implement Table Filtering Based on Query

Already implemented in 4.2's `applyFilter()` method. The filter:

- Checks package name (case-insensitive)
- Checks description (case-insensitive)
- Preserves array indices with `array_values()`

---

### 4.5 Add Visual Feedback for Active Filter

Already implemented in:

1. Panel title shows `(X/Y shown)` when filtered
2. Search bar shows `/` prefix with query
3. Shortcuts show `Esc: Clear Filter` when filter active
4. Different background color when search is active

---

### 4.6 Write Unit Tests for SearchFilter Component

**Create file**: `tests/Unit/Feature/ComposerManager/Component/SearchFilterTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\ComposerManager\Component;

use Butschster\Commander\Feature\ComposerManager\Component\SearchFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchFilter::class)]
final class SearchFilterTest extends TestCase
{
    #[Test]
    public function isActive_returns_false_initially(): void
    {
        $filter = new SearchFilter(fn() => null);

        self::assertFalse($filter->isActive());
    }

    #[Test]
    public function activate_enables_input_mode(): void
    {
        $filter = new SearchFilter(fn() => null);

        $filter->activate();

        self::assertTrue($filter->isActive());
    }

    #[Test]
    public function deactivate_disables_input_mode(): void
    {
        $filter = new SearchFilter(fn() => null);
        $filter->activate();

        $filter->deactivate();

        self::assertFalse($filter->isActive());
    }

    #[Test]
    public function typing_updates_query(): void
    {
        $receivedQuery = '';
        $filter = new SearchFilter(function (string $query) use (&$receivedQuery): void {
            $receivedQuery = $query;
        });

        $filter->activate();
        $filter->handleInput('s');
        $filter->handleInput('y');
        $filter->handleInput('m');

        self::assertSame('sym', $filter->getQuery());
        self::assertSame('sym', $receivedQuery);
    }

    #[Test]
    public function backspace_removes_character(): void
    {
        $filter = new SearchFilter(fn() => null);
        $filter->activate();
        $filter->handleInput('a');
        $filter->handleInput('b');
        $filter->handleInput('c');

        $filter->handleInput("\x7f"); // Backspace

        self::assertSame('ab', $filter->getQuery());
    }

    #[Test]
    public function escape_clears_query_first(): void
    {
        $filter = new SearchFilter(fn() => null);
        $filter->activate();
        $filter->handleInput('t');
        $filter->handleInput('e');

        $filter->handleInput("\e"); // Escape

        self::assertSame('', $filter->getQuery());
        self::assertTrue($filter->isActive()); // Still active
    }

    #[Test]
    public function escape_on_empty_deactivates(): void
    {
        $filter = new SearchFilter(fn() => null);
        $filter->activate();

        $filter->handleInput("\e"); // Escape

        self::assertFalse($filter->isActive());
    }

    #[Test]
    public function enter_deactivates(): void
    {
        $filter = new SearchFilter(fn() => null);
        $filter->activate();
        $filter->handleInput('x');

        $filter->handleInput("\r"); // Enter

        self::assertFalse($filter->isActive());
        self::assertSame('x', $filter->getQuery()); // Query preserved
    }

    #[Test]
    public function clear_resets_query_and_calls_callback(): void
    {
        $callbackCalled = false;
        $filter = new SearchFilter(function (string $query) use (&$callbackCalled): void {
            if ($query === '') {
                $callbackCalled = true;
            }
        });

        $filter->activate();
        $filter->handleInput('t');
        $filter->clear();

        self::assertSame('', $filter->getQuery());
        self::assertTrue($callbackCalled);
    }

    #[Test]
    public function hasFilter_returns_true_when_query_not_empty(): void
    {
        $filter = new SearchFilter(fn() => null);
        $filter->activate();
        $filter->handleInput('x');

        self::assertTrue($filter->hasFilter());
    }

    #[Test]
    public function hasFilter_returns_false_when_query_empty(): void
    {
        $filter = new SearchFilter(fn() => null);

        self::assertFalse($filter->hasFilter());
    }

    #[Test]
    public function callback_fires_on_each_keystroke(): void
    {
        $callCount = 0;
        $filter = new SearchFilter(function () use (&$callCount): void {
            $callCount++;
        });

        $filter->activate();
        $filter->handleInput('a');
        $filter->handleInput('b');
        $filter->handleInput('c');

        self::assertSame(3, $callCount);
    }
}
```

---

## Verification Steps

1. **Run tests**:
   ```bash
   composer test
   vendor/bin/phpunit --filter=SearchFilterTest
   ```

2. **Manual verification**:
   - Open Composer Manager
   - Go to Installed tab
   - Press `/` to activate search
   - Type `symfony` - list should filter
   - Press Enter to confirm, Escape to clear
   - Verify panel title shows `(X/Y shown)`
   - Press Ctrl+F - should also activate search
   - Go to Outdated tab, verify search works there too

3. **Edge cases**:
   - Search with no results
   - Search then refresh (Ctrl+R)
   - Search then switch tabs

---

## Acceptance Criteria

- [ ] SearchFilter component created
- [ ] `/` and `Ctrl+F` activate search in Installed tab
- [ ] `/` and `Ctrl+F` activate search in Outdated tab
- [ ] Typing filters package list in real-time
- [ ] Escape clears filter (or deactivates if empty)
- [ ] Enter confirms and exits search mode
- [ ] Panel title shows filter status
- [ ] Shortcuts update to show search mode
- [ ] Unit tests pass
