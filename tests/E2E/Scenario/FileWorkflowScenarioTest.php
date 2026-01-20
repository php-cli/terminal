<?php

declare(strict_types=1);

namespace Tests\E2E\Scenario;

use Butschster\Commander\Module\FileBrowser\Screen\FileBrowserScreen;
use Butschster\Commander\Module\FileBrowser\Service\FileSystemService;
use Butschster\Commander\UI\Screen\ScreenManager;
use Tests\TerminalTestCase;

/**
 * Full scenario test: Navigate files, test various workflows.
 */
final class FileWorkflowScenarioTest extends TerminalTestCase
{
    private string $testDir;
    private FileSystemService $fileSystem;
    private ScreenManager $screenManager;

    public function testFileBrowserShowsAllFiles(): void
    {
        $this->terminal()->setSize(80, 24);

        $screen = new FileBrowserScreen($this->fileSystem, $this->screenManager, $this->testDir);

        $this->driver->initialize();
        $app = $this->createApp();
        $renderer = $app->getRenderer();

        $renderer->beginFrame();
        $screen->render($renderer, 0, 0);
        $renderer->endFrame();

        $this->assertScreenContainsAll(['readme.txt', 'data.json', 'docs']);
    }

    public function testNavigationPreservesFileList(): void
    {
        $this->markTestSkipped('Requires full app loop');
    }

    public function testEnterDirectoryShowsContents(): void
    {
        $this->markTestSkipped('Requires full app loop');
    }

    public function testMultipleNavigationSteps(): void
    {
        $this->markTestSkipped('Requires full app loop');
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = \sys_get_temp_dir() . '/terminal_scenario_' . \uniqid();
        \mkdir($this->testDir);

        \file_put_contents(
            $this->testDir . '/readme.txt',
            "# Welcome\n\nThis is a test file.\nIt has multiple lines.\n",
        );
        \file_put_contents($this->testDir . '/data.json', '{"key": "value"}');
        \mkdir($this->testDir . '/docs');
        \file_put_contents($this->testDir . '/docs/guide.md', '# Guide\n\nSome documentation.');

        $this->fileSystem = new FileSystemService();
        $this->screenManager = new ScreenManager();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $files = \array_diff(\scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            \is_dir($path) ? $this->removeDirectory($path) : \unlink($path);
        }
        \rmdir($dir);
    }
}
