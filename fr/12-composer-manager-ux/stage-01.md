# Stage 1: Quick Fixes

## Overview

Quick, low-risk fixes that improve UX immediately without major refactoring.

## Prerequisites

- None (first stage)

## Tasks

### 1.1 Fix Misleading "Enter to Update" Shortcut

**File**: `src/Feature/ComposerManager/Tab/OutdatedPackagesTab.php`

**Current code (line 54-58)**:
```php
public function getShortcuts(): array
{
    return [
        'Tab' => 'Switch Panel',
        'Enter' => 'Update',      // ← Misleading
        'Ctrl+R' => 'Refresh',
    ];
}
```

**Option A**: Change label to match current behavior
```php
public function getShortcuts(): array
{
    return [
        'Tab' => 'Switch Panel',
        'Enter' => 'Details',     // ← Accurate
        'Ctrl+R' => 'Refresh',
    ];
}
```

**Option B**: Keep label, add TODO note (if implementing Stage 3)
```php
public function getShortcuts(): array
{
    return [
        'Tab' => 'Switch Panel',
        'Enter' => 'Update',      // Implemented in Stage 3
        'Ctrl+R' => 'Refresh',
    ];
}
```

**Recommendation**: Use Option A for now, change to 'Update' when Stage 3 is complete.

---

### 1.2 Extract ComposerBinaryLocator Utility

**Problem**: Duplicate code in two files

**File 1**: `src/Feature/ComposerManager/Service/ComposerService.php:683-711`
**File 2**: `src/Feature/ComposerManager/Tab/ScriptsTab.php:548-581`

**Create new file**: `src/Feature/ComposerManager/Service/ComposerBinaryLocator.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\ComposerManager\Service;

/**
 * Utility to locate the Composer binary on the system.
 */
final class ComposerBinaryLocator
{
    /**
     * Common locations to check for Composer binary.
     *
     * @var array<string>
     */
    private const CANDIDATES = [
        'composer',           // In PATH
        'composer.phar',      // Local phar
        '/usr/local/bin/composer',
        '/usr/bin/composer',
    ];

    private static ?string $cachedBinary = null;

    /**
     * Find the Composer binary.
     *
     * @return string|null Path to Composer binary, or null if not found
     */
    public static function find(): ?string
    {
        if (self::$cachedBinary !== null) {
            return self::$cachedBinary;
        }

        $candidates = self::CANDIDATES;

        // Add home directory location
        $home = $_SERVER['HOME'] ?? getenv('HOME');
        if ($home !== false && $home !== '') {
            $candidates[] = $home . '/.composer/composer.phar';
        }

        foreach ($candidates as $candidate) {
            if (self::isExecutable($candidate)) {
                self::$cachedBinary = $candidate;
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Clear the cached binary path.
     * Useful for testing or when Composer is installed during session.
     */
    public static function clearCache(): void
    {
        self::$cachedBinary = null;
    }

    /**
     * Check if a file is a valid Composer executable.
     */
    private static function isExecutable(string $file): bool
    {
        $output = @shell_exec(escapeshellarg($file) . ' --version 2>&1');
        return $output !== null && stripos($output, 'composer') !== false;
    }
}
```

**Update ComposerService.php**:

Replace lines 683-711 with:
```php
private function findComposerBinary(): ?string
{
    return ComposerBinaryLocator::find();
}
```

**Update ScriptsTab.php**:

Replace lines 548-581 with:
```php
private function findComposerBinary(): ?string
{
    return ComposerBinaryLocator::find();
}
```

---

### 1.3 Standardize Lazy Loading in InstalledPackagesTab

**File**: `src/Feature/ComposerManager/Tab/InstalledPackagesTab.php`

**Current code (line 105-110)**:
```php
#[\Override]
protected function onTabActivated(): void
{
    $this->loadData();    // ← Always reloads
    $this->updateFocus();
}
```

**Updated code** (match OutdatedPackagesTab pattern):
```php
private bool $dataLoaded = false;

#[\Override]
protected function onTabActivated(): void
{
    if (!$this->dataLoaded) {
        $this->loadData();
        $this->dataLoaded = true;
    }
    $this->updateFocus();
}
```

**Also update handleInput()** to reset flag on refresh:
```php
// In handleInput() method, after Ctrl+R block (around line 84-88):
if ($input->isCtrl(Key::R)) {
    $this->composerService->clearCache();
    $this->dataLoaded = false;  // ← Add this line
    $this->loadData();
    $this->dataLoaded = true;   // ← Add this line
    return true;
}
```

---

### 1.4 Add Package Count to Installed Tab Panel Title

**File**: `src/Feature/ComposerManager/Tab/InstalledPackagesTab.php`

**Add to end of loadData() method** (after line 275):
```php
// Update panel title with count
$count = \count($this->packages);
$directCount = \count(\array_filter($this->packages, static fn($p) => $p['isDirect']));
$this->leftPanel->setTitle("Packages ($count total, $directCount direct)");
```

---

### 1.5 Write Unit Tests for ComposerBinaryLocator

**Create file**: `tests/Unit/Feature/ComposerManager/Service/ComposerBinaryLocatorTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\ComposerManager\Service;

use Butschster\Commander\Feature\ComposerManager\Service\ComposerBinaryLocator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComposerBinaryLocator::class)]
final class ComposerBinaryLocatorTest extends TestCase
{
    protected function setUp(): void
    {
        ComposerBinaryLocator::clearCache();
    }

    protected function tearDown(): void
    {
        ComposerBinaryLocator::clearCache();
    }

    #[Test]
    public function find_returns_string_when_composer_installed(): void
    {
        $binary = ComposerBinaryLocator::find();

        // On CI and most dev environments, Composer should be available
        if ($binary !== null) {
            self::assertIsString($binary);
            self::assertNotEmpty($binary);
        } else {
            self::markTestSkipped('Composer not installed on this system');
        }
    }

    #[Test]
    public function find_caches_result(): void
    {
        $first = ComposerBinaryLocator::find();
        $second = ComposerBinaryLocator::find();

        self::assertSame($first, $second);
    }

    #[Test]
    public function clearCache_resets_cached_value(): void
    {
        $first = ComposerBinaryLocator::find();
        ComposerBinaryLocator::clearCache();
        $second = ComposerBinaryLocator::find();

        // Both should return same value (assuming no system changes)
        self::assertSame($first, $second);
    }
}
```

---

## Verification Steps

1. **Run existing tests**:
   ```bash
   composer test
   ```

2. **Run new tests**:
   ```bash
   vendor/bin/phpunit --filter=ComposerBinaryLocatorTest
   ```

3. **Manual verification**:
   - Open Composer Manager
   - Switch to Outdated tab
   - Verify shortcut bar shows "Enter: Details" (not "Update")
   - Switch to Installed tab, verify it doesn't reload each time
   - Press Ctrl+R, verify data refreshes

4. **Code style**:
   ```bash
   composer cs-fix
   composer psalm
   ```

---

## Acceptance Criteria

- [ ] Shortcut label accurately reflects Enter key behavior
- [ ] No duplicate `findComposerBinary()` code
- [ ] InstalledPackagesTab uses lazy loading with flag
- [ ] Package count shows in Installed tab title
- [ ] New ComposerBinaryLocator has unit tests
- [ ] All existing tests pass
- [ ] Code style checks pass
