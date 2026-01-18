# Stage 7: Documentation & Cleanup

## Overview

Final stage: update Help screen to auto-generate from registry, remove dead code,
add documentation, and perform final verification of all key bindings.

## Files

**MODIFY:**
- `src/Feature/CommandBrowser/Screen/CommandsScreen.php` - Update help text generation
- Any files with dead code from refactoring

**CREATE:**
- None (documentation in existing files via PHPDoc)

## Code References

- `src/Feature/CommandBrowser/Screen/CommandsScreen.php:700-706` - Current hardcoded help text

## Implementation Details

### Help Screen Auto-Generation

Update `showHelpModal()` in `CommandsScreen` to generate help from registry:

```php
private function showHelpModal(): bool
{
    // Get bindings from registry (need to inject or access)
    $helpText = $this->generateHelpText();
    
    $modal = new Modal(
        'Keyboard Shortcuts',
        $helpText,
        ['OK' => fn() => $this->helpModal = null],
    );
    
    $this->helpModal = $modal;
    return true;
}

private function generateHelpText(): string
{
    $lines = [];
    
    // Global shortcuts
    $lines[] = "GLOBAL SHORTCUTS";
    $lines[] = "================";
    
    $globalBindings = $this->keyBindings->getByCategory('global');
    foreach ($globalBindings as $binding) {
        $lines[] = \sprintf(
            "  %-15s %s",
            (string) $binding->combination,
            $binding->description,
        );
    }
    
    $lines[] = "";
    $lines[] = "MENU SHORTCUTS";
    $lines[] = "==============";
    
    $menuBindings = $this->keyBindings->getByCategory('menu');
    foreach ($menuBindings as $binding) {
        $lines[] = \sprintf(
            "  %-15s %s",
            (string) $binding->combination,
            $binding->description,
        );
    }
    
    $lines[] = "";
    $lines[] = "NAVIGATION";
    $lines[] = "==========";
    $lines[] = "  UP/DOWN       Navigate list";
    $lines[] = "  PAGE UP/DOWN  Page navigation";
    $lines[] = "  HOME/END      Go to start/end";
    $lines[] = "  ENTER         Select item";
    $lines[] = "  TAB           Switch panel";
    $lines[] = "  ESCAPE        Go back";
    
    return \implode("\n", $lines);
}
```

### Inject KeyBindingRegistry into Screens

Option A: Pass via constructor
```php
public function __construct(
    private readonly ScreenManager $screenManager,
    private readonly KeyBindingRegistryInterface $keyBindings,
) {}
```

Option B: Access via ScreenRegistry metadata
```php
// In ScreenRegistry, store reference to keyBindings
// Screens can access via $this->screenManager->getApplication()->getKeyBindings()
```

### Remove Dead Code Checklist

Search and remove:
```bash
# Find any remaining F10 references
grep -rn "'F10'" src/

# Find old global shortcuts references
grep -rn "globalShortcuts" src/

# Find deprecated method calls
grep -rn "registerGlobalShortcut" src/

# Find old DEFAULT_FKEY_MAP references
grep -rn "DEFAULT_FKEY_MAP" src/
```

### PHPDoc Updates

Add comprehensive documentation to new classes:

**Key.php**
```php
/**
 * Keyboard key constants
 * 
 * This enum contains all recognized keyboard keys that can be used
 * in key bindings. Use with KeyCombination for modifier combinations.
 * 
 * @example
 * ```php
 * // Check if key is navigation
 * match ($key) {
 *     Key::UP, Key::DOWN, Key::LEFT, Key::RIGHT => true,
 *     default => false,
 * };
 * ```
 */
enum Key: string
```

**KeyCombination.php**
```php
/**
 * Immutable value object representing a key combination
 * 
 * Combines a base key with optional modifiers (Ctrl, Alt, Shift).
 * Implements Stringable for human-readable display in UI.
 * 
 * @example
 * ```php
 * // Create combinations
 * $quit = KeyCombination::ctrl(Key::Q);     // Ctrl+Q
 * $save = KeyCombination::ctrlShift(Key::S); // Ctrl+Shift+S
 * $help = KeyCombination::key(Key::F1);      // F1
 * 
 * // Check against raw input
 * if ($combo->matches('CTRL_Q')) {
 *     // Handle quit
 * }
 * 
 * // Display in UI
 * echo $quit; // "Ctrl+Q"
 * ```
 */
final readonly class KeyCombination implements \Stringable
```

**KeyBindingRegistry.php**
```php
/**
 * Central registry for all keyboard bindings
 * 
 * Stores KeyBinding instances and provides lookup by raw key input
 * or action ID. Use DefaultKeyBindings::register() to populate with
 * standard application shortcuts.
 * 
 * @example
 * ```php
 * $registry = new KeyBindingRegistry();
 * DefaultKeyBindings::register($registry);
 * 
 * // In input handler
 * $binding = $registry->match('F12');
 * if ($binding?->actionId === 'app.quit') {
 *     $this->quit();
 * }
 * ```
 */
final class KeyBindingRegistry implements KeyBindingRegistryInterface
```

### Final Verification Checklist

Run through all key bindings manually:

1. **Global Keys**
   - [ ] F12 quits application
   - [ ] Ctrl+Q quits application
   - [ ] Ctrl+C quits application (if not in text input)

2. **Menu Keys**
   - [ ] F1 opens Help menu/shows help
   - [ ] F2 opens Tools menu
   - [ ] F3 opens Files menu
   - [ ] F4 opens System menu (if exists)
   - [ ] F5 opens Composer menu (if exists)

3. **Navigation**
   - [ ] UP/DOWN navigates lists
   - [ ] PAGE UP/DOWN pages through lists
   - [ ] HOME/END goes to start/end
   - [ ] TAB switches panels
   - [ ] ESCAPE goes back

4. **Terminal Compatibility**
   - [ ] Test in GNOME Terminal (no F10 conflict)
   - [ ] Test in xterm
   - [ ] Test in VS Code integrated terminal

## Definition of Done

- [ ] Help screen generates shortcuts from KeyBindingRegistry
- [ ] Help text shows F12 for quit (not F10)
- [ ] All dead code removed (grep finds no old patterns)
- [ ] All new classes have comprehensive PHPDoc
- [ ] README.md in Keyboard/ namespace explains usage
- [ ] All key bindings verified working manually
- [ ] No F10 strings remain in codebase (except comments/docs)
- [ ] Application works in GNOME Terminal without conflicts

## Dependencies

**Requires**: All previous stages (1-6)
**Enables**: Feature complete, ready for use
