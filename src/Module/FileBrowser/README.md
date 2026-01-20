# File Browser Module

The File Browser module provides an MC-style (Midnight Commander) dual-panel file system browser with file preview
capabilities.

## Features

- Dual-panel layout (file list + preview)
- Navigate directories and view files
- File preview with syntax highlighting
- File metadata display (size, permissions, dates)
- Full keyboard navigation
- Scrollable file content viewer

## Installation

The module is included in Commander by default. To use it with the Module SDK:

```php
use Butschster\Commander\SDK\Builder\ApplicationBuilder;
use Butschster\Commander\Module\FileBrowser\FileBrowserModule;

$app = ApplicationBuilder::create()
    ->withModule(new FileBrowserModule('/path/to/start'))
    ->withInitialScreen('file_browser')
    ->build();

$app->run();
```

## Screens

### File Browser Screen (`file_browser`)

Main dual-panel file browser with:

- **Left Panel**: File list with name, size, and modification date
- **Right Panel**: File preview or directory information

### File Viewer Screen (`file_viewer`)

Full-screen file content viewer opened via `Ctrl+E` or `Enter` on a file.

## Keyboard Shortcuts

### File Browser

| Key       | Action                     |
|-----------|----------------------------|
| `↑` / `↓` | Navigate files             |
| `Enter`   | Open directory / View file |
| `Tab`     | Switch between panels      |
| `Ctrl+E`  | Open file in viewer        |
| `Ctrl+G`  | Go to initial directory    |
| `Escape`  | Go back / Exit             |

### File Viewer

| Key                     | Action              |
|-------------------------|---------------------|
| `↑` / `↓`               | Scroll line by line |
| `Page Up` / `Page Down` | Scroll by page      |
| `Home` / `End`          | Jump to start/end   |
| `←` / `→`               | Scroll horizontally |
| `Escape`                | Close viewer        |

## Menu

The module adds a "Files" menu accessible via `F1`:

- **File Browser** (`b`) - Open the file browser

## Services

### FileSystemService

Provides file system operations:

```php
use Butschster\Commander\Module\FileBrowser\Service\FileSystemService;

$fileSystem = new FileSystemService();

// List directory contents
$items = $fileSystem->listDirectory('/path/to/dir');

// Get file info
$info = $fileSystem->getFileInfo('/path/to/file');

// Read file contents
$content = $fileSystem->readFile('/path/to/file');
```

## Components

### FileListComponent

Displays a scrollable file list with sorting:

```php
use Butschster\Commander\Module\FileBrowser\Component\FileListComponent;

$fileList = new FileListComponent($fileSystemService);
$fileList->setDirectory('/path/to/dir');
$fileList->onSelect(function(array $item) {
    // Handle file/directory selection
});
```

### FilePreviewComponent

Shows file content preview or directory info:

```php
use Butschster\Commander\Module\FileBrowser\Component\FilePreviewComponent;

$preview = new FilePreviewComponent($fileSystemService);
$preview->setFileInfo('/path/to/file');
```

### FileContentViewer

Scrollable text viewer for file contents with horizontal scrolling:

```php
use Butschster\Commander\Module\FileBrowser\Component\FileContentViewer;

$viewer = new FileContentViewer();
$viewer->setContent($fileContent);
$viewer->handleInput('down'); // Scroll down
```

## Configuration

The module accepts an optional initial path:

```php
// Start in specific directory
new FileBrowserModule('/var/log')

// Start in current working directory (default)
new FileBrowserModule()
```

## Key Bindings

The module registers:

- `Ctrl+O` - Open file browser (action: `files.open_browser`)
