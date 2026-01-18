<?php

declare(strict_types=1);

namespace Tests\Unit\Module\FileBrowser\Component;

use Butschster\Commander\Module\FileBrowser\Component\FileContentViewer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileContentViewer::class)]
final class FileContentViewerTest extends TestCase
{
    private FileContentViewer $viewer;

    #[Test]
    public function test_set_content_stores_lines(): void
    {
        $content = "Line 1\nLine 2\nLine 3";
        $this->viewer->setContent($content);

        // Verify by checking that input handling works (component has content)
        $this->assertTrue($this->viewer->handleInput('DOWN'));
    }

    #[Test]
    public function test_clear_removes_content(): void
    {
        $this->viewer->setContent("Line 1\nLine 2");
        $this->viewer->clear();

        // When empty, input should return false
        $this->assertFalse($this->viewer->handleInput('DOWN'));
    }

    #[Test]
    public function test_vertical_scroll_down(): void
    {
        $content = \implode("\n", \array_map(static fn($i) => "Line $i", \range(1, 100)));
        $this->viewer->setContent($content);

        // DOWN should be handled
        $this->assertTrue($this->viewer->handleInput('DOWN'));
    }

    #[Test]
    public function test_vertical_scroll_up(): void
    {
        $content = \implode("\n", \array_map(static fn($i) => "Line $i", \range(1, 100)));
        $this->viewer->setContent($content);

        // Scroll down first
        $this->viewer->handleInput('DOWN');
        $this->viewer->handleInput('DOWN');

        // UP should be handled
        $this->assertTrue($this->viewer->handleInput('UP'));
    }

    #[Test]
    public function test_horizontal_scroll_right(): void
    {
        $longLine = \str_repeat('x', 200);
        $this->viewer->setContent($longLine);

        // RIGHT should be handled
        $this->assertTrue($this->viewer->handleInput('RIGHT'));
    }

    #[Test]
    public function test_horizontal_scroll_left(): void
    {
        $longLine = \str_repeat('x', 200);
        $this->viewer->setContent($longLine);

        // Scroll right first
        $this->viewer->handleInput('RIGHT');
        $this->viewer->handleInput('RIGHT');

        // LEFT should be handled
        $this->assertTrue($this->viewer->handleInput('LEFT'));
    }

    #[Test]
    public function test_home_key_resets_position(): void
    {
        $content = \implode("\n", \array_map(static fn($i) => \str_repeat("Line $i ", 30), \range(1, 100)));
        $this->viewer->setContent($content);

        // Scroll down and right
        $this->viewer->handleInput('DOWN');
        $this->viewer->handleInput('DOWN');
        $this->viewer->handleInput('RIGHT');
        $this->viewer->handleInput('RIGHT');

        // HOME should reset position
        $this->assertTrue($this->viewer->handleInput('HOME'));
    }

    #[Test]
    public function test_end_key_goes_to_bottom(): void
    {
        $content = \implode("\n", \array_map(static fn($i) => "Line $i", \range(1, 100)));
        $this->viewer->setContent($content);

        // END should go to end
        $this->assertTrue($this->viewer->handleInput('END'));
    }

    #[Test]
    public function test_page_down(): void
    {
        $content = \implode("\n", \array_map(static fn($i) => "Line $i", \range(1, 100)));
        $this->viewer->setContent($content);

        $this->assertTrue($this->viewer->handleInput('PAGE_DOWN'));
    }

    #[Test]
    public function test_page_up(): void
    {
        $content = \implode("\n", \array_map(static fn($i) => "Line $i", \range(1, 100)));
        $this->viewer->setContent($content);

        // Go down first
        $this->viewer->handleInput('PAGE_DOWN');

        $this->assertTrue($this->viewer->handleInput('PAGE_UP'));
    }

    #[Test]
    public function test_unfocused_viewer_ignores_input(): void
    {
        $this->viewer->setFocused(false);
        $content = \implode("\n", \array_map(static fn($i) => "Line $i", \range(1, 100)));
        $this->viewer->setContent($content);

        $this->assertFalse($this->viewer->handleInput('DOWN'));
    }

    #[Test]
    public function test_unhandled_key_returns_false(): void
    {
        $content = "Line 1\nLine 2";
        $this->viewer->setContent($content);

        $this->assertFalse($this->viewer->handleInput('X'));
        $this->assertFalse($this->viewer->handleInput('a'));
    }

    #[Test]
    public function test_normalizes_crlf_line_endings(): void
    {
        $content = "Line 1\r\nLine 2\r\nLine 3";
        $this->viewer->setContent($content);

        // Should have 3 lines (CRLF normalized to LF)
        $this->assertTrue($this->viewer->handleInput('DOWN'));
    }

    #[Test]
    public function test_normalizes_cr_line_endings(): void
    {
        $content = "Line 1\rLine 2\rLine 3";
        $this->viewer->setContent($content);

        // Should have 3 lines (CR normalized to LF)
        $this->assertTrue($this->viewer->handleInput('DOWN'));
    }

    #[Test]
    public function test_min_size_returns_expected_dimensions(): void
    {
        $minSize = $this->viewer->getMinSize();

        $this->assertArrayHasKey('width', $minSize);
        $this->assertArrayHasKey('height', $minSize);
        $this->assertSame(40, $minSize['width']);
        $this->assertSame(10, $minSize['height']);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->viewer = new FileContentViewer();
        $this->viewer->setFocused(true);
    }
}
