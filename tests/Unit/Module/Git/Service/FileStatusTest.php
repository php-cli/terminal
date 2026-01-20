<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Git\Service;

use Butschster\Commander\Module\Git\Service\FileStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileStatus::class)]
final class FileStatusTest extends TestCase
{
    public static function statusLabelProvider(): array
    {
        return [
            'modified staged' => ['M', ' ', 'modified'],
            'modified unstaged' => [' ', 'M', 'modified'],
            'added' => ['A', ' ', 'added'],
            'deleted' => ['D', ' ', 'deleted'],
            'renamed' => ['R', ' ', 'renamed'],
            'untracked' => ['?', '?', 'untracked'],
            'conflict' => ['U', 'U', 'conflict'],
            'both modified' => ['M', 'M', 'modified (both)'],
        ];
    }

    #[Test]
    public function it_creates_staged_file_status(): void
    {
        $status = new FileStatus(
            path: 'src/File.php',
            status: FileStatus::STAGED,
            indexStatus: 'M',
            workTreeStatus: ' ',
        );

        $this->assertSame('src/File.php', $status->path);
        $this->assertTrue($status->isStaged());
        $this->assertFalse($status->isUnstaged());
        $this->assertFalse($status->isUntracked());
        $this->assertFalse($status->isConflict());
    }

    #[Test]
    public function it_creates_unstaged_file_status(): void
    {
        $status = new FileStatus(
            path: 'src/File.php',
            status: FileStatus::UNSTAGED,
            indexStatus: ' ',
            workTreeStatus: 'M',
        );

        $this->assertTrue($status->isUnstaged());
        $this->assertFalse($status->isStaged());
    }

    #[Test]
    public function it_creates_untracked_file_status(): void
    {
        $status = new FileStatus(
            path: 'new-file.txt',
            status: FileStatus::UNTRACKED,
            indexStatus: '?',
            workTreeStatus: '?',
        );

        $this->assertTrue($status->isUntracked());
        $this->assertSame('untracked', $status->getStatusLabel());
    }

    #[Test]
    public function it_handles_renamed_files(): void
    {
        $status = new FileStatus(
            path: 'new-name.php',
            status: FileStatus::STAGED,
            indexStatus: 'R',
            workTreeStatus: ' ',
            originalPath: 'old-name.php',
        );

        $this->assertTrue($status->isRenamed());
        $this->assertSame('old-name.php → new-name.php', $status->getDisplayPath());
    }

    #[Test]
    public function it_returns_correct_display_path_for_non_renamed(): void
    {
        $status = new FileStatus(
            path: 'src/File.php',
            status: FileStatus::STAGED,
            indexStatus: 'M',
            workTreeStatus: ' ',
        );

        $this->assertFalse($status->isRenamed());
        $this->assertSame('src/File.php', $status->getDisplayPath());
    }

    #[Test]
    #[DataProvider('statusLabelProvider')]
    public function it_returns_correct_status_label(string $index, string $workTree, string $expected): void
    {
        $status = new FileStatus(
            path: 'file.txt',
            status: FileStatus::STAGED,
            indexStatus: $index,
            workTreeStatus: $workTree,
        );

        $this->assertSame($expected, $status->getStatusLabel());
    }
}
