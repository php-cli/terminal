# Composer Manager Module

The Composer Manager module provides a terminal UI for managing Composer packages and dependencies in PHP projects.

## Features

- View all installed packages with version information
- Check for outdated packages
- Security vulnerability audit
- Run Composer scripts
- Package details with autoload, dependencies, and links
- Update packages from the UI
- Search and filter packages

## Installation

The module is included in Commander by default. To use it with the Module SDK:

```php
use Butschster\Commander\SDK\Builder\ApplicationBuilder;
use Butschster\Commander\Module\ComposerManager\ComposerModule;

$app = ApplicationBuilder::create()
    ->withModule(new ComposerModule('/path/to/project'))
    ->withInitialScreen('composer_manager')
    ->build();

$app->run();
```

## Screens

### Composer Manager Screen (`composer_manager`)

Tabbed interface with four sections:

#### Scripts Tab

Run Composer scripts defined in `composer.json`:

- View all available scripts
- Execute scripts with real-time output

#### Installed Packages Tab

Browse all installed packages:

- Package name and version
- Description
- Dev/production dependency indicator
- Detailed package info panel

#### Outdated Packages Tab

View packages with available updates:

- Current version vs. latest version
- Direct update capability
- Batch update support

#### Security Audit Tab

Check for security vulnerabilities:

- `composer audit` integration
- CVE information
- Affected version ranges
- Remediation suggestions

## Keyboard Shortcuts

| Key                 | Action                          |
|---------------------|---------------------------------|
| `Ctrl+←` / `Ctrl+→` | Switch tabs                     |
| `↑` / `↓`           | Navigate package list           |
| `Enter`             | View package details            |
| `Tab`               | Switch between list and details |
| `U`                 | Update selected package         |
| `D`                 | Remove selected package         |
| `/`                 | Search/filter packages          |
| `Escape`            | Go back                         |

## Menu

The module adds a "Composer" menu accessible via `F3`:

- **Package Manager** (`p`) - Open the Composer manager

## Services

### ComposerService

Provides all Composer operations:

```php
use Butschster\Commander\Module\ComposerManager\Service\ComposerService;

$composer = new ComposerService('/path/to/project');

// Get installed packages
$packages = $composer->getInstalledPackages();

// Check for outdated packages
$outdated = $composer->getOutdatedPackages();

// Run security audit
$vulnerabilities = $composer->runSecurityAudit();

// Get available scripts
$scripts = $composer->getScripts();

// Run a script
$output = $composer->runScript('test');

// Update a package
$composer->updatePackage('vendor/package');

// Require a new package
$composer->requirePackage('vendor/package', '^1.0');

// Remove a package
$composer->removePackage('vendor/package');
```

### ComposerBinaryLocator

Finds the Composer binary on the system:

```php
use Butschster\Commander\Module\ComposerManager\Service\ComposerBinaryLocator;

$locator = new ComposerBinaryLocator();
$composerPath = $locator->locate(); // Returns path to composer binary
```

## Components

### LoadingState

Displays loading indicator with animated spinner:

```php
use Butschster\Commander\Module\ComposerManager\Component\LoadingState;

$loading = new LoadingState('Loading packages...');
```

### SearchFilter

Text input for filtering package lists:

```php
use Butschster\Commander\Module\ComposerManager\Component\SearchFilter;

$filter = new SearchFilter('Search packages:');
$filter->onChange(function(string $query) {
    // Filter packages based on query
});
```

### UpdateProgressModal

Modal dialog showing update progress:

```php
use Butschster\Commander\Module\ComposerManager\Component\UpdateProgressModal;

$modal = new UpdateProgressModal('Updating packages...');
$modal->setProgress(50); // 50%
```

### PackageActionMenu

Context menu for package actions:

```php
use Butschster\Commander\Module\ComposerManager\Component\PackageActionMenu;

$menu = new PackageActionMenu($package);
$menu->onAction(function(string $action) {
    // Handle: 'update', 'remove', 'view'
});
```

## Data Types

### PackageInfo

Represents an installed package:

```php
readonly class PackageInfo {
    public string $name;
    public string $version;
    public string $description;
    public bool $isDev;
    public array $authors;
    public array $require;
    public array $autoload;
}
```

### OutdatedPackageInfo

Represents a package with available update:

```php
readonly class OutdatedPackageInfo {
    public string $name;
    public string $currentVersion;
    public string $latestVersion;
    public string $description;
}
```

### SecurityAdvisory

Represents a security vulnerability:

```php
readonly class SecurityAdvisory {
    public string $packageName;
    public string $cve;
    public string $title;
    public string $affectedVersions;
    public string $link;
}
```

## Configuration

The module requires a project path with `composer.json`:

```php
// Use specific project path
new ComposerModule('/var/www/myproject')

// Use current working directory
new ComposerModule(getcwd())
```

## Requirements

- Composer must be installed and accessible in PATH
- Project must have a valid `composer.json`
