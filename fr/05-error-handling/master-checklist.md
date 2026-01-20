# Feature: Error Handling for File Operations

## Overview

Add proper error handling around file system operations in FileBrowser components. Currently, no try-catch blocks exist
around operations that could throw exceptions (permission denied, file not found, etc.), which could crash the entire
application.

## Stage Dependencies

```
Stage 1 (FileSystemService) → Stage 2 (Components) → Stage 3 (User Feedback)
```

## Development Progress

### Stage 1: Add Error Handling to FileSystemService

- [ ] Substep 1.1: Create `FileSystemException` custom exception class
- [ ] Substep 1.2: Add try-catch to `listDirectory()` method
- [ ] Substep 1.3: Add try-catch to `getFileMetadata()` method
- [ ] Substep 1.4: Add try-catch to `readFileContents()` method (if exists)
- [ ] Substep 1.5: Return Result objects or empty arrays with error info

**Notes**:
**Status**: Not Started
**Completed**:

---

### Stage 2: Update Components to Handle Errors Gracefully

- [ ] Substep 2.1: Update `FileListComponent::setDirectory()` to handle errors
- [ ] Substep 2.2: Update `FilePreviewComponent::setFileInfo()` to handle errors
- [ ] Substep 2.3: Update `FileBrowserScreen::handleFileSelect()` to handle errors
- [ ] Substep 2.4: Ensure no uncaught exceptions can crash the TUI

**Notes**:
**Status**: Not Started
**Completed**:

---

### Stage 3: Add User Feedback for Errors

- [ ] Substep 3.1: Create error display mechanism (Alert or status bar message)
- [ ] Substep 3.2: Show user-friendly messages for common errors
- [ ] Substep 3.3: Add logging for debugging (optional PSR-3 logger)
- [ ] Substep 3.4: Allow user to retry or navigate away from errored state

**Notes**:
**Status**: Not Started
**Completed**:

---

## Codebase References

- `src/Feature/FileBrowser/Service/FileSystemService.php` - Main service to protect
- `src/Feature/FileBrowser/Component/FileListComponent.php:45-55` - `setDirectory()` call
- `src/Feature/FileBrowser/Component/FilePreviewComponent.php:40-60` - `setFileInfo()` call
- `src/Feature/FileBrowser/Screen/FileBrowserScreen.php:150-180` - Navigation handlers
- `src/UI/Component/Display/Alert.php` - Can be used for error messages

## Implementation Details

### FileSystemException

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\Feature\FileBrowser\Exception;

final class FileSystemException extends \RuntimeException
{
    public static function cannotReadDirectory(string $path, ?\Throwable $previous = null): self
    {
        return new self("Cannot read directory: {$path}", 0, $previous);
    }
    
    public static function cannotReadFile(string $path, ?\Throwable $previous = null): self
    {
        return new self("Cannot read file: {$path}", 0, $previous);
    }
    
    public static function accessDenied(string $path): self
    {
        return new self("Access denied: {$path}");
    }
    
    public static function pathNotFound(string $path): self
    {
        return new self("Path not found: {$path}");
    }
}
```

### Protected FileSystemService Methods

```php
public function listDirectory(string $path, bool $includeHidden = false): array
{
    if (!\is_dir($path)) {
        throw FileSystemException::pathNotFound($path);
    }
    
    if (!\is_readable($path)) {
        throw FileSystemException::accessDenied($path);
    }
    
    try {
        // ... existing implementation
    } catch (\Throwable $e) {
        throw FileSystemException::cannotReadDirectory($path, $e);
    }
}
```

### Component Error Handling Pattern

```php
// In FileListComponent
public function setDirectory(string $path): void
{
    try {
        $this->items = $this->fileSystem->listDirectory($path, false);
        $this->table->setRows($this->items);
        $this->error = null;
    } catch (FileSystemException $e) {
        $this->items = [];
        $this->table->setRows([]);
        $this->error = $e->getMessage();
    }
}
```

## Common Error Scenarios

1. **Permission denied** - User navigates to restricted directory
2. **Path not found** - Directory deleted while viewing
3. **Symlink loop** - Circular symbolic links
4. **Too many files** - Directory with thousands of entries

## Usage Instructions

⚠️ Keep this checklist updated:

- Mark completed substeps immediately with [x]
- Add notes about deviations or challenges
- Document decisions differing from plan
- Update status when starting/completing stages
