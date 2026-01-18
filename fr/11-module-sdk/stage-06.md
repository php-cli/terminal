# Stage 6: FileBrowser Module Migration

## Overview

Convert the existing `Feature/FileBrowser` to a proper module using the SDK. This serves as the first real-world test of
the module system and provides a template for converting other features.

**Why this stage**: Proves the SDK works with real code. The FileBrowser is a good candidate because it's self-contained
with clear dependencies.

## Files

**CREATE:**

```
src/Module/FileBrowser/
└── FileBrowserModule.php              - Module entry point
```

**MOVE (keep originals during migration):**

```
src/Feature/FileBrowser/Screen/        → src/Module/FileBrowser/Screen/
src/Feature/FileBrowser/Component/     → src/Module/FileBrowser/Component/
src/Feature/FileBrowser/Service/       → src/Module/FileBrowser/Service/
```

**CREATE (Tests):**

```
tests/Integration/Module/
└── FileBrowserModuleTest.php

tests/E2E/Scenario/
└── FileBrowserModuleE2ETest.php
```

## Code References

### Existing FileBrowser Code

- `src/Feature/FileBrowser/Screen/FileBrowserScreen.php:1-250` - Main screen
- `src/Feature/FileBrowser/Screen/FileViewerScreen.php:1-180` - File viewer
- `src/Feature/FileBrowser/Service/FileSystemService.php:1-300` - Core service
- `src/Feature/FileBrowser/Component/*.php` - UI components

### How FileBrowser is Currently Used

```php
// console:44-49 - Current manual wiring
$fileSystem = new FileSystemService();
$registry->register(new FileBrowserScreen(
    $fileSystem, 
    $app->getScreenManager(), 
    getcwd()
));
```

### Screen Metadata

```php
// src/Feature/FileBrowser/Screen/FileBrowserScreen.php:25-32
#[Metadata(
    name: 'file_browser',
    title: 'File Browser',
    description: 'Browse and manage files and directories',
    category: 'files',
    priority: 10,
)]
```

## Implementation Details

### FileBrowserModule

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\FileBrowser;

use Butschster\Commander\Infrastructure\Keyboard\KeyBinding;
use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;
use Butschster\Commander\Module\FileBrowser\Screen\FileBrowserScreen;
use Butschster\Commander\Module\FileBrowser\Screen\FileViewerScreen;
use Butschster\Commander\Module\FileBrowser\Service\FileSystemService;
use Butschster\Commander\SDK\Container\ContainerInterface;
use Butschster\Commander\SDK\Container\ServiceDefinition;
use Butschster\Commander\SDK\Module\ModuleContext;
use Butschster\Commander\SDK\Module\ModuleInterface;
use Butschster\Commander\SDK\Module\ModuleMetadata;
use Butschster\Commander\SDK\Provider\KeyBindingProviderInterface;
use Butschster\Commander\SDK\Provider\MenuProviderInterface;
use Butschster\Commander\SDK\Provider\ScreenProviderInterface;
use Butschster\Commander\SDK\Provider\ServiceProviderInterface;
use Butschster\Commander\UI\Menu\MenuDefinition;
use Butschster\Commander\UI\Menu\ScreenMenuItem;
use Butschster\Commander\UI\Screen\ScreenManager;

/**
 * File Browser Module
 * 
 * Provides file system browsing and viewing capabilities.
 * 
 * Screens:
 * - file_browser: Main dual-panel file browser (MC-style)
 * - file_viewer: Full-screen file content viewer
 * 
 * Services:
 * - FileSystemService: File system operations
 */
final class FileBrowserModule implements
    ModuleInterface,
    ServiceProviderInterface,
    ScreenProviderInterface,
    MenuProviderInterface,
    KeyBindingProviderInterface
{
    /**
     * @param string|null $initialPath Initial directory path (defaults to cwd)
     */
    public function __construct(
        private readonly ?string $initialPath = null,
    ) {}
    
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata(
            name: 'file_browser',
            title: 'File Browser',
            version: '1.0.0',
        );
    }
    
    public function services(): iterable
    {
        yield ServiceDefinition::singleton(
            FileSystemService::class,
            static fn() => new FileSystemService(),
        );
    }
    
    public function screens(ContainerInterface $container): iterable
    {
        $fileSystem = $container->get(FileSystemService::class);
        $screenManager = $container->get(ScreenManager::class);
        
        // File Browser Screen (main screen)
        yield new FileBrowserScreen(
            fileSystem: $fileSystem,
            screenManager: $screenManager,
            initialPath: $this->initialPath ?? \getcwd(),
        );
        
        // File Viewer Screen (opened via Ctrl+R from browser)
        // Note: FileViewerScreen is typically created dynamically when opening a file
        // We register a "template" instance here for the registry
        yield new FileViewerScreen(
            fileSystem: $fileSystem,
            screenManager: $screenManager,
            filePath: '', // Placeholder - actual path set when opened
        );
    }
    
    public function menus(): iterable
    {
        yield new MenuDefinition(
            label: 'Files',
            fkey: KeyCombination::fromString('F1'),
            items: [
                ScreenMenuItem::create('File Browser', 'file_browser', 'b'),
                ScreenMenuItem::create('File Viewer', 'file_viewer', 'v'),
            ],
            priority: 10,
        );
    }
    
    public function keyBindings(): iterable
    {
        yield new KeyBinding(
            combination: KeyCombination::fromString('Ctrl+O'),
            actionId: 'files.open_browser',
            description: 'Open file browser',
            category: 'files',
        );
    }
    
    public function boot(ModuleContext $context): void
    {
        // No additional initialization needed
        // Services are registered via services()
        // Screens are registered via screens()
    }
    
    public function shutdown(): void
    {
        // No cleanup needed
    }
}
```

### Update Namespace in Existing Files

The files in `src/Module/FileBrowser/` need namespace updates:

```php
// Before (in src/Feature/FileBrowser/Screen/FileBrowserScreen.php)
namespace Butschster\Commander\Feature\FileBrowser\Screen;

// After (in src/Module/FileBrowser/Screen/FileBrowserScreen.php)
namespace Butschster\Commander\Module\FileBrowser\Screen;
```

Same for:

- `FileViewerScreen.php`
- `FileSystemService.php`
- All Component files

### Directory Structure After Migration

```
src/Module/FileBrowser/
├── FileBrowserModule.php
├── Screen/
│   ├── FileBrowserScreen.php
│   └── FileViewerScreen.php
├── Component/
│   ├── DirectoryInfoSection.php
│   ├── FileContentViewer.php
│   ├── FileInfoSection.php
│   ├── FileListComponent.php
│   ├── FilePreviewComponent.php
│   └── TextInfoComponent.php
└── Service/
    └── FileSystemService.php
```

## Test Cases

### Integration Tests

```php
// tests/Integration/Module/FileBrowserModuleTest.php

final class FileBrowserModuleTest extends ModuleTestCase
{
    private string $testDir;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testDir = sys_get_temp_dir() . '/fb_module_test_' . uniqid();
        mkdir($this->testDir);
        file_put_contents($this->testDir . '/test.txt', 'Hello World');
        mkdir($this->testDir . '/subdir');
    }
    
    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }
    
    public function test_module_metadata(): void
    {
        $module = new FileBrowserModule();
        $metadata = $module->metadata();
        
        $this->assertSame('file_browser', $metadata->name);
        $this->assertSame('File Browser', $metadata->title);
        $this->assertSame('1.0.0', $metadata->version);
        $this->assertEmpty($metadata->dependencies);
    }
    
    public function test_provides_filesystem_service(): void
    {
        $module = new FileBrowserModule();
        $services = iterator_to_array($module->services());
        
        $this->assertCount(1, $services);
        $this->assertSame(FileSystemService::class, $services[0]->id);
        $this->assertTrue($services[0]->singleton);
    }
    
    public function test_provides_two_screens(): void
    {
        $module = new FileBrowserModule($this->testDir);
        $this->bootModule($module);
        
        $screens = iterator_to_array($module->screens($this->container));
        
        $this->assertCount(2, $screens);
        $this->assertInstanceOf(FileBrowserScreen::class, $screens[0]);
        $this->assertInstanceOf(FileViewerScreen::class, $screens[1]);
    }
    
    public function test_provides_files_menu(): void
    {
        $module = new FileBrowserModule();
        $menus = iterator_to_array($module->menus());
        
        $this->assertCount(1, $menus);
        $this->assertSame('Files', $menus[0]->label);
        $this->assertSame('F1', (string) $menus[0]->fkey);
        $this->assertSame(10, $menus[0]->priority);
    }
    
    public function test_provides_key_bindings(): void
    {
        $module = new FileBrowserModule();
        $bindings = iterator_to_array($module->keyBindings());
        
        $this->assertCount(1, $bindings);
        $this->assertSame('files.open_browser', $bindings[0]->actionId);
    }
    
    public function test_filesystem_service_is_singleton(): void
    {
        $module = new FileBrowserModule();
        $container = $this->bootModule($module);
        
        $first = $container->get(FileSystemService::class);
        $second = $container->get(FileSystemService::class);
        
        $this->assertSame($first, $second);
    }
    
    public function test_screens_receive_correct_dependencies(): void
    {
        $module = new FileBrowserModule($this->testDir);
        $container = $this->bootModule($module);
        
        $screens = iterator_to_array($module->screens($container));
        $browserScreen = $screens[0];
        
        // Verify screen is functional
        $this->assertSame('File Browser', $browserScreen->getTitle());
    }
    
    public function test_initial_path_is_used(): void
    {
        $module = new FileBrowserModule($this->testDir);
        $container = $this->bootModule($module);
        
        $screens = iterator_to_array($module->screens($container));
        $browserScreen = $screens[0];
        
        $this->assertSame($this->testDir, $browserScreen->getCurrentPath());
    }
    
    public function test_defaults_to_cwd_when_no_path(): void
    {
        $module = new FileBrowserModule();
        $container = $this->bootModule($module);
        
        $screens = iterator_to_array($module->screens($container));
        $browserScreen = $screens[0];
        
        $this->assertSame(getcwd(), $browserScreen->getCurrentPath());
    }
}
```

### E2E Tests

```php
// tests/E2E/Scenario/FileBrowserModuleE2ETest.php

final class FileBrowserModuleE2ETest extends ModuleTestCase
{
    private string $testDir;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testDir = sys_get_temp_dir() . '/fb_e2e_' . uniqid();
        mkdir($this->testDir);
        file_put_contents($this->testDir . '/readme.txt', "# Welcome\n\nThis is a test file.");
        file_put_contents($this->testDir . '/data.json', '{"key": "value"}');
        mkdir($this->testDir . '/docs');
        file_put_contents($this->testDir . '/docs/guide.md', '# Guide');
    }
    
    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }
    
    public function test_file_browser_renders_directory_contents(): void
    {
        $this->terminal()->setSize(120, 40);
        
        $app = ApplicationBuilder::create()
            ->withModule(new FileBrowserModule($this->testDir))
            ->withInitialScreen('file_browser')
            ->build();
        
        $this->runBuiltApp($app);
        
        $this->assertScreenContainsAll(['readme.txt', 'data.json', 'docs']);
    }
    
    public function test_f1_menu_shows_files_options(): void
    {
        $this->terminal()->setSize(120, 40);
        
        $this->keys()
            ->fn(1)      // F1 - Open Files menu
            ->frame()
            ->applyTo($this->terminal());
        
        $app = ApplicationBuilder::create()
            ->withModule(new FileBrowserModule($this->testDir))
            ->withInitialScreen('file_browser')
            ->build();
        
        $this->runBuiltApp($app);
        
        $this->assertScreenContains('File Browser');
        $this->assertScreenContains('File Viewer');
    }
    
    public function test_navigation_with_arrow_keys(): void
    {
        $this->terminal()->setSize(120, 40);
        
        $this->keys()
            ->down(2)    // Navigate down in file list
            ->frame()
            ->applyTo($this->terminal());
        
        $app = ApplicationBuilder::create()
            ->withModule(new FileBrowserModule($this->testDir))
            ->withInitialScreen('file_browser')
            ->build();
        
        $this->runBuiltApp($app);
        
        // Files should still be visible
        $this->assertScreenContainsAll(['readme.txt', 'data.json']);
    }
    
    public function test_enter_directory_shows_contents(): void
    {
        $this->terminal()->setSize(120, 40);
        
        // Navigate to 'docs' directory and enter
        $this->keys()
            ->down()     // First is '..'
            ->down()     // 'docs' directory
            ->enter()    // Enter directory
            ->frame()
            ->applyTo($this->terminal());
        
        $app = ApplicationBuilder::create()
            ->withModule(new FileBrowserModule($this->testDir))
            ->withInitialScreen('file_browser')
            ->build();
        
        $this->runBuiltApp($app);
        
        // Should show docs directory contents
        $this->assertScreenContains('guide.md');
    }
    
    public function test_escape_navigates_up(): void
    {
        $this->terminal()->setSize(120, 40);
        
        // Enter docs, then escape to go back
        $this->keys()
            ->down()->down()->enter()  // Enter docs
            ->frame()
            ->escape()                  // Go back
            ->frame()
            ->applyTo($this->terminal());
        
        $app = ApplicationBuilder::create()
            ->withModule(new FileBrowserModule($this->testDir))
            ->withInitialScreen('file_browser')
            ->build();
        
        $this->runBuiltApp($app);
        
        // Should be back in main directory
        $this->assertScreenContainsAll(['readme.txt', 'docs']);
    }
    
    public function test_preview_panel_shows_file_info(): void
    {
        $this->terminal()->setSize(120, 40);
        
        // Select readme.txt
        $this->keys()
            ->down()     // Skip '..'
            ->frame()
            ->applyTo($this->terminal());
        
        $app = ApplicationBuilder::create()
            ->withModule(new FileBrowserModule($this->testDir))
            ->withInitialScreen('file_browser')
            ->build();
        
        $this->runBuiltApp($app);
        
        // Preview panel should show file information
        $this->assertScreenContains('File Information');
    }
    
    public function test_works_with_other_modules(): void
    {
        $this->terminal()->setSize(120, 40);
        
        // Create a simple second module
        $otherModule = new class implements ModuleInterface, ScreenProviderInterface, MenuProviderInterface {
            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata('other', 'Other');
            }
            
            public function screens(ContainerInterface $c): iterable
            {
                return [];
            }
            
            public function menus(): iterable
            {
                yield new MenuDefinition('Other', KeyCombination::fromString('F2'), [], 20);
            }
            
            public function boot(ModuleContext $context): void {}
            public function shutdown(): void {}
        };
        
        $app = ApplicationBuilder::create()
            ->withModule(new FileBrowserModule($this->testDir))
            ->withModule($otherModule)
            ->withInitialScreen('file_browser')
            ->build();
        
        $this->runBuiltApp($app);
        
        // Both module menus should appear
        $this->assertScreenContains('F1 Files');
        $this->assertScreenContains('F2 Other');
    }
}
```

## Migration Checklist

### Step 1: Create Module Directory

```bash
mkdir -p src/Module/FileBrowser
```

### Step 2: Create Module Class

Create `src/Module/FileBrowser/FileBrowserModule.php`

### Step 3: Copy Files (Preserve Originals)

```bash
cp -r src/Feature/FileBrowser/Screen src/Module/FileBrowser/
cp -r src/Feature/FileBrowser/Component src/Module/FileBrowser/
cp -r src/Feature/FileBrowser/Service src/Module/FileBrowser/
```

### Step 4: Update Namespaces

In each copied file, change:

- `Butschster\Commander\Feature\FileBrowser\*`
- To: `Butschster\Commander\Module\FileBrowser\*`

### Step 5: Update Internal Imports

Check for any `use` statements that reference the old namespace.

### Step 6: Run Tests

```bash
vendor/bin/phpunit --filter=FileBrowserModule
```

### Step 7: Verify with Builder

```php
$app = ApplicationBuilder::create()
    ->withModule(new FileBrowserModule())
    ->build();

$app->run();
```

## Definition of Done

- [ ] `FileBrowserModule` class created implementing all providers
- [ ] Files moved to `src/Module/FileBrowser/`
- [ ] Namespaces updated in all files
- [ ] Module provides:
    - [ ] `FileSystemService` as singleton
    - [ ] `FileBrowserScreen` and `FileViewerScreen`
    - [ ] "Files" menu with F1 key
    - [ ] `Ctrl+O` key binding
- [ ] Integration tests verify:
    - [ ] Module metadata is correct
    - [ ] Services are registered
    - [ ] Screens are provided
    - [ ] Menus are provided
- [ ] E2E tests verify:
    - [ ] File browser renders correctly
    - [ ] Navigation works
    - [ ] Menu appears in top bar
    - [ ] Works alongside other modules
- [ ] All existing FileBrowser functionality preserved
- [ ] Static analysis passes

## Dependencies

**Requires**:

- Stage 5 (ApplicationBuilder to test module)

**Enables**:

- Stage 7 (Template for other module conversions)
