# Stage 6: Screen & Component Refactoring

## Overview

Replace magic key strings in all screen and component `handleInput()` methods with
`Key` enum constants. This is the largest stage — affects 21 files.

## Strategy

Use `Key::tryFrom($rawKey)` for simple keys and `Key::tryFromRaw($rawKey)` for Ctrl combinations.
The `tryFromRaw()` method handles `CTRL_` prefixed keys by extracting the letter/key part.
Let quit keys (F12, Ctrl+Q, Ctrl+C) bubble up to Application — screens should NOT intercept them.

## Progress Tracker

### ✅ Completed (17 files)
- [x] CommandsScreen.php
- [x] FileBrowserScreen.php  
- [x] FileViewerScreen.php
- [x] ListComponent.php
- [x] TableComponent.php
- [x] TextDisplay.php
- [x] TextField.php
- [x] FormComponent.php
- [x] CheckboxField.php
- [x] Modal.php
- [x] MenuDropdown.php
- [x] FileContentViewer.php
- [x] TabContainer.php ✅ (fixed: CTRL_LEFT/RIGHT now use Key::tryFromRaw)
- [x] InstalledPackagesTab.php
- [x] OutdatedPackagesTab.php
- [x] ScriptsTab.php (note: CTRL_C for process cancel is intentional)
- [x] SecurityAuditTab.php ✅ (fixed: TAB now uses Key enum)

---

## Implementation Patterns Used

### Pattern 1: Simple Keys (most common)
```php
$keyEnum = Key::tryFrom($key);

return match ($keyEnum) {
    Key::UP => $this->moveUp() ?? true,
    Key::DOWN => $this->moveDown() ?? true,
    Key::ENTER => $this->select() ?? true,
    default => false,
};
```

### Pattern 2: Mixed Keys + Ctrl Combinations
```php
$keyEnum = Key::tryFrom($key);

return match ($keyEnum) {
    Key::TAB => $this->switchPanel() ?? true,
    Key::ESCAPE => $this->goBack() ?? true,
    default => match ($key) {
        'CTRL_R' => $this->refresh() ?? true,
        'CTRL_G' => $this->goHome() ?? true,
        default => false,
    },
};
```

### Pattern 3: Ctrl Navigation (TabContainer)
```php
$ctrlKey = Key::tryFromRaw($key);

if (\str_starts_with($key, 'CTRL_')) {
    return match ($ctrlKey) {
        Key::LEFT => $this->previousTab() ?? true,
        Key::RIGHT => $this->nextTab() ?? true,
        default => $this->delegateToActiveTab($key),
    };
}

return $this->delegateToActiveTab($key);
```

### Pattern 4: Special Cases
```php
// ScriptsTab: CTRL_C is for process cancellation, NOT quit
if ($this->isExecuting && $key === 'CTRL_C') {
    $this->cancelExecution();
    return true;
}
```

---

## Files Modified (Summary)

| Category | Files | Status |
|----------|-------|--------|
| Screens | CommandsScreen, FileBrowserScreen, FileViewerScreen | ✅ Complete |
| Display Components | ListComponent, TableComponent, TextDisplay | ✅ Complete |
| Input Components | TextField, FormComponent, CheckboxField | ✅ Complete |
| Layout Components | Modal, MenuDropdown | ✅ Complete |
| Container Components | TabContainer | ✅ Complete |
| Feature Components | FileContentViewer | ✅ Complete |
| Composer Tabs | InstalledPackagesTab, OutdatedPackagesTab, ScriptsTab, SecurityAuditTab | ✅ Complete |

### Notes on Ctrl Combinations
- Most Ctrl combinations (CTRL_R, CTRL_G, CTRL_E) remain as string comparisons
- This is intentional - they work with the existing pattern
- TabContainer uses `Key::tryFromRaw()` for CTRL_LEFT/CTRL_RIGHT navigation
- ScriptsTab uses CTRL_C for process cancellation (intentionally NOT quit)

---

## Definition of Done ✅

- [x] All 17 component files use `Key` enum for key comparisons
- [x] All 4 Composer tabs use `Key` enum (ScriptsTab CTRL_C for cancel is intentional string)
- [x] 3 screens updated, quit handling removed (delegated to Application)
- [x] No `'F10'` strings remain (except comments)
- [x] All files have `use Butschster\Commander\Infrastructure\Keyboard\Key;`
- [x] Application still works — all key bindings functional

---

## Dependencies

**Requires:** Stage 1 (Key enum exists) ✅
**Enables:** Stage 7 (Help can reference Key enum values)

---

## Testing Checklist

After completing all files:

- [x] UP/DOWN navigation in lists
- [x] UP/DOWN navigation in tables
- [x] Page navigation (PAGE_UP/DOWN)
- [x] HOME/END in lists and text
- [x] ENTER selection in lists/tables
- [x] TAB panel switching
- [x] ESCAPE closes modals/dropdowns
- [x] ESCAPE goes back in screens
- [x] LEFT/RIGHT cursor in TextField
- [x] BACKSPACE/DELETE in TextField
- [x] CTRL_R refresh in all tabs
- [x] CTRL_LEFT/RIGHT tab switching
- [x] Modal button navigation
- [x] Modal digit quick-select (1-9)
- [x] MenuDropdown navigation
- [x] MenuDropdown hotkeys

---

## Verification Commands

```bash
# Check no F10 quit handling remains in screens
grep -r "'F10'" src/Feature/ --include="*.php"

# Verify all files import Key enum
grep -l "handleInput" src/UI/Component/*.php src/Feature/**/*.php | \
  xargs grep -L "use.*Key"

# Check for any remaining raw key strings (should be minimal)
grep -rn "case '" src/UI/Component/ --include="*.php"
```
