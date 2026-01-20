<?php

declare(strict_types=1);

namespace Tests\Integration;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Tests\TerminalTestCase;

/**
 * Smoke test to verify testing framework works.
 */
final class SmokeTest extends TerminalTestCase
{
    public function testVirtualDriverWorks(): void
    {
        $this->terminal()->setSize(40, 10);
        $this->terminal()->queueInput('DOWN', 'DOWN', 'ENTER');

        $this->assertSame(3, $this->terminal()->getRemainingInputCount());
        $this->assertSame('DOWN', $this->terminal()->readInput());
        $this->assertSame(2, $this->terminal()->getRemainingInputCount());
    }

    public function testKeySequenceBuilder(): void
    {
        $sequence = $this->keys()
            ->down(3)
            ->enter()
            ->escape()
            ->build();

        $this->assertSame(['DOWN', 'DOWN', 'DOWN', 'ENTER', 'ESCAPE'], $sequence);
    }

    public function testScreenCaptureContains(): void
    {
        $this->terminal()->setSize(20, 5);
        $this->terminal()->write("\033[1;1HHello World");

        $capture = $this->capture();

        $this->assertTrue($capture->contains('Hello'));
        $this->assertTrue($capture->contains('World'));
        $this->assertFalse($capture->contains('Goodbye'));
    }

    public function testSimpleScreenRendering(): void
    {
        $screen = new class implements ScreenInterface {
            public function render(Renderer $renderer, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null): void
            {
                $theme = $renderer->getThemeContext();
                $renderer->writeAt($x + 1, $y + 1, 'Test Screen', $theme->getNormalText());
            }

            public function handleInput(string $key): bool
            {
                return false;
            }

            public function onActivate(): void {}

            public function onDeactivate(): void {}

            public function update(): void {}

            public function getTitle(): string
            {
                return 'Test';
            }
        };

        $this->terminal()->setSize(40, 10);
        $this->runApp($screen);

        $this->assertScreenContains('Test Screen');
    }

    public function testKeySequenceApplyTo(): void
    {
        $this->keys()
            ->down(2)
            ->enter()
            ->applyTo($this->terminal());

        $this->assertSame(3, $this->terminal()->getRemainingInputCount());
        $this->assertSame('DOWN', $this->terminal()->readInput());
        $this->assertSame('DOWN', $this->terminal()->readInput());
        $this->assertSame('ENTER', $this->terminal()->readInput());
    }

    public function testScreenCaptureGetLine(): void
    {
        $this->terminal()->setSize(20, 5);
        $this->terminal()->write("\033[1;1HLine One");
        $this->terminal()->write("\033[2;1HLine Two");

        $capture = $this->capture();

        $this->assertSame('Line One', \trim($capture->getLine(0)));
        $this->assertSame('Line Two', \trim($capture->getLine(1)));
    }

    public function testScreenCaptureFindText(): void
    {
        $this->terminal()->setSize(20, 5);
        $this->terminal()->write("\033[2;5HTarget");

        $capture = $this->capture();
        $position = $capture->findText('Target');

        $this->assertNotNull($position);
        $this->assertSame(4, $position['x']);
        $this->assertSame(1, $position['y']);
    }
}
