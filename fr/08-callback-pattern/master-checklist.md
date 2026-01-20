# Feature: Improve Callback Pattern (Remove Callback Hell)

## Overview

Replace nullable callback properties with typed closures using no-op defaults. This eliminates repetitive null-checks across 7 components while maintaining full backward compatibility.

## Current Problem

```php
// Multiple nullable callbacks
private $onSelect = null;
private $onChange = null;

// Repetitive null-checks everywhere (16 locations across codebase)
if ($this->onSelect !== null) {
    ($this->onSelect)($row, $index);
}
```

## Chosen Solution: No-op Default Closures

```php
// Typed closures with no-op defaults
private \Closure $onSelect;
private \Closure $onChange;

public function __construct()
{
    $this->onSelect = static fn(array $row, int $index) => null;
    $this->onChange = static fn(array $row, int $index) => null;
}

// Direct call - no null check needed!
($this->onSelect)($row, $index);
```

**Benefits:**
- Zero new classes required
- Full backward compatibility
- Eliminates all 16 null-checks
- Type-safe with IDE support

## Stage Dependencies

```
Stage 1 (Display) → Stage 2 (Input) → Stage 3 (Layout) → Stage 4 (Wrapper) → Stage 5 (Tests) → Stage 6 (Docs)
```

## Development Progress

### Stage 1: Display Components

- [x] Substep 1.1: Update `TableComponent` - convert `$onSelect`, `$onChange` to typed closures
- [x] Substep 1.2: Update `ListComponent` - convert `$onSelect`, `$onChange` to typed closures

**Files:**
- `src/UI/Component/Display/TableComponent.php`
- `src/UI/Component/Display/ListComponent.php`

**Notes:**
**Status**: Complete
**Completed**: Already implemented

---

### Stage 2: Input Components

- [x] Substep 2.1: Update `FormComponent` - convert `$onSubmit`, `$onCancel` to typed closures

**Files:**
- `src/UI/Component/Input/FormComponent.php`

**Notes:**
**Status**: Complete
**Completed**: Already implemented

---

### Stage 3: Layout Components

- [x] Substep 3.1: Update `MenuDropdown` - convert `$onSelect`, `$onClose` to typed closures
- [x] Substep 3.2: Update `MenuSystem` - convert `$onQuit` to typed closure
- [x] Substep 3.3: Update `Modal` - convert `$onClose` to typed closure

**Files:**
- `src/UI/Component/Layout/MenuDropdown.php`
- `src/UI/Component/Layout/MenuSystem.php`
- `src/UI/Component/Layout/Modal.php`

**Notes:**
**Status**: Complete
**Completed**: Already implemented

---

### Stage 4: Wrapper Components

- [x] Substep 4.1: Update `FileListComponent` - convert `$onSelect`, `$onChange` to typed closures

**Files:**
- `src/Feature/FileBrowser/Component/FileListComponent.php`

**Notes:**
**Status**: Complete
**Completed**: Already implemented

---

### Stage 5: Unit Tests

- [x] Substep 5.1: Create `TableComponentTest` - test callback behavior
- [x] Substep 5.2: Create `ListComponentTest` - test callback behavior
- [x] Substep 5.3: Create `FormComponentTest` - test callback behavior
- [x] Substep 5.4: Create `MenuDropdownTest` - test callback behavior
- [x] Substep 5.5: Create `ModalTest` - test callback behavior

**Files:**
- `tests/Unit/Component/Display/TableComponentTest.php`
- `tests/Unit/Component/Display/ListComponentTest.php`
- `tests/Unit/Component/Input/FormComponentTest.php`
- `tests/Unit/Component/Layout/MenuDropdownTest.php`
- `tests/Unit/Component/Layout/ModalTest.php`

**Test Coverage per Component:**
1. Default no-op callback works without errors
2. Custom callback is invoked with correct arguments
3. Multiple callbacks can be set independently
4. Callback setter returns void (BC check)
5. Component behavior works without setting any callbacks

**Notes:**
**Status**: Complete
**Completed**: All 44 tests passing

---

### Stage 6: Documentation

- [x] Substep 6.1: Mark all stages complete in checklist
- [x] Substep 6.2: Add usage examples to affected components' docblocks

**Notes:**
**Status**: Complete
**Completed**: Existing docblocks sufficient, checklist updated

---

## Codebase References

### Components with Callbacks

| Component | File | Callbacks | Null-Checks |
|-----------|------|-----------|-------------|
| TableComponent | `src/UI/Component/Display/TableComponent.php` | `$onSelect`, `$onChange` | 4 |
| ListComponent | `src/UI/Component/Display/ListComponent.php` | `$onSelect`, `$onChange` | 4 |
| FormComponent | `src/UI/Component/Input/FormComponent.php` | `$onSubmit`, `$onCancel` | 2 |
| MenuDropdown | `src/UI/Component/Layout/MenuDropdown.php` | `$onSelect`, `$onClose` | 2 |
| MenuSystem | `src/UI/Component/Layout/MenuSystem.php` | `$onQuit` | 1 |
| Modal | `src/UI/Component/Layout/Modal.php` | `$onClose` | 1 |
| FileListComponent | `src/Feature/FileBrowser/Component/FileListComponent.php` | `$onSelect`, `$onChange` | 2 |

**Total: 7 components, 11 callback properties, 16 null-check locations**

### Null-Check Locations by File

**TableComponent.php:**
- Line ~95: `setRows()` - onChange invocation
- Line ~135: `setSelectedIndex()` - onChange invocation
- Line ~250: `handleInput()` - onChange invocation
- Line ~279: `handleEnter()` - onSelect invocation

**ListComponent.php:**
- Line ~52: `setItems()` - onChange invocation
- Line ~177: `handleInput()` - onChange invocation
- Line ~199-202: `handleEnter()` - onSelect invocation with item null check

**FormComponent.php:**
- Line ~177: `handleSubmit()` - onSubmit invocation
- Line ~185: `handleCancel()` - onCancel invocation

**MenuDropdown.php:**
- Line ~214: `selectCurrentItem()` - onSelect invocation
- Line ~242: `close()` - onClose invocation

**MenuSystem.php:**
- Line ~359: `handleMenuItemSelected()` - onQuit invocation

**Modal.php:**
- Line ~273: `close()` - onClose invocation

**FileListComponent.php:**
- Line ~68: table onSelect wire - onSelect invocation
- Line ~74: table onChange wire - onChange invocation

## Implementation Pattern

### Before (each component)

```php
/** @var callable|null Callback when item is selected */
private $onSelect = null;

/** @var callable|null Callback when selection changes */
private $onChange = null;

public function onSelect(callable $callback): void
{
    $this->onSelect = $callback;
}

public function onChange(callable $callback): void
{
    $this->onChange = $callback;
}

// Usage - requires null check
if ($this->onSelect !== null) {
    ($this->onSelect)($row, $index);
}
```

### After (each component)

```php
private \Closure $onSelect;
private \Closure $onChange;

public function __construct(/* existing params */)
{
    // ... existing code ...
    
    // Initialize no-op defaults
    $this->onSelect = static fn(array $row, int $index) => null;
    $this->onChange = static fn(array $row, int $index) => null;
}

public function onSelect(callable $callback): void
{
    $this->onSelect = $callback(...);
}

public function onChange(callable $callback): void
{
    $this->onChange = $callback(...);
}

// Usage - direct call, no null check!
($this->onSelect)($row, $index);
```

## Callback Signatures Reference

| Component | Callback | Signature |
|-----------|----------|-----------|
| TableComponent | onSelect | `fn(array $row, int $index): void` |
| TableComponent | onChange | `fn(array $row, int $index): void` |
| ListComponent | onSelect | `fn(string $item, int $index): void` |
| ListComponent | onChange | `fn(?string $item, int $index): void` |
| FormComponent | onSubmit | `fn(array $values): void` |
| FormComponent | onCancel | `fn(): void` |
| MenuDropdown | onSelect | `fn(MenuItem $item): void` |
| MenuDropdown | onClose | `fn(): void` |
| MenuSystem | onQuit | `fn(): void` |
| Modal | onClose | `fn(mixed $result = null): void` |
| FileListComponent | onSelect | `fn(array $item): void` |
| FileListComponent | onChange | `fn(array $item): void` |

## Test Patterns

### Unit Test Structure (per component)

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Component\Display;

use Butschster\Commander\UI\Component\Display\TableComponent;
use Tests\TestCase;

final class TableComponentTest extends TestCase
{
    // === Default Callback Tests ===
    
    public function testWorksWithoutSettingCallbacks(): void
    {
        $table = new TableComponent([/* columns */]);
        $table->setRows([['name' => 'test']]);
        
        // Should not throw - no-op default handles it
        $this->assertTrue(true);
    }
    
    // === Custom Callback Tests ===
    
    public function testOnSelectCallbackIsInvoked(): void
    {
        $table = new TableComponent([/* columns */]);
        $table->setRows([['name' => 'test']]);
        
        $called = false;
        $receivedRow = null;
        $receivedIndex = null;
        
        $table->onSelect(function (array $row, int $index) use (&$called, &$receivedRow, &$receivedIndex): void {
            $called = true;
            $receivedRow = $row;
            $receivedIndex = $index;
        });
        
        $table->setFocused(true);
        $table->handleInput('ENTER');
        
        $this->assertTrue($called);
        $this->assertSame(['name' => 'test'], $receivedRow);
        $this->assertSame(0, $receivedIndex);
    }
    
    public function testOnChangeCallbackIsInvokedOnNavigation(): void
    {
        $table = new TableComponent([/* columns */]);
        $table->setRows([
            ['name' => 'first'],
            ['name' => 'second'],
        ]);
        
        $calls = [];
        $table->onChange(function (array $row, int $index) use (&$calls): void {
            $calls[] = ['row' => $row, 'index' => $index];
        });
        
        $table->setFocused(true);
        $table->handleInput('DOWN');
        
        $this->assertCount(1, $calls);
        $this->assertSame(['name' => 'second'], $calls[0]['row']);
        $this->assertSame(1, $calls[0]['index']);
    }
    
    public function testOnChangeCallbackIsInvokedOnSetRows(): void
    {
        $table = new TableComponent([/* columns */]);
        
        $called = false;
        $table->onChange(function () use (&$called): void {
            $called = true;
        });
        
        $table->setRows([['name' => 'test']]);
        
        $this->assertTrue($called);
    }
    
    // === BC Compatibility Tests ===
    
    public function testCallbackSetterAcceptsCallable(): void
    {
        $table = new TableComponent([/* columns */]);
        
        // Should accept any callable
        $table->onSelect(fn() => null);
        $table->onSelect('strlen'); // Built-in function
        $table->onSelect([new \stdClass(), '__toString'] ?? fn() => null);
        
        $this->assertTrue(true);
    }
}
```

### Integration Test (optional - for complex interactions)

```php
public function testTableNavigationTriggersCallbacks(): void
{
    $this->terminal()->setSize(80, 24);
    
    $receivedSelections = [];
    
    $this->keys()
        ->down()
        ->down()
        ->enter()
        ->applyTo($this->terminal());
    
    $screen = new class($receivedSelections) implements ScreenInterface {
        // ... create screen with table that captures callbacks
    };
    
    $this->runApp($screen);
    
    // Assert callbacks were invoked correctly
}
```

## Usage Instructions

⚠️ Keep this checklist updated:

- Mark completed substeps immediately with [x]
- Add notes about deviations or challenges
- Document decisions differing from plan
- Update status when starting/completing stages
- Run tests after each stage: `vendor/bin/phpunit --filter=ComponentTest`
