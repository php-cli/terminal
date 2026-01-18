# Feature: Composer Manager UX/DX Improvements

## Overview

Enhance the Composer Manager feature with missing functionality, improved user experience, loading states, and comprehensive testing.

**Goal**: Make the Composer Manager fully functional for package management, not just viewing.

## Stage Dependencies

```
Stage 1 (Quick Fixes) ────────────────────────────┐
     │                                             │
     ▼                                             │
Stage 2 (Loading States) ─────────────────────────┤
     │                                             │
     ▼                                             │
Stage 3 (Update Functionality) ───────────────────┤
     │                                             │
     ▼                                             │
Stage 4 (Search Filter) ──────────────────────────┤
     │                                             │
     ▼                                             │
Stage 5 (Testing) ────────────────────────────────┤
     │                                             │
     ▼                                             │
Stage 6 (Advanced Features) ──────────────────────┘
```

## Development Progress

### Stage 1: Quick Fixes
- [ ] 1.1: Fix "Enter to Update" shortcut label in OutdatedPackagesTab
- [ ] 1.2: Extract `ComposerBinaryLocator` utility from duplicate code
- [ ] 1.3: Standardize lazy loading in InstalledPackagesTab
- [ ] 1.4: Add package count to Installed tab panel title
- [ ] 1.5: Write unit tests for ComposerBinaryLocator

**Code References**:
- `src/Feature/ComposerManager/Tab/OutdatedPackagesTab.php:54-58` - Shortcut hint
- `src/Feature/ComposerManager/Service/ComposerService.php:683-701` - Binary locator #1
- `src/Feature/ComposerManager/Tab/ScriptsTab.php:548-566` - Binary locator #2
- `src/Feature/ComposerManager/Tab/InstalledPackagesTab.php:106-110` - Missing lazy loading

**Status**: Not Started

---

### Stage 2: Loading States
- [ ] 2.1: Create `LoadingState` component with spinner
- [ ] 2.2: Add loading state to OutdatedPackagesTab
- [ ] 2.3: Add loading state to SecurityAuditTab
- [ ] 2.4: Add loading state to Ctrl+R refresh operations
- [ ] 2.5: Write unit tests for LoadingState component

**Code References**:
- `src/UI/Component/Display/Spinner.php` - Existing spinner component to reuse
- `src/Feature/ComposerManager/Tab/OutdatedPackagesTab.php:192-202` - loadData()
- `src/Feature/ComposerManager/Tab/SecurityAuditTab.php:173-205` - loadData()

**Status**: Not Started

---

### Stage 3: Update Functionality
- [ ] 3.1: Create confirmation modal component
- [ ] 3.2: Wire Enter key in OutdatedPackagesTab to update flow
- [ ] 3.3: Implement `performPackageUpdate()` with real-time output
- [ ] 3.4: Handle update errors gracefully with error display
- [ ] 3.5: Invalidate caches after successful update
- [ ] 3.6: Write integration tests for update flow

**Code References**:
- `src/Feature/ComposerManager/Tab/OutdatedPackagesTab.php:184-186` - TODO comment
- `src/Feature/ComposerManager/Service/ComposerService.php:306-309` - updatePackage()
- `src/Feature/ComposerManager/Tab/ScriptsTab.php:344-392` - Script execution pattern to follow
- `src/UI/Component/Layout/Modal.php` - Existing modal component

**Status**: Not Started

---

### Stage 4: Search Filter
- [ ] 4.1: Create `SearchFilter` component with input handling
- [ ] 4.2: Add search to InstalledPackagesTab (/ or Ctrl+F)
- [ ] 4.3: Add search to OutdatedPackagesTab
- [ ] 4.4: Implement table filtering based on query
- [ ] 4.5: Add visual feedback for active filter
- [ ] 4.6: Write unit tests for SearchFilter component

**Code References**:
- `src/UI/Component/Input/TextField.php` - Existing input component pattern
- `src/Feature/ComposerManager/Tab/InstalledPackagesTab.php:131-232` - Table setup
- `src/UI/Component/Display/TableComponent.php` - Table filtering integration

**Status**: Not Started

---

### Stage 5: Testing
- [ ] 5.1: Write unit tests for ComposerService
- [ ] 5.2: Write unit tests for InstalledPackagesTab
- [ ] 5.3: Write unit tests for OutdatedPackagesTab
- [ ] 5.4: Write unit tests for SecurityAuditTab
- [ ] 5.5: Write unit tests for ScriptsTab
- [ ] 5.6: Write E2E tests for Composer Manager workflows
- [ ] 5.7: Achieve >80% test coverage for ComposerManager

**Code References**:
- `tests/Integration/Module/ComposerModuleTest.php` - Existing test
- `tests/TerminalTestCase.php` - Base test class
- `tests/E2E/Scenario/MenuSystemScenarioTest.php` - E2E test patterns

**Status**: Not Started

---

### Stage 6: Advanced Features (Optional)
- [ ] 6.1: Create PackageActionMenu component (Update/Remove/Info)
- [ ] 6.2: Implement package removal with confirmation
- [ ] 6.3: Optimize reverse dependency lookup with graph caching
- [ ] 6.4: Add script execution confirmation for destructive scripts
- [ ] 6.5: Consider Packagist search integration

**Code References**:
- `src/Feature/ComposerManager/Service/ComposerService.php:128-149` - O(n²) algorithm
- `src/Feature/ComposerManager/Service/ComposerService.php:328-331` - removePackage()
- `src/Feature/ComposerManager/Service/SearchResult.php` - Unused model
- `src/UI/Menu/` - Menu patterns for action menu

**Status**: Not Started

---

## Codebase References

### Core Files
- `src/Feature/ComposerManager/Screen/ComposerManagerScreen.php:1-124` - Main screen
- `src/Feature/ComposerManager/Service/ComposerService.php:1-712` - Core service
- `src/Feature/ComposerManager/Tab/InstalledPackagesTab.php:1-365` - Installed tab
- `src/Feature/ComposerManager/Tab/OutdatedPackagesTab.php:1-259` - Outdated tab
- `src/Feature/ComposerManager/Tab/SecurityAuditTab.php:1-247` - Security tab
- `src/Feature/ComposerManager/Tab/ScriptsTab.php:1-626` - Scripts tab

### UI Components (for reference)
- `src/UI/Component/Display/Spinner.php` - Spinner animation
- `src/UI/Component/Display/Alert.php` - Alert messages
- `src/UI/Component/Layout/Modal.php` - Modal dialogs
- `src/UI/Component/Layout/Panel.php` - Panels
- `src/UI/Component/Display/TableComponent.php` - Tables

### Testing Infrastructure
- `tests/TerminalTestCase.php` - Base test class
- `tests/Testing/VirtualTerminalDriver.php` - Virtual terminal
- `tests/Testing/ScriptedKeySequence.php` - Key input builder

---

## Usage Instructions

Keep this checklist updated:
- Mark completed substeps immediately with [x]
- Add notes about deviations or challenges
- Document decisions differing from plan
- Update status when starting/completing stages

---

## Final Acceptance Criteria

### P0 - Must Have
- [ ] "Enter to Update" hint accurately reflects behavior
- [ ] Loading indicators shown during slow operations
- [ ] No regressions in existing functionality

### P1 - Should Have
- [ ] Package update functionality works
- [ ] Search/filter available for package lists
- [ ] Lazy loading consistent across all tabs

### P2 - Could Have
- [ ] Package removal UI works
- [ ] Confirmation for destructive scripts
- [ ] >80% test coverage for ComposerManager

---

## Quick Reference: Key Locations

| Issue | File | Line |
|-------|------|------|
| Misleading hint | `OutdatedPackagesTab.php` | 54-58 |
| TODO comment | `OutdatedPackagesTab.php` | 184-186 |
| Binary locator #1 | `ComposerService.php` | 683-701 |
| Binary locator #2 | `ScriptsTab.php` | 548-566 |
| O(n²) algorithm | `ComposerService.php` | 128-149 |
| Missing lazy load | `InstalledPackagesTab.php` | 106-110 |
| Unused model | `SearchResult.php` | 1-19 |
