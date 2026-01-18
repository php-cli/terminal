# FR-10: Polymorphic Menu Item Refactoring — Master Checklist

## Overview
Refactor `MenuItem` from string-based type discrimination to a polymorphic class hierarchy.

---

## Stage 1: Create Interface and Base Class
- [x] Create `MenuItemInterface` with contract methods
- [x] Create `AbstractMenuItem` with shared implementation
- [x] Add unit tests for base functionality

## Stage 2: Create Concrete Item Classes
- [x] Create `ScreenMenuItem`
- [x] Create `ActionMenuItem` (with callable→Closure conversion)
- [x] Create `SubmenuMenuItem`
- [x] Create `SeparatorMenuItem`
- [x] Add unit tests for each class

## Stage 3: Update MenuBuilder
- [x] Replace `MenuItem::screen()` with `ScreenMenuItem::create()`
- [x] Replace `MenuItem::action()` with `ActionMenuItem::create()`
- [x] Replace `MenuItem::separator()` with `SeparatorMenuItem::create()`
- [x] Update type hints to `MenuItemInterface`
- [x] Verify all tests pass

## Stage 4: Update MenuDefinition
- [x] Change `array<MenuItem>` to `array<MenuItemInterface>`
- [x] Update `getFirstItem()` return type

## Stage 5: Refactor MenuSystem
- [x] Update imports
- [x] Refactor `handleMenuItemSelected()` to use `match` with `instanceof`
- [x] Remove any `isScreen()` / `isAction()` calls

## Stage 6: Refactor MenuDropdown
- [x] Update imports
- [x] Refactor `renderItem()` for polymorphic rendering
- [x] Keep `isSeparator()` calls (interface method)

## Stage 7: Update Existing Tests
- [x] Update `MenuDropdownTest` imports
- [x] Replace `MenuItem::action()` → `ActionMenuItem::create()`
- [x] Replace `MenuItem::separator()` → `SeparatorMenuItem::create()`
- [x] Update callback type hints `MenuItem` → `MenuItemInterface`
- [x] Replace `$item->label` → `$item->getLabel()`
- [x] Verify all tests pass

## Stage 8: Cleanup
- [ ] Delete old `MenuItem.php` ← **MANUAL: `rm src/UI/Menu/MenuItem.php`**
- [x] Search for remaining references (none found)
- [x] Run full test suite (all pass)
- [x] Run PHPStan
- [x] Update any documentation

## Stage 9: E2E Tests
- [x] Create `MenuSystemScenarioTest.php`
- [x] Test menu bar rendering with F-key hints
- [x] Test F-key opens dropdown
- [x] Test ESC closes dropdown
- [x] Test arrow key navigation
- [x] Test `ScreenMenuItem` triggers navigation
- [x] Test `ActionMenuItem` executes closure
- [x] Test `SeparatorMenuItem` is skipped during navigation
- [x] Test hotkey selection
- [x] Test `SubmenuMenuItem` shows ► indicator
- [x] Test `MenuDefinition.getFirstItem()` edge cases
- [x] All 15 E2E tests pass

---

## Progress Tracker

| Stage | Status | Notes |
|-------|--------|-------|
| 1 | ✅ Complete | Interface + AbstractMenuItem |
| 2 | ✅ Complete | 4 concrete classes |
| 3 | ✅ Complete | MenuBuilder migration |
| 4 | ✅ Complete | MenuDefinition type hints |
| 5 | ✅ Complete | MenuSystem refactoring |
| 6 | ✅ Complete | MenuDropdown refactoring |
| 7 | ✅ Complete | Update existing tests |
| 8 | ⬜ Not Started | Cleanup + verification |
| 9 | ✅ Complete | E2E tests (15 tests) |
