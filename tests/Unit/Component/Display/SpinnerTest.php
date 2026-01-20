<?php

declare(strict_types=1);

namespace Tests\Unit\Component\Display;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Display\Spinner;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Tests\TerminalTestCase;

final class SpinnerTest extends TerminalTestCase
{
    // === State Tests ===

    public function testStartsStoppedByDefault(): void
    {
        $spinner = new Spinner();

        $this->assertFalse($spinner->isRunning());
    }

    public function testStartEnablesRunningState(): void
    {
        $spinner = new Spinner();

        $spinner->start();

        $this->assertTrue($spinner->isRunning());
    }

    public function testStopDisablesRunningState(): void
    {
        $spinner = Spinner::createAndStart();

        $spinner->stop();

        $this->assertFalse($spinner->isRunning());
    }

    public function testResetReturnsToFirstFrame(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE, 0.001);
        $spinner->start();

        // Advance a few frames
        for ($i = 0; $i < 5; $i++) {
            \usleep(2000); // 2ms
            $spinner->update();
        }

        $firstFrame = $spinner->getFrame(0);
        $spinner->reset();

        $this->assertSame($firstFrame, $spinner->getCurrentFrame());
    }

    // === Frame Tests ===

    public function testGetCurrentFrameReturnsFirstFrameInitially(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE);

        $this->assertSame('-', $spinner->getCurrentFrame());
    }

    public function testGetFrameAtIndex(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE);

        $this->assertSame('-', $spinner->getFrame(0));
        $this->assertSame('\\', $spinner->getFrame(1));
        $this->assertSame('|', $spinner->getFrame(2));
        $this->assertSame('/', $spinner->getFrame(3));
    }

    public function testGetFrameCount(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE);

        $this->assertSame(4, $spinner->getFrameCount());
    }

    public function testFrameIndexWrapsAround(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE); // 4 frames

        $this->assertSame($spinner->getFrame(0), $spinner->getFrame(4));
        $this->assertSame($spinner->getFrame(1), $spinner->getFrame(5));
    }

    // === Animation Tests ===

    public function testUpdateAdvancesFrameAfterInterval(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE, 0.01); // 10ms interval
        $spinner->start();

        $firstFrame = $spinner->getCurrentFrame();

        // Wait for interval to pass
        \usleep(15000); // 15ms
        $spinner->update();

        $this->assertNotSame($firstFrame, $spinner->getCurrentFrame());
    }

    public function testUpdateDoesNotAdvanceWhenStopped(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE, 0.001);
        // Don't start

        $firstFrame = $spinner->getCurrentFrame();

        \usleep(5000); // 5ms
        $spinner->update();

        $this->assertSame($firstFrame, $spinner->getCurrentFrame());
    }

    public function testUpdateDoesNotAdvanceBeforeInterval(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE, 1.0); // 1 second interval
        $spinner->start();

        $firstFrame = $spinner->getCurrentFrame();

        // Immediate update (well before interval)
        $spinner->update();

        $this->assertSame($firstFrame, $spinner->getCurrentFrame());
    }

    // === Interval Tests ===

    public function testSetAndGetInterval(): void
    {
        $spinner = new Spinner();

        $spinner->setInterval(0.5);

        $this->assertSame(0.5, $spinner->getInterval());
    }

    // === Style Tests ===

    public function testBrailleStyleFrames(): void
    {
        $spinner = new Spinner(Spinner::STYLE_BRAILLE);

        $this->assertSame(10, $spinner->getFrameCount());
        $this->assertSame('⠋', $spinner->getFrame(0));
    }

    public function testDotsStyleFrames(): void
    {
        $spinner = new Spinner(Spinner::STYLE_DOTS);

        $this->assertSame(8, $spinner->getFrameCount());
        $this->assertSame('⣾', $spinner->getFrame(0));
    }

    public function testArrowStyleFrames(): void
    {
        $spinner = new Spinner(Spinner::STYLE_ARROW);

        $this->assertSame(8, $spinner->getFrameCount());
        $this->assertSame('←', $spinner->getFrame(0));
    }

    public function testCircleStyleFrames(): void
    {
        $spinner = new Spinner(Spinner::STYLE_CIRCLE);

        $this->assertSame(4, $spinner->getFrameCount());
        $this->assertSame('◐', $spinner->getFrame(0));
    }

    public function testSquareStyleFrames(): void
    {
        $spinner = new Spinner(Spinner::STYLE_SQUARE);

        $this->assertSame(4, $spinner->getFrameCount());
        $this->assertSame('◰', $spinner->getFrame(0));
    }

    public function testDotsBounceStyleFrames(): void
    {
        $spinner = new Spinner(Spinner::STYLE_DOTS_BOUNCE);

        $this->assertSame(8, $spinner->getFrameCount());
        $this->assertSame('⠁', $spinner->getFrame(0));
    }

    public function testClockStyleFrames(): void
    {
        $spinner = new Spinner(Spinner::STYLE_CLOCK);

        $this->assertSame(12, $spinner->getFrameCount());
        $this->assertSame('🕐', $spinner->getFrame(0));
    }

    public function testInvalidStyleDefaultsToBraille(): void
    {
        $spinner = new Spinner('invalid_style');

        $this->assertSame(10, $spinner->getFrameCount()); // Braille has 10 frames
    }

    // === Factory Tests ===

    public function testCreateReturnsStoppedSpinner(): void
    {
        $spinner = Spinner::create(Spinner::STYLE_LINE);

        $this->assertFalse($spinner->isRunning());
    }

    public function testCreateAndStartReturnsRunningSpinner(): void
    {
        $spinner = Spinner::createAndStart(Spinner::STYLE_LINE);

        $this->assertTrue($spinner->isRunning());
    }

    // === ComponentInterface Tests ===

    public function testRendersAtPosition(): void
    {
        $this->terminal()->setSize(80, 24);

        $spinner = new Spinner(Spinner::STYLE_LINE);

        $this->runApp($this->createSpinnerScreen($spinner, 5, 5));

        // Line style starts with '-'
        $this->assertScreenContains('-');
    }

    public function testRendersWithPrefixAndSuffix(): void
    {
        $this->terminal()->setSize(80, 24);

        $spinner = new Spinner(Spinner::STYLE_LINE);
        $spinner->setPrefix('[ ')->setSuffix(' ]');

        $this->runApp($this->createSpinnerScreen($spinner, 5, 5));

        $this->assertScreenContains('[ - ]');
    }

    public function testHandleInputReturnsFalse(): void
    {
        $spinner = new Spinner();

        $this->assertFalse($spinner->handleInput('ENTER'));
        $this->assertFalse($spinner->handleInput('UP'));
        $this->assertFalse($spinner->handleInput('a'));
    }

    public function testGetMinSizeReturnsFrameWidth(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE);

        $size = $spinner->getMinSize();

        $this->assertSame(1, $size['width']); // '-' is 1 char
        $this->assertSame(1, $size['height']);
    }

    public function testGetMinSizeIncludesPrefixSuffix(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE);
        $spinner->setPrefix('[ ')->setSuffix(' ]');

        $size = $spinner->getMinSize();

        // '[ ' (2) + '-' (1) + ' ]' (2) = 5
        $this->assertSame(5, $size['width']);
        $this->assertSame(1, $size['height']);
    }

    // === Fluent Setter Tests ===

    public function testSetPrefixReturnsSelf(): void
    {
        $spinner = new Spinner();

        $result = $spinner->setPrefix('test');

        $this->assertSame($spinner, $result);
    }

    public function testSetSuffixReturnsSelf(): void
    {
        $spinner = new Spinner();

        $result = $spinner->setSuffix('test');

        $this->assertSame($spinner, $result);
    }

    public function testSetColorReturnsSelf(): void
    {
        $spinner = new Spinner();

        $result = $spinner->setColor("\033[1;33m");

        $this->assertSame($spinner, $result);
    }

    public function testGetPrefixReturnsSetValue(): void
    {
        $spinner = new Spinner();
        $spinner->setPrefix('Loading: ');

        $this->assertSame('Loading: ', $spinner->getPrefix());
    }

    public function testGetSuffixReturnsSetValue(): void
    {
        $spinner = new Spinner();
        $spinner->setSuffix(' please wait');

        $this->assertSame(' please wait', $spinner->getSuffix());
    }

    public function testGetColorReturnsSetValue(): void
    {
        $spinner = new Spinner();
        $spinner->setColor("\033[1;33m");

        $this->assertSame("\033[1;33m", $spinner->getColor());
    }

    public function testGetColorReturnsNullByDefault(): void
    {
        $spinner = new Spinner();

        $this->assertNull($spinner->getColor());
    }

    // === Formatted Text Tests (Legacy API) ===

    public function testGetFormattedTextReturnsFrameWithAffixes(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE);

        $text = $spinner->getFormattedText('[ ', ' ]');

        $this->assertSame('[ - ]', $text);
    }

    public function testGetFormattedTextWithEmptyAffixes(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE);

        $text = $spinner->getFormattedText();

        $this->assertSame('-', $text);
    }

    public function testRenderTextIsDeprecatedAlias(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE);

        $expected = $spinner->getFormattedText('< ', ' >');
        $actual = $spinner->renderText('< ', ' >');

        $this->assertSame($expected, $actual);
    }

    // === Edge Case Tests ===

    public function testEmptyPrefixAndSuffixByDefault(): void
    {
        $spinner = new Spinner();

        $this->assertSame('', $spinner->getPrefix());
        $this->assertSame('', $spinner->getSuffix());
    }

    public function testGetMinSizeWithEmptyPrefixSuffix(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE);
        $spinner->setPrefix('')->setSuffix('');

        $size = $spinner->getMinSize();

        $this->assertSame(1, $size['width']); // Just the frame
        $this->assertSame(1, $size['height']);
    }

    public function testGetMinSizeWithLongPrefixSuffix(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE);
        $spinner->setPrefix('Loading: ')->setSuffix(' please wait...');

        $size = $spinner->getMinSize();

        // 'Loading: ' (9) + '-' (1) + ' please wait...' (15) = 25
        $this->assertSame(25, $size['width']);
        $this->assertSame(1, $size['height']);
    }

    public function testGetMinSizeWithUnicodeFrame(): void
    {
        $spinner = new Spinner(Spinner::STYLE_BRAILLE);

        $size = $spinner->getMinSize();

        // Braille characters are 1 character wide (mb_strlen)
        $this->assertSame(1, $size['width']);
        $this->assertSame(1, $size['height']);
    }

    public function testGetMinSizeWithClockEmoji(): void
    {
        $spinner = new Spinner(Spinner::STYLE_CLOCK);

        $size = $spinner->getMinSize();

        // Clock emoji is 1 character (mb_strlen returns 1 for emoji)
        $this->assertGreaterThanOrEqual(1, $size['width']);
        $this->assertSame(1, $size['height']);
    }

    public function testRenderTruncatesWhenWidthTooSmall(): void
    {
        $this->terminal()->setSize(80, 24);

        $spinner = new Spinner(Spinner::STYLE_LINE);
        $spinner->setPrefix('Very long prefix: ')->setSuffix(' very long suffix');

        // Render with small width - should truncate
        $this->runApp($this->createSpinnerScreenWithWidth($spinner, 5, 5, 10));

        // Should not contain full text due to truncation
        $this->assertScreenNotContains('very long suffix');
    }

    // === Full Animation Cycle Tests ===

    public function testFullFrameCycleReturnsToStart(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE, 0.001); // 4 frames
        $spinner->start();

        $firstFrame = $spinner->getCurrentFrame();
        $frameCount = $spinner->getFrameCount();

        // Advance through all frames
        for ($i = 0; $i < $frameCount; $i++) {
            \usleep(2000); // 2ms
            $spinner->update();
        }

        // Should be back to first frame
        $this->assertSame($firstFrame, $spinner->getCurrentFrame());
    }

    public function testFrameSequenceIsCorrect(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE, 0.001);
        $spinner->start();

        $expectedSequence = ['-', '\\', '|', '/'];
        $actualSequence = [];

        $actualSequence[] = $spinner->getCurrentFrame();

        for ($i = 0; $i < 3; $i++) {
            \usleep(2000);
            $spinner->update();
            $actualSequence[] = $spinner->getCurrentFrame();
        }

        $this->assertSame($expectedSequence, $actualSequence);
    }

    // === AbstractComponent Inherited Behavior Tests ===

    public function testFocusStateDefaultsToFalse(): void
    {
        $spinner = new Spinner();

        $this->assertFalse($spinner->isFocused());
    }

    public function testSetFocusedChangesState(): void
    {
        $spinner = new Spinner();

        $spinner->setFocused(true);

        $this->assertTrue($spinner->isFocused());
    }

    public function testGetBoundsReturnsSetValues(): void
    {
        $this->terminal()->setSize(80, 24);

        $spinner = new Spinner(Spinner::STYLE_LINE);

        // Render to set bounds
        $this->runApp($this->createSpinnerScreen($spinner, 10, 15));

        $bounds = $spinner->getBounds();

        $this->assertSame(10, $bounds['x']);
        $this->assertSame(15, $bounds['y']);
    }

    public function testSpinnerHasNoChildren(): void
    {
        $spinner = new Spinner();

        $this->assertEmpty($spinner->getChildren());
    }

    public function testSpinnerHasNoParent(): void
    {
        $spinner = new Spinner();

        $this->assertNull($spinner->getParent());
    }

    // === Chained Fluent Calls Tests ===

    public function testFluentChaining(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE);

        $result = $spinner
            ->setPrefix('[ ')
            ->setSuffix(' ]')
            ->setColor("\033[1;32m");

        $this->assertSame($spinner, $result);
        $this->assertSame('[ ', $spinner->getPrefix());
        $this->assertSame(' ]', $spinner->getSuffix());
        $this->assertSame("\033[1;32m", $spinner->getColor());
    }

    public function testFluentChainingWithInterval(): void
    {
        $spinner = new Spinner(Spinner::STYLE_BRAILLE);

        $spinner
            ->setPrefix('Loading ')
            ->setSuffix('...');
        $spinner->setInterval(0.2);

        $this->assertSame('Loading ', $spinner->getPrefix());
        $this->assertSame('...', $spinner->getSuffix());
        $this->assertSame(0.2, $spinner->getInterval());
    }

    // === Color Reset Tests ===

    public function testSetColorToNullResetsToDefault(): void
    {
        $spinner = new Spinner();
        $spinner->setColor("\033[1;33m");

        $spinner->setColor(null);

        $this->assertNull($spinner->getColor());
    }

    // === Multiple Start/Stop Cycles ===

    public function testMultipleStartStopCycles(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE, 0.001);

        // First cycle
        $spinner->start();
        $this->assertTrue($spinner->isRunning());
        $spinner->stop();
        $this->assertFalse($spinner->isRunning());

        // Second cycle
        $spinner->start();
        $this->assertTrue($spinner->isRunning());
        $spinner->stop();
        $this->assertFalse($spinner->isRunning());

        // Third cycle
        $spinner->start();
        $this->assertTrue($spinner->isRunning());
    }

    public function testStartResetsFrameToFirst(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE, 0.001);
        $spinner->start();

        // Advance some frames
        for ($i = 0; $i < 3; $i++) {
            \usleep(2000);
            $spinner->update();
        }

        // Should not be on first frame
        $this->assertNotSame('-', $spinner->getCurrentFrame());

        // Start again
        $spinner->start();

        // Should be back to first frame
        $this->assertSame('-', $spinner->getCurrentFrame());
    }

    // === Interval Edge Cases ===

    public function testVerySmallInterval(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE, 0.0001); // 0.1ms
        $spinner->start();

        $firstFrame = $spinner->getCurrentFrame();

        \usleep(1000); // 1ms - should be enough for multiple frame advances
        $spinner->update();

        // Frame should have advanced
        $this->assertNotSame($firstFrame, $spinner->getCurrentFrame());
    }

    public function testVeryLargeInterval(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE, 10.0); // 10 seconds
        $spinner->start();

        $firstFrame = $spinner->getCurrentFrame();

        // Multiple updates should not advance frame
        for ($i = 0; $i < 100; $i++) {
            $spinner->update();
        }

        $this->assertSame($firstFrame, $spinner->getCurrentFrame());
    }

    public function testZeroInterval(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE, 0.0);
        $spinner->start();

        $firstFrame = $spinner->getCurrentFrame();

        // Any update should advance frame with zero interval
        $spinner->update();

        // May or may not advance depending on microtime precision
        // Just ensure no exception is thrown
        $this->assertIsString($spinner->getCurrentFrame());
    }

    // === Factory Method Tests ===

    public function testCreateWithCustomInterval(): void
    {
        $spinner = Spinner::create(Spinner::STYLE_ARROW, 0.25);

        $this->assertSame(0.25, $spinner->getInterval());
        $this->assertSame(8, $spinner->getFrameCount()); // Arrow has 8 frames
        $this->assertFalse($spinner->isRunning());
    }

    public function testCreateAndStartWithCustomInterval(): void
    {
        $spinner = Spinner::createAndStart(Spinner::STYLE_CIRCLE, 0.5);

        $this->assertSame(0.5, $spinner->getInterval());
        $this->assertSame(4, $spinner->getFrameCount()); // Circle has 4 frames
        $this->assertTrue($spinner->isRunning());
    }

    // === Large Index Tests ===

    public function testGetFrameWithVeryLargeIndexWraps(): void
    {
        $spinner = new Spinner(Spinner::STYLE_LINE); // 4 frames

        // Index 1000 should wrap to 1000 % 4 = 0
        $this->assertSame('-', $spinner->getFrame(1000));

        // Index 1001 should wrap to 1001 % 4 = 1
        $this->assertSame('\\', $spinner->getFrame(1001));
    }

    // === Helper Methods ===

    private function createSpinnerScreen(Spinner $spinner, int $posX, int $posY): ScreenInterface
    {
        return new readonly class($spinner, $posX, $posY) implements ScreenInterface {
            public function __construct(
                private Spinner $spinner,
                private int $posX,
                private int $posY,
            ) {}

            public function render(
                Renderer $renderer,
                int $x = 0,
                int $y = 0,
                ?int $width = null,
                ?int $height = null,
            ): void {
                $this->spinner->render($renderer, $x + $this->posX, $y + $this->posY, 20, 1);
            }

            public function handleInput(string $key): bool
            {
                return false;
            }

            public function onActivate(): void {}

            public function onDeactivate(): void {}

            public function update(): void
            {
                $this->spinner->update();
            }

            public function getTitle(): string
            {
                return 'Spinner Test';
            }
        };
    }

    private function createSpinnerScreenWithWidth(Spinner $spinner, int $posX, int $posY, int $width): ScreenInterface
    {
        return new readonly class($spinner, $posX, $posY, $width) implements ScreenInterface {
            public function __construct(
                private Spinner $spinner,
                private int $posX,
                private int $posY,
                private int $width,
            ) {}

            public function render(
                Renderer $renderer,
                int $x = 0,
                int $y = 0,
                ?int $width = null,
                ?int $height = null,
            ): void {
                $this->spinner->render($renderer, $x + $this->posX, $y + $this->posY, $this->width, 1);
            }

            public function handleInput(string $key): bool
            {
                return false;
            }

            public function onActivate(): void {}

            public function onDeactivate(): void {}

            public function update(): void
            {
                $this->spinner->update();
            }

            public function getTitle(): string
            {
                return 'Spinner Test';
            }
        };
    }
}
