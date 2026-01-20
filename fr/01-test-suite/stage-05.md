# Stage 5: Integration Tests - Screens & Menu

## Overview

Create integration tests for the screen management system and menu system. These tests verify that multiple components
work together correctly for navigation and user interaction flows.

## Files

CREATE:

- `tests/Integration/Screen/ScreenManagerTest.php`
- `tests/Integration/Screen/ScreenRegistryTest.php`
- `tests/Integration/Menu/MenuSystemTest.php`
- `tests/Integration/Menu/MenuBuilderTest.php`

## Code References

- `src/UI/Screen/ScreenManager.php:1-150` - Screen stack management
- `src/UI/Screen/ScreenRegistry.php:1-120` - Screen discovery
- `src/UI/Component/Layout/MenuSystem.php:1-300` - Menu bar and dropdowns
- `src/UI/Menu/MenuBuilder.php:1-120` - Menu construction

## Implementation Details

### ScreenManagerTest Key Tests

```php
public function test_pushScreen_adds_to_stack(): void
public function test_pushScreen_activates_new_screen(): void
public function test_pushScreen_deactivates_previous_screen(): void
public function test_popScreen_removes_from_stack(): void
public function test_popScreen_returns_removed_screen(): void
public function test_popScreen_reactivates_previous_screen(): void
public function test_replaceScreen_swaps_current(): void
public function test_getCurrentScreen_returns_top(): void
public function test_getDepth_returns_stack_size(): void
public function test_hasScreens_returns_correct_state(): void
public function test_clear_removes_all_screens(): void
public function test_popUntil_stops_at_matching_screen(): void
public function test_render_delegates_to_current_screen(): void
public function test_handleInput_delegates_to_current_screen(): void
public function test_update_delegates_to_current_screen(): void
```

### ScreenRegistryTest Key Tests

```php
public function test_register_stores_screen(): void
public function test_register_extracts_metadata_from_attribute(): void
public function test_register_throws_on_duplicate_name(): void
public function test_getScreen_returns_registered_screen(): void
public function test_getScreen_returns_null_for_unknown(): void
public function test_getNames_returns_all_names(): void
public function test_getByCategory_groups_screens(): void
public function test_getByCategory_sorts_by_priority(): void
public function test_has_returns_correct_state(): void
public function test_count_returns_screen_count(): void
public function test_sets_screen_manager_on_aware_screens(): void
```

### MenuSystemTest Key Tests

```php
public function test_renders_menu_bar(): void
public function test_handles_fkey_press_opens_dropdown(): void
public function test_closes_dropdown_on_escape(): void
public function test_navigates_dropdown_with_arrows(): void
public function test_selects_item_with_enter(): void
public function test_navigates_to_screen_on_select(): void
public function test_executes_action_on_select(): void
public function test_f10_triggers_quit(): void
public function test_renderDropdown_overlays_content(): void
public function test_isDropdownOpen_returns_correct_state(): void
```

### MenuBuilderTest Key Tests

```php
public function test_builds_menus_from_registry(): void
public function test_assigns_fkeys_from_map(): void
public function test_groups_screens_by_category(): void
public function test_sorts_menus_by_priority(): void
public function test_adds_quit_menu(): void
public function test_withFKeys_overrides_mapping(): void
public function test_addItem_extends_menu(): void
public function test_addSeparator_adds_separator_item(): void
```

### Testing Patterns

```php
// Create test screen for integration tests
#[Metadata(name: 'test_screen', title: 'Test', category: 'test')]
class TestScreen implements ScreenInterface
{
    public bool $activated = false;
    public bool $deactivated = false;
    public array $inputHistory = [];
    
    public function render(...): void {}
    public function handleInput(string $key): bool 
    {
        $this->inputHistory[] = $key;
        return true;
    }
    public function onActivate(): void { $this->activated = true; }
    public function onDeactivate(): void { $this->deactivated = true; }
    public function update(): void {}
    public function getTitle(): string { return 'Test'; }
}

// Pattern for testing screen navigation
public function test_navigation_flow(): void
{
    $manager = new ScreenManager();
    
    $screen1 = new TestScreen();
    $screen2 = new TestScreen();
    
    $manager->pushScreen($screen1);
    $this->assertTrue($screen1->activated);
    
    $manager->pushScreen($screen2);
    $this->assertTrue($screen1->deactivated);
    $this->assertTrue($screen2->activated);
    
    $manager->popScreen();
    $this->assertTrue($screen2->deactivated);
    $this->assertTrue($screen1->activated); // Re-activated
}

// Pattern for testing menu interaction
public function test_menu_navigation_flow(): void
{
    $registry = new ScreenRegistry($manager);
    $registry->register(new TestScreen());
    
    $menus = (new MenuBuilder($registry))->build();
    $menuSystem = new MenuSystem($menus, $registry, $manager);
    
    // Press F3 to open menu
    $handled = $menuSystem->handleInput('F3');
    $this->assertTrue($handled);
    $this->assertTrue($menuSystem->isDropdownOpen());
    
    // Press Enter to select first item
    $menuSystem->handleInput('ENTER');
    $this->assertFalse($menuSystem->isDropdownOpen());
    
    // Verify screen was pushed
    $this->assertNotNull($manager->getCurrentScreen());
}
```

## Definition of Done

- [ ] ScreenManagerTest covers all navigation operations
- [ ] ScreenRegistryTest covers registration and metadata extraction
- [ ] MenuSystemTest covers menu bar and dropdown interaction
- [ ] MenuBuilderTest covers menu construction from registry
- [ ] All tests pass with `vendor/bin/phpunit tests/Integration`
- [ ] Tests verify component interaction, not just individual behavior
- [ ] Tests cover error cases (missing screens, invalid operations)

## Dependencies

**Requires**: Stage 1 (infrastructure), Stage 2-4 (component tests)
**Enables**: Complete test coverage for the framework
