<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\Git\Service;

/**
 * Handles interaction with Git CLI
 *
 * Provides methods for common git operations:
 * - Status (staged, unstaged, untracked files)
 * - Diff (file and commit diffs)
 * - Branches (list, current, checkout)
 * - Tags (list with details)
 */
final class GitService
{
    private ?array $statusCache = null;
    private ?array $branchesCache = null;
    private ?array $tagsCache = null;

    public function __construct(
        private readonly string $repositoryPath,
    ) {}

    /**
     * Check if the path is a valid git repository
     */
    public function isValidRepository(): bool
    {
        $gitDir = $this->repositoryPath . '/.git';
        return \is_dir($gitDir) || \is_file($gitDir); // .git can be a file for worktrees
    }

    /**
     * Get the repository root path
     */
    public function getRepositoryPath(): string
    {
        return $this->repositoryPath;
    }

    /**
     * Get the current branch name
     */
    public function getCurrentBranch(): ?string
    {
        $output = $this->runGitCommand(['branch', '--show-current']);
        return $output ? \trim($output) : null;
    }

    /**
     * Get the current HEAD commit hash
     */
    public function getHeadCommit(): ?string
    {
        $output = $this->runGitCommand(['rev-parse', 'HEAD']);
        return $output ? \trim($output) : null;
    }

    /**
     * Get repository status
     *
     * @return array{staged: FileStatus[], unstaged: FileStatus[], untracked: FileStatus[], conflicts: FileStatus[]}
     */
    public function getStatus(): array
    {
        if ($this->statusCache !== null) {
            return $this->statusCache;
        }

        $output = $this->runGitCommand(['status', '--porcelain=v1', '-uall']);

        $staged = [];
        $unstaged = [];
        $untracked = [];
        $conflicts = [];

        if ($output === null) {
            return $this->statusCache = \compact('staged', 'unstaged', 'untracked', 'conflicts');
        }

        $lines = \explode("\n", $output);

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $indexStatus = $line[0];
            $workTreeStatus = $line[1];
            $path = \substr($line, 3);

            // Handle renames (format: "R  old -> new")
            $originalPath = null;
            if (\str_contains($path, ' -> ')) {
                [$originalPath, $path] = \explode(' -> ', $path, 2);
            }

            // Determine file status category
            if ($indexStatus === '?' && $workTreeStatus === '?') {
                // Untracked
                $untracked[] = new FileStatus(
                    path: $path,
                    status: FileStatus::UNTRACKED,
                    indexStatus: $indexStatus,
                    workTreeStatus: $workTreeStatus,
                );
            } elseif (\in_array($indexStatus, ['U', 'A', 'D'], true) && \in_array($workTreeStatus, ['U', 'A', 'D'], true)) {
                // Conflict
                $conflicts[] = new FileStatus(
                    path: $path,
                    status: FileStatus::CONFLICT,
                    indexStatus: $indexStatus,
                    workTreeStatus: $workTreeStatus,
                );
            } else {
                // Staged changes
                if ($indexStatus !== ' ' && $indexStatus !== '?') {
                    $staged[] = new FileStatus(
                        path: $path,
                        status: FileStatus::STAGED,
                        indexStatus: $indexStatus,
                        workTreeStatus: ' ',
                        originalPath: $originalPath,
                    );
                }

                // Unstaged changes
                if ($workTreeStatus !== ' ' && $workTreeStatus !== '?') {
                    $unstaged[] = new FileStatus(
                        path: $path,
                        status: FileStatus::UNSTAGED,
                        indexStatus: ' ',
                        workTreeStatus: $workTreeStatus,
                    );
                }
            }
        }

        return $this->statusCache = \compact('staged', 'unstaged', 'untracked', 'conflicts');
    }

    /**
     * Get total count of changed files
     */
    public function getChangedFilesCount(): int
    {
        $status = $this->getStatus();
        return \count($status['staged'])
            + \count($status['unstaged'])
            + \count($status['untracked'])
            + \count($status['conflicts']);
    }

    /**
     * Get diff for a specific file
     *
     * @param bool $staged Whether to get staged diff (--cached)
     */
    public function getFileDiff(string $filePath, bool $staged = false): ?string
    {
        $args = ['diff', '--color=never'];

        if ($staged) {
            $args[] = '--cached';
        }

        $args[] = '--';
        $args[] = $filePath;

        return $this->runGitCommand($args);
    }

    /**
     * Get diff for all changes
     *
     * @param bool $staged Whether to get staged diff (--cached)
     */
    public function getDiff(bool $staged = false): ?string
    {
        $args = ['diff', '--color=never'];

        if ($staged) {
            $args[] = '--cached';
        }

        return $this->runGitCommand($args);
    }

    /**
     * Get all branches (local and remote)
     *
     * @return BranchInfo[]
     */
    public function getBranches(bool $includeRemote = true): array
    {
        if ($this->branchesCache !== null) {
            return $this->branchesCache;
        }

        $args = ['branch', '-v', '--format=%(HEAD)%(refname:short)|%(upstream:short)|%(objectname:short)|%(subject)'];

        if ($includeRemote) {
            $args[] = '-a';
        }

        $output = $this->runGitCommand($args);

        if ($output === null) {
            return [];
        }

        $branches = [];
        $lines = \explode("\n", $output);

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $isCurrent = $line[0] === '*';
            $line = \substr($line, 1); // Remove HEAD indicator

            $parts = \explode('|', $line, 4);
            $name = $parts[0] ?? '';
            $upstream = $parts[1] !== '' ? $parts[1] : null;
            $commitHash = $parts[2] ?? '';
            $commitMessage = $parts[3] ?? '';

            // Skip HEAD reference
            if ($name === 'HEAD' || \str_contains($name, 'HEAD')) {
                continue;
            }

            $isRemote = \str_starts_with($name, 'remotes/');
            if ($isRemote) {
                $name = \substr($name, 8); // Remove 'remotes/' prefix
            }

            // Get ahead/behind counts for local branches with upstream
            $aheadCount = null;
            $behindCount = null;

            if (!$isRemote && $upstream !== null) {
                $countOutput = $this->runGitCommand([
                    'rev-list',
                    '--left-right',
                    '--count',
                    "{$name}...{$upstream}",
                ]);

                if ($countOutput !== null) {
                    $counts = \explode("\t", \trim($countOutput));
                    $aheadCount = (int) ($counts[0] ?? 0);
                    $behindCount = (int) ($counts[1] ?? 0);
                }
            }

            $branches[] = new BranchInfo(
                name: $name,
                isCurrent: $isCurrent,
                isRemote: $isRemote,
                upstream: $upstream,
                lastCommitHash: $commitHash,
                lastCommitMessage: $commitMessage,
                aheadCount: $aheadCount,
                behindCount: $behindCount,
            );
        }

        // Sort: current first, then local, then remote
        \usort($branches, static function (BranchInfo $a, BranchInfo $b): int {
            if ($a->isCurrent !== $b->isCurrent) {
                return $a->isCurrent ? -1 : 1;
            }
            if ($a->isRemote !== $b->isRemote) {
                return $a->isRemote ? 1 : -1;
            }
            return \strcasecmp($a->name, $b->name);
        });

        return $this->branchesCache = $branches;
    }

    /**
     * Get local branches only
     *
     * @return BranchInfo[]
     */
    public function getLocalBranches(): array
    {
        return \array_filter(
            $this->getBranches(includeRemote: false),
            static fn(BranchInfo $b) => !$b->isRemote,
        );
    }

    /**
     * Get all tags
     *
     * @return TagInfo[]
     */
    public function getTags(): array
    {
        if ($this->tagsCache !== null) {
            return $this->tagsCache;
        }

        // Get tags with details
        $output = $this->runGitCommand([
            'tag',
            '-l',
            '--format=%(refname:short)|%(objectname:short)|%(objecttype)|%(subject)|%(taggername)|%(taggerdate:short)',
        ]);

        if ($output === null) {
            return [];
        }

        $tags = [];
        $lines = \explode("\n", $output);

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $parts = \explode('|', $line, 6);
            $name = $parts[0] ?? '';
            $commitHash = $parts[1] ?? '';
            $objectType = $parts[2] ?? '';
            $message = $parts[3] ?? '';
            $taggerName = $parts[4] !== '' ? $parts[4] : null;
            $taggerDate = $parts[5] !== '' ? $parts[5] : null;

            // For annotated tags, get the actual commit hash
            if ($objectType === 'tag') {
                $dereferenced = $this->runGitCommand(['rev-parse', "{$name}^{}"]);
                if ($dereferenced !== null) {
                    $commitHash = \substr(\trim($dereferenced), 0, 7);
                }
            }

            $tags[] = new TagInfo(
                name: $name,
                commitHash: $commitHash,
                message: $message !== '' ? $message : null,
                taggerName: $taggerName,
                taggerDate: $taggerDate,
                isAnnotated: $objectType === 'tag',
            );
        }

        // Sort by name (semantic version aware would be nice, but simple alpha for now)
        \usort($tags, static fn(TagInfo $a, TagInfo $b) => \version_compare($b->name, $a->name));

        return $this->tagsCache = $tags;
    }

    /**
     * Checkout a branch
     */
    public function checkout(string $branchName): bool
    {
        $output = $this->runGitCommand(['checkout', $branchName], captureStderr: true);
        $this->clearCache();
        return $output !== null;
    }

    /**
     * Stage a file
     */
    public function stageFile(string $filePath): bool
    {
        $output = $this->runGitCommand(['add', '--', $filePath]);
        $this->clearCache();
        return $output !== null;
    }

    /**
     * Unstage a file
     */
    public function unstageFile(string $filePath): bool
    {
        $output = $this->runGitCommand(['reset', 'HEAD', '--', $filePath]);
        $this->clearCache();
        return $output !== null;
    }

    /**
     * Stage all changes
     */
    public function stageAll(): bool
    {
        $output = $this->runGitCommand(['add', '-A']);
        $this->clearCache();
        return $output !== null;
    }

    /**
     * Unstage all changes
     */
    public function unstageAll(): bool
    {
        $output = $this->runGitCommand(['reset', 'HEAD']);
        $this->clearCache();
        return $output !== null;
    }

    /**
     * Get log entries
     *
     * @return array<array{hash: string, shortHash: string, author: string, date: string, message: string}>
     */
    public function getLog(int $limit = 50): array
    {
        $output = $this->runGitCommand([
            'log',
            "--max-count={$limit}",
            '--format=%H|%h|%an|%ad|%s',
            '--date=short',
        ]);

        if ($output === null) {
            return [];
        }

        $entries = [];
        $lines = \explode("\n", $output);

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $parts = \explode('|', $line, 5);
            $entries[] = [
                'hash' => $parts[0] ?? '',
                'shortHash' => $parts[1] ?? '',
                'author' => $parts[2] ?? '',
                'date' => $parts[3] ?? '',
                'message' => $parts[4] ?? '',
            ];
        }

        return $entries;
    }

    /**
     * Clear all caches
     */
    public function clearCache(): void
    {
        $this->statusCache = null;
        $this->branchesCache = null;
        $this->tagsCache = null;
    }

    /**
     * Run a git command and return output
     */
    private function runGitCommand(array $args, bool $captureStderr = false): ?string
    {
        $command = \array_merge(['git', '-C', $this->repositoryPath], $args);
        $commandString = \implode(' ', \array_map(escapeshellarg(...), $command));

        if ($captureStderr) {
            $commandString .= ' 2>&1';
        } else {
            $commandString .= ' 2>/dev/null';
        }

        $output = [];
        $returnCode = 0;

        \exec($commandString, $output, $returnCode);

        if ($returnCode !== 0 && !$captureStderr) {
            return null;
        }

        return \implode("\n", $output);
    }
}
