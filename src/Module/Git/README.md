# Git Module

The Git module provides a terminal UI for browsing and managing Git repositories.

## Features

- View repository status (staged, unstaged, untracked, conflicts)
- View file diffs with syntax highlighting
- Browse local and remote branches
- View and compare tags
- Stage/unstage files
- Checkout branches
- Ahead/behind tracking for branches

## Installation

The module is included in Commander by default. To use it with the Module SDK:

```php
use Butschster\Commander\SDK\Builder\ApplicationBuilder;
use Butschster\Commander\Module\Git\GitModule;

$app = ApplicationBuilder::create()
    ->withModule(new GitModule('/path/to/repo'))
    ->withInitialScreen('git')
    ->build();

$app->run();
```

## Screens

### Git Screen (`git`)

Tabbed interface with three sections:

#### Status Tab

View and manage working tree changes:

- **Staged files**: Changes ready to commit
- **Unstaged files**: Modified but not staged
- **Untracked files**: New files not in Git
- **Conflicts**: Files with merge conflicts
- File diff viewer

#### Branches Tab

Browse repository branches:

- Current branch indicator
- Local branches
- Remote tracking branches
- Ahead/behind commit counts
- Last commit info per branch
- Branch checkout

#### Tags Tab

View all repository tags:

- Tag name and commit hash
- Annotated vs lightweight tags
- Tag message
- Tagger and date
- Semantic version sorting

## Keyboard Shortcuts

### Navigation

| Key                 | Action                            |
|---------------------|-----------------------------------|
| `Ctrl+←` / `Ctrl+→` | Switch tabs                       |
| `↑` / `↓`           | Navigate file/branch list         |
| `Tab`               | Switch between list and diff view |
| `Escape`            | Go back                           |

### Status Tab

| Key     | Action                |
|---------|-----------------------|
| `Enter` | View file diff        |
| `S`     | Stage selected file   |
| `U`     | Unstage selected file |
| `A`     | Stage all files       |
| `R`     | Refresh status        |

### Branches Tab

| Key     | Action           |
|---------|------------------|
| `Enter` | Checkout branch  |
| `R`     | Refresh branches |

### Diff Viewer

| Key                     | Action              |
|-------------------------|---------------------|
| `↑` / `↓`               | Scroll line by line |
| `Page Up` / `Page Down` | Scroll by page      |
| `←` / `→`               | Scroll horizontally |

## Menu

The module adds a "Git" menu accessible via `F4`:

- **Repository** (`g`) - Open Git repository view

## Key Bindings

The module registers:

- `Ctrl+G` - Open Git repository (action: `git.open`)

## Services

### GitService

Provides all Git operations:

```php
use Butschster\Commander\Module\Git\Service\GitService;

$git = new GitService('/path/to/repo');

// Check if valid repository
if ($git->isValidRepository()) {
    // Get current branch
    $branch = $git->getCurrentBranch();

    // Get repository status
    $status = $git->getStatus();
    // Returns: [
    //     'staged' => FileStatus[],
    //     'unstaged' => FileStatus[],
    //     'untracked' => FileStatus[],
    //     'conflicts' => FileStatus[],
    // ]

    // Get file diff
    $diff = $git->getFileDiff('src/File.php', staged: false);

    // Get branches
    $branches = $git->getBranches(includeRemote: true);

    // Get tags
    $tags = $git->getTags();

    // Checkout branch
    $git->checkout('feature/new-feature');

    // Stage/unstage files
    $git->stageFile('src/File.php');
    $git->unstageFile('src/File.php');
    $git->stageAll();
    $git->unstageAll();

    // Get commit log
    $log = $git->getLog(limit: 50);
}
```

## Data Types

### FileStatus

Represents a file's Git status:

```php
readonly class FileStatus {
    public const STAGED = 'staged';
    public const UNSTAGED = 'unstaged';
    public const UNTRACKED = 'untracked';
    public const CONFLICT = 'conflict';

    public string $path;
    public string $status;
    public string $indexStatus;      // Index (staging area) status
    public string $workTreeStatus;   // Working tree status
    public ?string $originalPath;    // For renames
}
```

Status codes:

- `M` - Modified
- `A` - Added
- `D` - Deleted
- `R` - Renamed
- `C` - Copied
- `U` - Unmerged (conflict)
- `?` - Untracked

### BranchInfo

Represents a Git branch:

```php
readonly class BranchInfo {
    public string $name;
    public bool $isCurrent;
    public bool $isRemote;
    public ?string $upstream;
    public string $lastCommitHash;
    public string $lastCommitMessage;
    public ?int $aheadCount;   // Commits ahead of upstream
    public ?int $behindCount;  // Commits behind upstream
}
```

### TagInfo

Represents a Git tag:

```php
readonly class TagInfo {
    public string $name;
    public string $commitHash;
    public ?string $message;
    public ?string $taggerName;
    public ?string $taggerDate;
    public bool $isAnnotated;
}
```

## Components

### DiffViewer

Scrollable diff viewer with syntax highlighting:

```php
use Butschster\Commander\Module\Git\Component\DiffViewer;

$viewer = new DiffViewer();
$viewer->setDiff($diffContent);
```

Features:

- Color-coded additions (green) and deletions (red)
- Line numbers
- Horizontal scrolling for long lines
- Hunk headers

## Configuration

The module accepts an optional repository path:

```php
// Use specific repository
new GitModule('/var/www/myproject')

// Use current working directory (default)
new GitModule()
```

## Error Handling

If the path is not a valid Git repository, the screen displays:

- "Not a Git repository" message
- The checked path
- Option to go back (ESC)

## Requirements

- Git must be installed and accessible in PATH
- Read/write access to the repository
