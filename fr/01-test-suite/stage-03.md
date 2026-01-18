# Stage 3: Unit Tests - Infrastructure

## Overview

Create unit tests for the infrastructure layer: KeyboardHandler, Renderer buffer operations, ColorScheme utilities, and
SizeUnit calculations. These are foundational classes that other components depend on.

## Files

CREATE:

- `tests/Unit/Infrastructure/KeyboardHandlerTest.php`
- `tests/Unit/Infrastructure/RendererTest.php`
- `tests/Unit/Theme/ColorSchemeTest.php`
- `tests/Unit/Component/Container/SizeUnitTest.php`

## Code References

- `src/Infrastructure/Terminal/KeyboardHandler.php:1-200` - Key parsing logic
- `src/Infrastructure/Terminal/Renderer.php:1-250` - Buffer operations
- `src/UI/Theme/ColorScheme.php:1-150` - Color utilities
- `src/UI/Component/Container/SizeUnit.php` - Size calculations

## Implementation Details

### KeyboardHandlerTest Key Tests

```php
public function test_parses_regular_characters(): void
public function test_parses_arrow_keys(): void
public function test_parses_function_keys(): void
public function test_parses_ctrl_combinations(): void
public function test_parses_enter_key(): void
public function test_parses_escape_key(): void
public function test_parses_page_up_page_down(): void
public function test_parses_home_end(): void
public function test_returns_null_when_no_input(): void
```

Note: KeyboardHandler tests may need to mock STDIN or use process isolation.

### RendererTest Key Tests

```php
public function test_writeAt_stores_characters_in_buffer(): void
public function test_writeAt_clips_to_boundaries(): void
public function test_writeAt_handles_unicode(): void
public function test_drawBox_renders_corners(): void
public function test_drawBox_renders_horizontal_edges(): void
public function test_drawBox_renders_vertical_edges(): void
public function test_fillRect_fills_area(): void
public function test_beginFrame_clears_back_buffer(): void
public function test_endFrame_only_updates_changed_cells(): void
public function test_invalidate_forces_full_redraw(): void
public function test_handleResize_updates_dimensions(): void
```

### ColorSchemeTest Key Tests

```php
public function test_combine_concatenates_codes(): void
public function test_colorize_wraps_with_reset(): void
public function test_applyTheme_sets_static_properties(): void
public function test_constants_are_valid_ansi_codes(): void
```

### SizeUnitTest Key Tests

```php
public function test_parses_fixed_integer(): void
public function test_parses_percentage_string(): void
public function test_parses_flex_star(): void
public function test_parses_flex_fr(): void
public function test_calculates_fixed_size(): void
public function test_calculates_percentage_of_available(): void
public function test_calculates_flex_with_fraction_unit(): void
public function test_isFixed_returns_correct_value(): void
public function test_isFlexible_returns_correct_value(): void
```

### Testing Patterns

```php
// Pattern for testing key parsing (may need reflection or public method)
public function test_parses_arrow_up(): void
{
    $handler = new KeyboardHandler();
    
    // Simulate escape sequence for UP arrow
    // This may require mocking stdin or using a test double
    $sequence = "\033[A";
    $result = $this->invokeKeyParsing($handler, $sequence);
    
    $this->assertEquals('UP', $result);
}

// Pattern for testing color combining
public function test_combine_concatenates_codes(): void
{
    $result = ColorScheme::combine(
        ColorScheme::BG_BLUE,
        ColorScheme::FG_WHITE,
        ColorScheme::BOLD
    );
    
    $this->assertEquals("\033[44m\033[37m\033[1m", $result);
}

// Pattern for testing size calculations
public function test_calculates_percentage(): void
{
    $unit = SizeUnit::parse('50%');
    
    $this->assertEquals(50, $unit->calculate(100));
    $this->assertEquals(40, $unit->calculate(80));
}

public function test_calculates_flex(): void
{
    $unit = SizeUnit::parse('2fr');
    
    // With fraction unit of 10, 2fr = 20
    $this->assertEquals(20, $unit->calculate(100, 10));
}
```

## Definition of Done

- [ ] KeyboardHandlerTest covers all key mappings
- [ ] RendererTest verifies buffer operations
- [ ] ColorSchemeTest validates ANSI code generation
- [ ] SizeUnitTest covers all size specification formats
- [ ] All tests pass with `vendor/bin/phpunit tests/Unit/Infrastructure`
- [ ] Edge cases tested (boundary values, invalid input)
- [ ] Tests don't require actual terminal (fully mocked)

## Dependencies

**Requires**: Stage 1 (test infrastructure)
**Enables**: Stage 4 (integration tests need working infrastructure)
