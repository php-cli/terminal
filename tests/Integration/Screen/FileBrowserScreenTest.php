<?php

declare(strict_types=1);

namespace Tests\Integration\Screen;

use Butschster\Commander\Feature\FileBrowser\Screen\FileBrowserScreen;
use Butschster\Commander\Feature\FileBrowser\Service\FileSystemService;
use Butschster\Commander\UI\Screen\ScreenManager;
use Tests\TerminalTestCase;

final class FileBrowserScreenTest extends TerminalTestCase
{
    private string $testDir;
    private FileSystemService $fileSystem;
    private ScreenManager $screenManager;

    public function testInitialRender(): void
    {
        $this->terminal()->setSize(80, 24);

        $screen = new FileBrowserScreen($this->fileSystem, $this->screenManager, $this->testDir);

        // Just do one render frame without full app loop
        $this->driver->initialize();
        $app = $this->createApp();
        $renderer = $app->getRenderer();

        $renderer->beginFrame();
        $screen->render($renderer, 0, 0);
        $renderer->endFrame();

        $this->assertScreenContains('file1.txt');
    }

    public function testNavigateDown(): void
    {
        $this->markTestSkipped('Requires full app loop - skipping for now');
    }

    public function testNavigateWithPageDown(): void
    {
        $this->markTestSkipped('Requires full app loop - skipping for now');
    }

    public function testHomeKeyJumpsToFirst(): void
    {
        $this->markTestSkipped('Requires full app loop - skipping for now');
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = \sys_get_temp_dir() . '/terminal_test_' . \uniqid();
        \mkdir($this->testDir);

        \file_put_contents($this->testDir . '/file1.txt', 'Content 1');
        \file_put_contents($this->testDir . '/file2.txt', 'Content 2');
        \file_put_contents($this->testDir . '/file3.php', '<?php echo "test";');
        \mkdir($this->testDir . '/subdir');
        \file_put_contents($this->testDir . '/subdir/nested.txt', 'Nested content');

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
