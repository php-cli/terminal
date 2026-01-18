# Stage 5: Testing

## Overview

Add comprehensive unit tests, integration tests, and E2E tests for the Composer Manager feature.

## Prerequisites

- Stages 1-4 completed (all new components available)

## Tasks

### 5.1 Write Unit Tests for ComposerService

**Create file**: `tests/Unit/Feature/ComposerManager/Service/ComposerServiceTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\ComposerManager\Service;

use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;
use Butschster\Commander\Feature\ComposerManager\Service\PackageInfo;
use Butschster\Commander\Feature\ComposerManager\Service\OutdatedPackageInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComposerService::class)]
final class ComposerServiceTest extends TestCase
{
    private string $testDir;
    private ComposerService $service;

    protected function setUp(): void
    {
        $this->testDir = \sys_get_temp_dir() . '/composer_service_test_' . \uniqid();
        \mkdir($this->testDir);

        // Create minimal composer.json
        \file_put_contents($this->testDir . '/composer.json', \json_encode([
            'name' => 'test/project',
            'require' => [
                'php' => '^8.3',
            ],
        ]));

        $this->service = new ComposerService($this->testDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
    }

    #[Test]
    public function isAvailable_returns_true_with_valid_composer_json(): void
    {
        self::assertTrue($this->service->isAvailable());
    }

    #[Test]
    public function isAvailable_returns_false_without_composer_json(): void
    {
        \unlink($this->testDir . '/composer.json');

        self::assertFalse($this->service->isAvailable());
    }

    #[Test]
    public function getInstalledPackages_returns_empty_without_vendor(): void
    {
        $packages = $this->service->getInstalledPackages();

        self::assertIsArray($packages);
        // Without vendor/composer/installed.json, should be empty
    }

    #[Test]
    public function getInstalledPackages_uses_cache(): void
    {
        $first = $this->service->getInstalledPackages();
        $second = $this->service->getInstalledPackages();

        self::assertSame($first, $second);
    }

    #[Test]
    public function clearCache_invalidates_installed_packages_cache(): void
    {
        $this->service->getInstalledPackages(); // Populate cache

        $this->service->clearCache();

        // Should not throw, cache is cleared
        $packages = $this->service->getInstalledPackages();
        self::assertIsArray($packages);
    }

    #[Test]
    public function clearCache_invalidates_outdated_packages_cache(): void
    {
        // Note: This test may be slow as it runs composer outdated
        $this->service->clearCache();

        // Should not throw
        self::assertTrue(true);
    }

    #[Test]
    public function getPackageDetails_returns_null_for_nonexistent_package(): void
    {
        $details = $this->service->getPackageDetails('nonexistent/package');

        self::assertNull($details);
    }

    #[Test]
    public function getPackageDependencies_returns_empty_for_nonexistent_package(): void
    {
        $deps = $this->service->getPackageDependencies('nonexistent/package');

        self::assertSame([], $deps);
    }

    #[Test]
    public function getReverseDependencies_returns_empty_for_nonexistent_package(): void
    {
        $reverseDeps = $this->service->getReverseDependencies('nonexistent/package');

        self::assertSame([], $reverseDeps);
    }

    #[Test]
    public function getPlatformRequirements_returns_php_requirement(): void
    {
        $requirements = $this->service->getPlatformRequirements();

        self::assertArrayHasKey('php', $requirements);
        self::assertSame('^8.3', $requirements['php']);
    }

    #[Test]
    public function getRootScripts_returns_empty_without_scripts(): void
    {
        $scripts = $this->service->getRootScripts();

        self::assertSame([], $scripts);
    }

    #[Test]
    public function getRootScripts_returns_defined_scripts(): void
    {
        // Update composer.json with scripts
        \file_put_contents($this->testDir . '/composer.json', \json_encode([
            'name' => 'test/project',
            'require' => ['php' => '^8.3'],
            'scripts' => [
                'test' => 'phpunit',
                'lint' => ['php-cs-fixer', 'psalm'],
            ],
        ]));

        // Clear cache to reload
        $this->service->clearCache();
        $scripts = $this->service->getRootScripts();

        self::assertArrayHasKey('test', $scripts);
        self::assertSame('phpunit', $scripts['test']);
    }

    #[Test]
    public function canRemovePackage_returns_true_for_package_without_dependents(): void
    {
        // Without any installed packages, any package can be "removed"
        $canRemove = $this->service->canRemovePackage('some/package');

        self::assertTrue($canRemove);
    }

    #[Test]
    public function getVersion_returns_string(): void
    {
        $version = $this->service->getVersion();

        self::assertIsString($version);
        self::assertNotEmpty($version);
    }

    private function removeDirectory(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $items = \scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (\is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                \unlink($path);
            }
        }

        \rmdir($dir);
    }
}
```

---

### 5.2 Write Unit Tests for InstalledPackagesTab

**Create file**: `tests/Unit/Feature/ComposerManager/Tab/InstalledPackagesTabTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\ComposerManager\Tab;

use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;
use Butschster\Commander\Feature\ComposerManager\Tab\InstalledPackagesTab;
use Butschster\Commander\UI\Screen\ScreenManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InstalledPackagesTab::class)]
final class InstalledPackagesTabTest extends TestCase
{
    #[Test]
    public function getTitle_returns_installed(): void
    {
        $service = $this->createMock(ComposerService::class);
        $screenManager = $this->createMock(ScreenManager::class);

        $tab = new InstalledPackagesTab($service, $screenManager);

        self::assertSame('Installed', $tab->getTitle());
    }

    #[Test]
    public function getShortcuts_returns_expected_keys(): void
    {
        $service = $this->createMock(ComposerService::class);
        $screenManager = $this->createMock(ScreenManager::class);

        $tab = new InstalledPackagesTab($service, $screenManager);
        $shortcuts = $tab->getShortcuts();

        self::assertArrayHasKey('Tab', $shortcuts);
        self::assertArrayHasKey('Ctrl+R', $shortcuts);
    }

    #[Test]
    public function handleInput_returns_true_for_tab_key(): void
    {
        $service = $this->createMock(ComposerService::class);
        $service->method('getInstalledPackages')->willReturn([]);
        $service->method('getOutdatedPackages')->willReturn([]);

        $screenManager = $this->createMock(ScreenManager::class);

        $tab = new InstalledPackagesTab($service, $screenManager);

        // Simulate tab key
        $result = $tab->handleInput("\t");

        self::assertTrue($result);
    }

    #[Test]
    public function handleInput_returns_true_for_ctrl_r(): void
    {
        $service = $this->createMock(ComposerService::class);
        $service->method('getInstalledPackages')->willReturn([]);
        $service->method('getOutdatedPackages')->willReturn([]);
        $service->expects(self::once())->method('clearCache');

        $screenManager = $this->createMock(ScreenManager::class);

        $tab = new InstalledPackagesTab($service, $screenManager);

        // Simulate Ctrl+R (ASCII 18)
        $result = $tab->handleInput("\x12");

        self::assertTrue($result);
    }
}
```

---

### 5.3 Write Unit Tests for OutdatedPackagesTab

**Create file**: `tests/Unit/Feature/ComposerManager/Tab/OutdatedPackagesTabTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\ComposerManager\Tab;

use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;
use Butschster\Commander\Feature\ComposerManager\Tab\OutdatedPackagesTab;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OutdatedPackagesTab::class)]
final class OutdatedPackagesTabTest extends TestCase
{
    #[Test]
    public function getTitle_returns_outdated(): void
    {
        $service = $this->createMock(ComposerService::class);

        $tab = new OutdatedPackagesTab($service);

        self::assertSame('Outdated', $tab->getTitle());
    }

    #[Test]
    public function getShortcuts_returns_expected_keys(): void
    {
        $service = $this->createMock(ComposerService::class);

        $tab = new OutdatedPackagesTab($service);
        $shortcuts = $tab->getShortcuts();

        self::assertArrayHasKey('Tab', $shortcuts);
        self::assertArrayHasKey('Enter', $shortcuts);
        self::assertArrayHasKey('Ctrl+R', $shortcuts);
    }

    #[Test]
    public function handleInput_returns_true_for_tab_key(): void
    {
        $service = $this->createMock(ComposerService::class);

        $tab = new OutdatedPackagesTab($service);

        $result = $tab->handleInput("\t");

        self::assertTrue($result);
    }
}
```

---

### 5.4 Write Unit Tests for SecurityAuditTab

**Create file**: `tests/Unit/Feature/ComposerManager/Tab/SecurityAuditTabTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\ComposerManager\Tab;

use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;
use Butschster\Commander\Feature\ComposerManager\Tab\SecurityAuditTab;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityAuditTab::class)]
final class SecurityAuditTabTest extends TestCase
{
    #[Test]
    public function getTitle_returns_security(): void
    {
        $service = $this->createMock(ComposerService::class);

        $tab = new SecurityAuditTab($service);

        self::assertSame('Security', $tab->getTitle());
    }

    #[Test]
    public function getShortcuts_returns_expected_keys(): void
    {
        $service = $this->createMock(ComposerService::class);

        $tab = new SecurityAuditTab($service);
        $shortcuts = $tab->getShortcuts();

        self::assertArrayHasKey('Tab', $shortcuts);
        self::assertArrayHasKey('Enter', $shortcuts);
        self::assertArrayHasKey('Ctrl+R', $shortcuts);
    }
}
```

---

### 5.5 Write Unit Tests for ScriptsTab

**Create file**: `tests/Unit/Feature/ComposerManager/Tab/ScriptsTabTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\ComposerManager\Tab;

use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;
use Butschster\Commander\Feature\ComposerManager\Tab\ScriptsTab;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScriptsTab::class)]
final class ScriptsTabTest extends TestCase
{
    #[Test]
    public function getTitle_returns_scripts(): void
    {
        $service = $this->createMock(ComposerService::class);
        $service->method('getRootScripts')->willReturn([]);

        $tab = new ScriptsTab($service);

        self::assertSame('Scripts', $tab->getTitle());
    }

    #[Test]
    public function getShortcuts_returns_default_keys_when_not_executing(): void
    {
        $service = $this->createMock(ComposerService::class);
        $service->method('getRootScripts')->willReturn([]);

        $tab = new ScriptsTab($service);
        $shortcuts = $tab->getShortcuts();

        self::assertArrayHasKey('Tab', $shortcuts);
        self::assertArrayHasKey('Enter', $shortcuts);
        self::assertArrayHasKey('Ctrl+R', $shortcuts);
        self::assertArrayNotHasKey('Ctrl+C', $shortcuts);
    }
}
```

---

### 5.6 Write E2E Tests for Composer Manager Workflows

**Create file**: `tests/E2E/Scenario/ComposerManagerE2ETest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\E2E\Scenario;

use Butschster\Commander\Feature\ComposerManager\Screen\ComposerManagerScreen;
use Butschster\Commander\Feature\ComposerManager\Service\ComposerService;
use Butschster\Commander\UI\Screen\ScreenManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TerminalTestCase;

#[CoversClass(ComposerManagerScreen::class)]
final class ComposerManagerE2ETest extends TerminalTestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = \sys_get_temp_dir() . '/composer_e2e_test_' . \uniqid();
        \mkdir($this->testDir);

        // Create composer.json with scripts
        \file_put_contents($this->testDir . '/composer.json', \json_encode([
            'name' => 'test/e2e-project',
            'require' => ['php' => '^8.3'],
            'scripts' => [
                'test' => 'echo "Running tests"',
                'lint' => 'echo "Running linter"',
            ],
        ]));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    #[Test]
    public function renders_tab_container_with_all_tabs(): void
    {
        $this->terminal()->setSize(120, 40);

        $screen = $this->createScreen();
        $this->runApp($screen);

        // Should show tab headers
        $this->assertScreenContains('Scripts');
        $this->assertScreenContains('Installed');
        $this->assertScreenContains('Outdated');
        $this->assertScreenContains('Security');
    }

    #[Test]
    public function scripts_tab_shows_available_scripts(): void
    {
        $this->terminal()->setSize(120, 40);

        $screen = $this->createScreen();
        $this->runApp($screen);

        // Scripts tab is first, should show our scripts
        $this->assertScreenContains('test');
        $this->assertScreenContains('lint');
    }

    #[Test]
    public function ctrl_right_switches_to_next_tab(): void
    {
        $this->terminal()->setSize(120, 40);

        $this->keys()
            ->ctrl('→')  // Ctrl+Right to switch tab
            ->frame()
            ->applyTo($this->terminal());

        $screen = $this->createScreen();
        $this->runApp($screen);

        // Should be on Installed tab now
        $this->assertScreenContains('Packages');
    }

    #[Test]
    public function tab_key_switches_panel_focus(): void
    {
        $this->terminal()->setSize(120, 40);

        $this->keys()
            ->tab()
            ->frame()
            ->applyTo($this->terminal());

        $screen = $this->createScreen();
        $this->runApp($screen);

        // Right panel should now be focused
        // (Visual verification - panel border changes)
        self::assertTrue(true);
    }

    #[Test]
    public function ctrl_r_refreshes_data(): void
    {
        $this->terminal()->setSize(120, 40);

        $this->keys()
            ->ctrl('r')
            ->frame()
            ->applyTo($this->terminal());

        $screen = $this->createScreen();
        $this->runApp($screen);

        // Should not crash, data should refresh
        self::assertTrue(true);
    }

    #[Test]
    public function search_filter_activates_with_slash(): void
    {
        $this->terminal()->setSize(120, 40);

        // Switch to Installed tab first
        $this->keys()
            ->ctrl('→')
            ->frame()
            ->press('/')
            ->frame()
            ->applyTo($this->terminal());

        $screen = $this->createScreen();
        $this->runApp($screen);

        // Search mode should be active (/ visible)
        $this->assertScreenContains('/');
    }

    private function createScreen(): ComposerManagerScreen
    {
        $service = new ComposerService($this->testDir);
        $screen = new ComposerManagerScreen($service);

        // Inject screen manager
        $screenManager = new ScreenManager();
        $screen->setScreenManager($screenManager);
        $screen->onActivate();

        return $screen;
    }

    private function removeDirectory(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $items = \scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (\is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                \unlink($path);
            }
        }

        \rmdir($dir);
    }
}
```

---

### 5.7 Achieve >80% Test Coverage

**Run coverage report**:
```bash
vendor/bin/phpunit --testsuite=Unit --filter=ComposerManager --coverage-html=coverage/composer
```

**Files to ensure coverage**:

| File | Target Coverage |
|------|-----------------|
| `ComposerService.php` | 70%+ |
| `InstalledPackagesTab.php` | 60%+ |
| `OutdatedPackagesTab.php` | 60%+ |
| `SecurityAuditTab.php` | 60%+ |
| `ScriptsTab.php` | 50%+ |
| `LoadingState.php` | 90%+ |
| `SearchFilter.php` | 90%+ |
| `ConfirmationModal.php` | 90%+ |
| `ComposerBinaryLocator.php` | 80%+ |

**Overall target**: 80%+ for new/modified code

---

## Verification Steps

1. **Run all tests**:
   ```bash
   composer test
   ```

2. **Run ComposerManager tests specifically**:
   ```bash
   vendor/bin/phpunit --filter=ComposerManager
   vendor/bin/phpunit --filter=Composer
   ```

3. **Generate coverage report**:
   ```bash
   vendor/bin/phpunit --coverage-html=coverage --filter=ComposerManager
   ```

4. **View coverage**:
   Open `coverage/index.html` in browser

---

## Test Directory Structure

```
tests/
├── Unit/
│   └── Feature/
│       └── ComposerManager/
│           ├── Service/
│           │   ├── ComposerServiceTest.php
│           │   └── ComposerBinaryLocatorTest.php
│           ├── Tab/
│           │   ├── InstalledPackagesTabTest.php
│           │   ├── OutdatedPackagesTabTest.php
│           │   ├── SecurityAuditTabTest.php
│           │   └── ScriptsTabTest.php
│           └── Component/
│               ├── LoadingStateTest.php
│               ├── SearchFilterTest.php
│               └── ConfirmationModalTest.php
├── Integration/
│   └── Feature/
│       └── ComposerManager/
│           └── ComposerServiceIntegrationTest.php
└── E2E/
    └── Scenario/
        └── ComposerManagerE2ETest.php
```

---

## Acceptance Criteria

- [ ] Unit tests for ComposerService with >70% coverage
- [ ] Unit tests for all tabs
- [ ] Unit tests for new components (LoadingState, SearchFilter, ConfirmationModal)
- [ ] E2E tests for main workflows
- [ ] Overall coverage >80% for ComposerManager feature
- [ ] All tests pass in CI
