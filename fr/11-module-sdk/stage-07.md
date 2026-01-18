# Stage 7: Remaining Modules & Cleanup

## Overview

Convert the remaining features (ComposerManager, CommandBrowser) to modules, update the `console` script to use
`ApplicationBuilder`, and clean up deprecated code. This completes the module system migration.

**Why this stage**: Finishes the migration, proves the pattern works for all features, and removes technical debt.

## Files

**CREATE:**

```
src/Module/ComposerManager/
└── ComposerModule.php

src/Module/CommandBrowser/
└── CommandBrowserModule.php
```

**MOVE:**

```
src/Feature/ComposerManager/    → src/Module/ComposerManager/
src/Feature/CommandBrowser/     → src/Module/CommandBrowser/
```

**MODIFY:**

```
console                         - Use ApplicationBuilder
```

**DELETE (after verification):**

```
src/Feature/                    - Entire directory (deprecated)
```

**CREATE (Tests):**

```
tests/Integration/Module/
├── ComposerModuleTest.php
└── CommandBrowserModuleTest.php

tests/E2E/Scenario/
└── MultiModuleE2ETest.php
```

## Code References

### ComposerManager Feature

- `src/Feature/ComposerManager/Screen/ComposerManagerScreen.php:1-400` - Main screen
- `src/Feature/ComposerManager/Service/ComposerService.php:1-200` - Core service
- `src/Feature/ComposerManager/Tab/*.php` - Tab implementations

### CommandBrowser Feature

- `src/Feature/CommandBrowser/Screen/CommandsScreen.php:1-250` - Main screen
- `src/Feature/CommandBrowser/Service/CommandDiscovery.php:1-100` - Command discovery
- `src/Feature/CommandBrowser/Service/CommandExecutor.php:1-80` - Command execution

### Current Console Script

```php
// console:32-65 - Current bootstrap (to be replaced)
$commandDiscovery = new CommandDiscovery($symfonyApp);
$commandExecutor = new CommandExecutor($symfonyApp);
$fileSystem = new FileSystemService();

$registry->register(new CommandsScreen($commandDiscovery, $commandExecutor));
$registry->register(new FileBrowserScreen($fileSystem, $app->getScreenManager(), getcwd()));
$registry->register(new ComposerManagerScreen(new ComposerService(__DIR__)));

$keyBindings = DefaultKeyBindings::createRegistry();
$menuBuilder = new MenuBuilder($registry, $keyBindings);
$app->setMenuSystem($menuBuilder->build());
```

## Implementation Details

### ComposerModule

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\ComposerManager;

use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;
use Butschster\Commander\Module\ComposerManager\Screen\ComposerManagerScreen;
use Butschster\Commander\Module\ComposerManager\Service\ComposerService;
use Butschster\Commander\SDK\Container\ContainerInterface;
use Butschster\Commander\SDK\Container\ServiceDefinition;
use Butschster\Commander\SDK\Module\ModuleContext;
use Butschster\Commander\SDK\Module\ModuleInterface;
use Butschster\Commander\SDK\Module\ModuleMetadata;
use Butschster\Commander\SDK\Provider\MenuProviderInterface;
use Butschster\Commander\SDK\Provider\ScreenProviderInterface;
use Butschster\Commander\SDK\Provider\ServiceProviderInterface;
use Butschster\Commander\UI\Menu\MenuDefinition;
use Butschster\Commander\UI\Menu\ScreenMenuItem;

/**
 * Composer Manager Module
 * 
 * Provides Composer package management UI.
 * 
 * Screens:
 * - composer_manager: Package list, outdated, security audit
 * 
 * Services:
 * - ComposerService: Composer operations
 */
final class ComposerModule implements
    ModuleInterface,
    ServiceProviderInterface,
    ScreenProviderInterface,
    MenuProviderInterface
{
    /**
     * @param string $projectPath Path to project with composer.json
     */
    public function __construct(
        private readonly string $projectPath,
    ) {}
    
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata(
            name: 'composer_manager',
            title: 'Composer Manager',
            version: '1.0.0',
        );
    }
    
    public function services(): iterable
    {
        yield ServiceDefinition::singleton(
            ComposerService::class,
            fn() => new ComposerService($this->projectPath),
        );
    }
    
    public function screens(ContainerInterface $container): iterable
    {
        yield new ComposerManagerScreen(
            $container->get(ComposerService::class),
        );
    }
    
    public function menus(): iterable
    {
        yield new MenuDefinition(
            label: 'Composer',
            fkey: KeyCombination::fromString('F3'),
            items: [
                ScreenMenuItem::create('Package Manager', 'composer_manager', 'p'),
            ],
            priority: 30,
        );
    }
    
    public function boot(ModuleContext $context): void
    {
        // Verify composer.json exists
        $composerFile = $this->projectPath . '/composer.json';
        if (!file_exists($composerFile)) {
            // Could log warning, but don't fail boot
        }
    }
    
    public function shutdown(): void
    {
        // No cleanup needed
    }
}
```

### CommandBrowserModule

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\CommandBrowser;

use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;
use Butschster\Commander\Module\CommandBrowser\Screen\CommandsScreen;
use Butschster\Commander\Module\CommandBrowser\Service\CommandDiscovery;
use Butschster\Commander\Module\CommandBrowser\Service\CommandExecutor;
use Butschster\Commander\SDK\Container\ContainerInterface;
use Butschster\Commander\SDK\Container\ServiceDefinition;
use Butschster\Commander\SDK\Module\ModuleContext;
use Butschster\Commander\SDK\Module\ModuleInterface;
use Butschster\Commander\SDK\Module\ModuleMetadata;
use Butschster\Commander\SDK\Provider\MenuProviderInterface;
use Butschster\Commander\SDK\Provider\ScreenProviderInterface;
use Butschster\Commander\SDK\Provider\ServiceProviderInterface;
use Butschster\Commander\UI\Menu\MenuDefinition;
use Butschster\Commander\UI\Menu\ScreenMenuItem;
use Symfony\Component\Console\Application as SymfonyApplication;

/**
 * Command Browser Module
 * 
 * Provides UI for browsing and executing Symfony Console commands.
 * 
 * Screens:
 * - command_browser: Command list and execution
 * 
 * Services:
 * - CommandDiscovery: Finds available commands
 * - CommandExecutor: Executes commands
 */
final class CommandBrowserModule implements
    ModuleInterface,
    ServiceProviderInterface,
    ScreenProviderInterface,
    MenuProviderInterface
{
    public function __construct(
        private readonly SymfonyApplication $symfonyApp,
    ) {}
    
    public function metadata(): ModuleMetadata
    {
        return new ModuleMetadata(
            name: 'command_browser',
            title: 'Command Browser',
            version: '1.0.0',
        );
    }
    
    public function services(): iterable
    {
        yield ServiceDefinition::singleton(
            CommandDiscovery::class,
            fn() => new CommandDiscovery($this->symfonyApp),
        );
        
        yield ServiceDefinition::singleton(
            CommandExecutor::class,
            fn() => new CommandExecutor($this->symfonyApp),
        );
    }
    
    public function screens(ContainerInterface $container): iterable
    {
        yield new CommandsScreen(
            $container->get(CommandDiscovery::class),
            $container->get(CommandExecutor::class),
        );
    }
    
    public function menus(): iterable
    {
        yield new MenuDefinition(
            label: 'Commands',
            fkey: KeyCombination::fromString('F2'),
            items: [
                ScreenMenuItem::create('Command Browser', 'command_browser', 'c'),
            ],
            priority: 20,
        );
    }
    
    public function boot(ModuleContext $context): void
    {
        // No initialization needed
    }
    
    public function shutdown(): void
    {
        // No cleanup needed
    }
}
```

### Updated Console Script

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Butschster\Commander\SDK\Builder\ApplicationBuilder;
use Butschster\Commander\Module\FileBrowser\FileBrowserModule;
use Butschster\Commander\Module\ComposerManager\ComposerModule;
use Butschster\Commander\Module\CommandBrowser\CommandBrowserModule;
use Butschster\Commander\UI\Theme\MidnightTheme;
use Symfony\Component\Console\Application as SymfonyApplication;

// Create Symfony Console Application (for command browser)
$symfonyApp = new SymfonyApplication('My Console Application', '1.0.0');

try {
    $app = ApplicationBuilder::create()
        // Register modules
        ->withModule(new CommandBrowserModule($symfonyApp))
        ->withModule(new FileBrowserModule(getcwd()))
        ->withModule(new ComposerModule(__DIR__))
        
        // Configuration
        ->withTheme(new MidnightTheme())
        ->withFps(30)
        
        // Start with command browser
        ->withInitialScreen('command_browser')
        
        ->build();
    
    $app->run();
} catch (\Throwable $e) {
    // Fallback error display
    echo "\033[31mError: " . $e->getMessage() . "\033[0m\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
```

## Test Cases

### ComposerModule Tests

```php
// tests/Integration/Module/ComposerModuleTest.php

final class ComposerModuleTest extends ModuleTestCase
{
    private string $testDir;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testDir = sys_get_temp_dir() . '/composer_test_' . uniqid();
        mkdir($this->testDir);
        
        // Create minimal composer.json
        file_put_contents($this->testDir . '/composer.json', json_encode([
            'name' => 'test/project',
            'require' => ['php' => '^8.3'],
        ]));
    }
    
    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }
    
    public function test_module_metadata(): void
    {
        $module = new ComposerModule($this->testDir);
        $metadata = $module->metadata();
        
        $this->assertSame('composer_manager', $metadata->name);
        $this->assertSame('Composer Manager', $metadata->title);
    }
    
    public function test_provides_composer_service(): void
    {
        $module = new ComposerModule($this->testDir);
        $services = iterator_to_array($module->services());
        
        $this->assertCount(1, $services);
        $this->assertSame(ComposerService::class, $services[0]->id);
    }
    
    public function test_provides_composer_manager_screen(): void
    {
        $module = new ComposerModule($this->testDir);
        $this->bootModule($module);
        
        $screens = iterator_to_array($module->screens($this->container));
        
        $this->assertCount(1, $screens);
        $this->assertInstanceOf(ComposerManagerScreen::class, $screens[0]);
    }
    
    public function test_provides_composer_menu(): void
    {
        $module = new ComposerModule($this->testDir);
        $menus = iterator_to_array($module->menus());
        
        $this->assertCount(1, $menus);
        $this->assertSame('Composer', $menus[0]->label);
        $this->assertSame('F3', (string) $menus[0]->fkey);
    }
}
```

### CommandBrowserModule Tests

```php
// tests/Integration/Module/CommandBrowserModuleTest.php

final class CommandBrowserModuleTest extends ModuleTestCase
{
    private SymfonyApplication $symfonyApp;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->symfonyApp = new SymfonyApplication('Test', '1.0.0');
    }
    
    public function test_module_metadata(): void
    {
        $module = new CommandBrowserModule($this->symfonyApp);
        $metadata = $module->metadata();
        
        $this->assertSame('command_browser', $metadata->name);
        $this->assertSame('Command Browser', $metadata->title);
    }
    
    public function test_provides_command_services(): void
    {
        $module = new CommandBrowserModule($this->symfonyApp);
        $services = iterator_to_array($module->services());
        
        $this->assertCount(2, $services);
        
        $ids = array_map(fn($s) => $s->id, $services);
        $this->assertContains(CommandDiscovery::class, $ids);
        $this->assertContains(CommandExecutor::class, $ids);
    }
    
    public function test_provides_commands_screen(): void
    {
        $module = new CommandBrowserModule($this->symfonyApp);
        $this->bootModule($module);
        
        $screens = iterator_to_array($module->screens($this->container));
        
        $this->assertCount(1, $screens);
        $this->assertInstanceOf(CommandsScreen::class, $screens[0]);
    }
    
    public function test_provides_commands_menu(): void
    {
        $module = new CommandBrowserModule($this->symfonyApp);
        $menus = iterator_to_array($module->menus());
        
        $this->assertCount(1, $menus);
        $this->assertSame('Commands', $menus[0]->label);
        $this->assertSame('F2', (string) $menus[0]->fkey);
    }
}
```

### Multi-Module E2E Tests

```php
// tests/E2E/Scenario/MultiModuleE2ETest.php

final class MultiModuleE2ETest extends ModuleTestCase
{
    private string $testDir;
    private SymfonyApplication $symfonyApp;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testDir = sys_get_temp_dir() . '/multi_e2e_' . uniqid();
        mkdir($this->testDir);
        file_put_contents($this->testDir . '/test.txt', 'content');
        file_put_contents($this->testDir . '/composer.json', '{"name":"test/app"}');
        
        $this->symfonyApp = new SymfonyApplication('Test', '1.0.0');
    }
    
    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }
    
    public function test_all_three_modules_work_together(): void
    {
        $this->terminal()->setSize(120, 40);
        
        $app = ApplicationBuilder::create()
            ->withModule(new CommandBrowserModule($this->symfonyApp))
            ->withModule(new FileBrowserModule($this->testDir))
            ->withModule(new ComposerModule($this->testDir))
            ->withInitialScreen('command_browser')
            ->build();
        
        $this->runBuiltApp($app);
        
        // All menus should be visible
        $this->assertScreenContains('F1 Files');
        $this->assertScreenContains('F2 Commands');
        $this->assertScreenContains('F3 Composer');
        $this->assertScreenContains('F12 Quit');
    }
    
    public function test_menus_sorted_by_priority(): void
    {
        $this->terminal()->setSize(120, 40);
        
        $app = ApplicationBuilder::create()
            ->withModule(new FileBrowserModule($this->testDir))     // priority 10
            ->withModule(new CommandBrowserModule($this->symfonyApp)) // priority 20
            ->withModule(new ComposerModule($this->testDir))         // priority 30
            ->build();
        
        $this->runBuiltApp($app);
        
        // Capture screen and verify order
        $capture = $this->capture();
        $line0 = $capture->getLine(0);
        
        // Files (F1) should come before Commands (F2)
        $filesPos = strpos($line0, 'Files');
        $commandsPos = strpos($line0, 'Commands');
        $composerPos = strpos($line0, 'Composer');
        
        $this->assertLessThan($commandsPos, $filesPos);
        $this->assertLessThan($composerPos, $commandsPos);
    }
    
    public function test_switch_between_modules_with_f_keys(): void
    {
        $this->terminal()->setSize(120, 40);
        
        $this->keys()
            ->fn(1)      // F1 - Files menu
            ->frame()
            ->enter()    // Select File Browser
            ->frame()
            ->applyTo($this->terminal());
        
        $app = ApplicationBuilder::create()
            ->withModule(new CommandBrowserModule($this->symfonyApp))
            ->withModule(new FileBrowserModule($this->testDir))
            ->withInitialScreen('command_browser')
            ->build();
        
        $this->runBuiltApp($app);
        
        // Should show file browser content
        $this->assertScreenContains('test.txt');
    }
    
    public function test_modules_shutdown_properly(): void
    {
        $shutdownCalled = false;
        
        $trackingModule = new class($shutdownCalled) implements ModuleInterface {
            public function __construct(private bool &$flag) {}
            
            public function metadata(): ModuleMetadata
            {
                return new ModuleMetadata('tracking', 'Tracking');
            }
            
            public function boot(ModuleContext $context): void {}
            
            public function shutdown(): void
            {
                $this->flag = true;
            }
        };
        
        $app = ApplicationBuilder::create()
            ->withModule($trackingModule)
            ->build();
        
        // Simulate app run and shutdown
        $app->getModuleRegistry()->shutdown();
        
        $this->assertTrue($shutdownCalled);
    }
    
    public function test_initial_screen_selection(): void
    {
        $this->terminal()->setSize(120, 40);
        
        // Start with Composer screen
        $app = ApplicationBuilder::create()
            ->withModule(new FileBrowserModule($this->testDir))
            ->withModule(new ComposerModule($this->testDir))
            ->withInitialScreen('composer_manager')
            ->build();
        
        $this->runBuiltApp($app);
        
        // Should show composer screen
        $this->assertScreenContains('Installed');  // Tab in composer screen
    }
}
```

## Migration Checklist

### Phase 1: Create Modules (Parallel Work)

1. **ComposerModule**
    - [ ] Create `src/Module/ComposerManager/ComposerModule.php`
    - [ ] Copy files from `Feature/ComposerManager`
    - [ ] Update namespaces
    - [ ] Write tests

2. **CommandBrowserModule**
    - [ ] Create `src/Module/CommandBrowser/CommandBrowserModule.php`
    - [ ] Copy files from `Feature/CommandBrowser`
    - [ ] Update namespaces
    - [ ] Write tests

### Phase 2: Update Console Script

- [ ] Replace manual wiring with `ApplicationBuilder`
- [ ] Test all functionality works
- [ ] Verify menus appear correctly
- [ ] Verify navigation works

### Phase 3: Verification

- [ ] Run full test suite
- [ ] Manual testing of each feature
- [ ] Check for any missed imports/namespaces

### Phase 4: Cleanup

- [ ] Add deprecation notice to `Feature/` directory
- [ ] Update documentation
- [ ] Remove `Feature/` directory (after release cycle)

## Definition of Done

- [ ] `ComposerModule` created and tested
- [ ] `CommandBrowserModule` created and tested
- [ ] Both modules provide:
    - [ ] Correct metadata
    - [ ] Services as singletons
    - [ ] Screens via provider
    - [ ] Menu entries with F-keys
- [ ] `console` script updated to use `ApplicationBuilder`
- [ ] All original functionality preserved:
    - [ ] Command browser works
    - [ ] File browser works
    - [ ] Composer manager works
    - [ ] Menu navigation works
    - [ ] F-key shortcuts work
- [ ] E2E tests verify multi-module scenario
- [ ] All tests pass: `vendor/bin/phpunit`
- [ ] Static analysis passes: `vendor/bin/phpstan`
- [ ] `Feature/` directory removed or deprecated

## Dependencies

**Requires**:

- Stage 5 (ApplicationBuilder)
- Stage 6 (FileBrowserModule as template)

**Enables**:

- Future external modules
- Clean package distribution
