<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Terminal\Driver;

/**
 * Abstraction for terminal I/O operations.
 *
 * Allows swapping real terminal with virtual terminal for testing.
 */
interface TerminalDriverInterface
{
    /**
     * Get terminal dimensions.
     *
     * @return array{width: int, height: int}
     */
    public function getSize(): array;

    /**
     * Read next input character/escape sequence (non-blocking).
     *
     * @return string|null Raw input or null if no input available
     */
    public function readInput(): ?string;

    /**
     * Check if input is available without blocking.
     */
    public function hasInput(): bool;

    /**
     * Write raw output to terminal.
     */
    public function write(string $output): void;

    /**
     * Initialize terminal for application use.
     * (raw mode, alternate screen, hide cursor, etc.)
     */
    public function initialize(): void;

    /**
     * Cleanup and restore terminal to original state.
     */
    public function cleanup(): void;

    /**
     * Check if this is an interactive terminal.
     * Returns false for pipes, files, or virtual drivers.
     */
    public function isInteractive(): bool;
}
