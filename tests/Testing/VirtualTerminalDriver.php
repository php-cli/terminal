<?php

declare(strict_types=1);

namespace Tests\Testing;

use Butschster\Commander\Infrastructure\Terminal\Driver\TerminalDriverInterface;

/**
 * Virtual terminal driver for testing.
 *
 * Provides scripted input and captures output for verification.
 */
final class VirtualTerminalDriver implements TerminalDriverInterface
{
    /** @var list<string|null> Queued input keys (null = frame boundary) */
    private array $inputQueue = [];

    /** Raw output written to terminal */
    private string $outputBuffer = '';

    /** Whether initialize() was called */
    private bool $initialized = false;

    public function __construct(private int $width = 80, private int $height = 24)
    {
    }

    public function setSize(int $width, int $height): void
    {
        $this->width = $width;
        $this->height = $height;
    }

    public function getSize(): array
    {
        return ['width' => $this->width, 'height' => $this->height];
    }

    public function readInput(): ?string
    {
        if (empty($this->inputQueue)) {
            return null;
        }

        return \array_shift($this->inputQueue);
    }

    public function hasInput(): bool
    {
        return !empty($this->inputQueue);
    }

    public function write(string $output): void
    {
        $this->outputBuffer .= $output;
    }

    public function initialize(): void
    {
        $this->initialized = true;
        $this->outputBuffer = '';
    }

    public function cleanup(): void
    {
        $this->initialized = false;
    }

    public function isInteractive(): bool
    {
        return false;
    }

    /**
     * Queue input keys to be returned by readInput().
     *
     * @param string ...$keys Key names: 'UP', 'ENTER', 'F10', 'CTRL_C', etc.
     */
    public function queueInput(string ...$keys): void
    {
        foreach ($keys as $key) {
            $this->inputQueue[] = $key;
        }
    }

    /**
     * Queue a frame boundary marker.
     */
    public function queueFrameBoundary(): void
    {
        $this->inputQueue[] = null;
    }

    /**
     * Queue multiple key sequences at once.
     *
     * @param array<string|array<string>> $sequence
     */
    public function queueSequence(array $sequence): void
    {
        foreach ($sequence as $item) {
            if (\is_array($item)) {
                foreach ($item as $key) {
                    $this->inputQueue[] = $key;
                }
            } else {
                $this->inputQueue[] = $item;
            }
        }
    }

    /**
     * Clear all queued input.
     */
    public function clearInput(): void
    {
        $this->inputQueue = [];
    }

    /**
     * Get raw output buffer.
     */
    public function getOutput(): string
    {
        return $this->outputBuffer;
    }

    /**
     * Clear output buffer.
     */
    public function clearOutput(): void
    {
        $this->outputBuffer = '';
    }

    /**
     * Check if driver was initialized.
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Get remaining input count.
     */
    public function getRemainingInputCount(): int
    {
        return \count($this->inputQueue);
    }

    /**
     * Parse ANSI output and return screen capture.
     */
    public function getScreenCapture(): ScreenCapture
    {
        $parser = new AnsiParser($this->width, $this->height);
        return $parser->parse($this->outputBuffer);
    }
}
