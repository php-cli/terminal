<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Terminal\Driver;

use Tests\Testing\VirtualTerminalDriver;
use PHPUnit\Framework\TestCase;

final class VirtualTerminalDriverTest extends TestCase
{
    public function testDefaultSize(): void
    {
        $driver = new VirtualTerminalDriver();

        $this->assertSame(['width' => 80, 'height' => 24], $driver->getSize());
    }

    public function testCustomSize(): void
    {
        $driver = new VirtualTerminalDriver(120, 40);

        $this->assertSame(['width' => 120, 'height' => 40], $driver->getSize());
    }

    public function testSetSize(): void
    {
        $driver = new VirtualTerminalDriver();
        $driver->setSize(100, 30);

        $this->assertSame(['width' => 100, 'height' => 30], $driver->getSize());
    }

    public function testQueueAndReadInput(): void
    {
        $driver = new VirtualTerminalDriver();

        $driver->queueInput('UP', 'DOWN', 'ENTER');

        $this->assertTrue($driver->hasInput());
        $this->assertSame('UP', $driver->readInput());
        $this->assertSame('DOWN', $driver->readInput());
        $this->assertSame('ENTER', $driver->readInput());
        $this->assertFalse($driver->hasInput());
        $this->assertNull($driver->readInput());
    }

    public function testFrameBoundary(): void
    {
        $driver = new VirtualTerminalDriver();

        $driver->queueInput('UP');
        $driver->queueFrameBoundary();
        $driver->queueInput('DOWN');

        $this->assertSame('UP', $driver->readInput());
        $this->assertNull($driver->readInput()); // Frame boundary
        $this->assertSame('DOWN', $driver->readInput());
    }

    public function testQueueSequence(): void
    {
        $driver = new VirtualTerminalDriver();

        $driver->queueSequence(['UP', ['DOWN', 'LEFT'], 'ENTER']);

        $this->assertSame('UP', $driver->readInput());
        $this->assertSame('DOWN', $driver->readInput());
        $this->assertSame('LEFT', $driver->readInput());
        $this->assertSame('ENTER', $driver->readInput());
    }

    public function testClearInput(): void
    {
        $driver = new VirtualTerminalDriver();

        $driver->queueInput('UP', 'DOWN');
        $driver->clearInput();

        $this->assertFalse($driver->hasInput());
        $this->assertSame(0, $driver->getRemainingInputCount());
    }

    public function testOutputCapture(): void
    {
        $driver = new VirtualTerminalDriver();

        $driver->write('Hello ');
        $driver->write('World');

        $this->assertSame('Hello World', $driver->getOutput());
    }

    public function testClearOutput(): void
    {
        $driver = new VirtualTerminalDriver();

        $driver->write('Test');
        $driver->clearOutput();

        $this->assertSame('', $driver->getOutput());
    }

    public function testInitializeAndCleanup(): void
    {
        $driver = new VirtualTerminalDriver();

        $this->assertFalse($driver->isInitialized());

        $driver->initialize();
        $this->assertTrue($driver->isInitialized());
        $this->assertSame('', $driver->getOutput()); // Cleared on init

        $driver->cleanup();
        $this->assertFalse($driver->isInitialized());
    }

    public function testIsNotInteractive(): void
    {
        $driver = new VirtualTerminalDriver();

        $this->assertFalse($driver->isInteractive());
    }

    public function testRemainingInputCount(): void
    {
        $driver = new VirtualTerminalDriver();

        $driver->queueInput('UP', 'DOWN', 'ENTER');

        $this->assertSame(3, $driver->getRemainingInputCount());
        $driver->readInput();
        $this->assertSame(2, $driver->getRemainingInputCount());
    }

    public function testScreenCapture(): void
    {
        $driver = new VirtualTerminalDriver(10, 3);

        // Simulate simple output: position cursor and write
        $driver->write("\033[1;1H"); // Move to (0,0)
        $driver->write("Hello");
        $driver->write("\033[2;1H"); // Move to (0,1)
        $driver->write("World");

        $capture = $driver->getScreenCapture();

        $this->assertTrue($capture->contains('Hello'));
        $this->assertTrue($capture->contains('World'));
        $this->assertSame('Hello', \trim($capture->getLine(0)));
        $this->assertSame('World', \trim($capture->getLine(1)));
    }

    public function testScreenCaptureWithColors(): void
    {
        $driver = new VirtualTerminalDriver(20, 3);

        $driver->write("\033[1;1H");
        $driver->write("\033[31m"); // Red
        $driver->write("Red");
        $driver->write("\033[32m"); // Green
        $driver->write("Green");

        $capture = $driver->getScreenCapture();

        $this->assertTrue($capture->contains('Red'));
        $this->assertTrue($capture->contains('Green'));
        $this->assertSame("\033[31m", $capture->getColorAt(0, 0));
        $this->assertSame("\033[32m", $capture->getColorAt(3, 0));
    }
}
