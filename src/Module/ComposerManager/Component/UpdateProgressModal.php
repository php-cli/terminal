<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\ComposerManager\Component;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Component\Display\Spinner;
use Butschster\Commander\UI\Theme\ColorScheme;
use Symfony\Component\Process\Process;

/**
 * Modal dialog for showing Composer update progress with real-time output.
 */
final class UpdateProgressModal extends AbstractComponent
{
    private const int MODAL_WIDTH = 80;
    private const int MODAL_HEIGHT = 20;
    private const int OUTPUT_LINES = 12;

    private readonly Spinner $spinner;
    private ?Process $process = null;
    private array $outputLines = [];
    private bool $isRunning = false;
    private bool $isComplete = false;
    private ?int $exitCode = null;
    private readonly string $title;

    /** @var \Closure(): void */
    private \Closure $onClose;

    public function __construct(
        private readonly string $packageName = '',
        string $action = 'Updating',
    ) {
        $this->spinner = new Spinner(Spinner::STYLE_BRAILLE, 0.1);
        $this->title = $packageName !== ''
            ? "{$action}: {$packageName}"
            : "{$action} All Packages";
        $this->onClose = static fn() => null;
    }

    /**
     * Set callback for when modal is closed.
     */
    public function onClose(callable $callback): void
    {
        $this->onClose = $callback(...);
    }

    /**
     * Start the update process.
     */
    public function startProcess(Process $process): void
    {
        $this->process = $process;
        $this->isRunning = true;
        $this->isComplete = false;
        $this->exitCode = null;
        $this->outputLines = ['Starting update...', ''];
        $this->spinner->start();
        $this->process->start();
    }

    /**
     * Check if the update is running.
     */
    public function isRunning(): bool
    {
        return $this->isRunning;
    }

    /**
     * Check if the update is complete.
     */
    public function isComplete(): bool
    {
        return $this->isComplete;
    }

    /**
     * Get the exit code (null if still running).
     */
    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    #[\Override]
    public function update(): void
    {
        parent::update();

        if ($this->isRunning) {
            $this->spinner->update();
            $this->readProcessOutput();

            if ($this->process !== null && !$this->process->isRunning()) {
                $this->handleProcessCompletion();
            }
        }
    }

    #[\Override]
    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);

        // Calculate modal position (centered)
        $modalX = $x + (int) (($width - self::MODAL_WIDTH) / 2);
        $modalY = $y + (int) (($height - self::MODAL_HEIGHT) / 2);

        // Draw dimmed overlay
        $dimColor = ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_BRIGHT_BLACK);
        for ($row = $y; $row < $y + $height; $row++) {
            $renderer->writeAt($x, $row, \str_repeat(' ', $width), $dimColor);
        }

        // Fill modal background
        $bgColor = ColorScheme::$NORMAL_TEXT;
        $renderer->fillRect($modalX, $modalY, self::MODAL_WIDTH, self::MODAL_HEIGHT, ' ', $bgColor);

        // Draw modal border
        $borderColor = $this->isComplete
            ? ($this->exitCode === 0 ? ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_GREEN) : ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_RED))
            : ColorScheme::$ACTIVE_BORDER;
        $renderer->drawBox($modalX, $modalY, self::MODAL_WIDTH, self::MODAL_HEIGHT, $borderColor);

        // Draw title with spinner or status icon
        $icon = $this->isRunning
            ? $this->spinner->getCurrentFrame()
            : ($this->exitCode === 0 ? '[OK]' : '[!!]');
        $titleText = " {$icon} {$this->title} ";
        $titleX = $modalX + (int) ((self::MODAL_WIDTH - \mb_strlen($titleText)) / 2);
        $renderer->writeAt($titleX, $modalY, $titleText, $borderColor);

        // Draw separator
        $renderer->writeAt($modalX + 1, $modalY + 1, \str_repeat('─', self::MODAL_WIDTH - 2), ColorScheme::$INACTIVE_BORDER);

        // Draw output area
        $outputY = $modalY + 2;
        $outputHeight = self::OUTPUT_LINES;
        $visibleLines = \array_slice($this->outputLines, -$outputHeight);

        foreach ($visibleLines as $i => $line) {
            $truncatedLine = \mb_substr((string) $line, 0, self::MODAL_WIDTH - 4);
            $renderer->writeAt($modalX + 2, $outputY + $i, $truncatedLine, ColorScheme::$NORMAL_TEXT);
        }

        // Draw separator before buttons
        $buttonSepY = $modalY + self::MODAL_HEIGHT - 3;
        $renderer->writeAt($modalX + 1, $buttonSepY, \str_repeat('─', self::MODAL_WIDTH - 2), ColorScheme::$INACTIVE_BORDER);

        // Draw status line and buttons
        $buttonY = $modalY + self::MODAL_HEIGHT - 2;
        if ($this->isRunning) {
            $statusText = 'Press Ctrl+C to cancel';
            $renderer->writeAt($modalX + 2, $buttonY, $statusText, ColorScheme::$NORMAL_TEXT);
        } else {
            $statusText = $this->exitCode === 0
                ? '[OK] Update completed successfully'
                : "[!!] Update failed (exit code: {$this->exitCode})";
            $statusColor = $this->exitCode === 0
                ? ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_GREEN)
                : ColorScheme::combine(ColorScheme::$NORMAL_BG, ColorScheme::FG_RED);
            $renderer->writeAt($modalX + 2, $buttonY, $statusText, $statusColor);

            // Draw close button
            $closeText = '[ Close (Enter) ]';
            $closeX = $modalX + self::MODAL_WIDTH - \mb_strlen($closeText) - 2;
            $renderer->writeAt($closeX, $buttonY, $closeText, ColorScheme::$HIGHLIGHT_TEXT);
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        $input = KeyInput::from($key);

        // Cancel with Ctrl+C while running
        if ($this->isRunning && $input->isCtrl(Key::C)) {
            $this->cancelProcess();
            return true;
        }

        // Close with Enter or Escape when complete
        if ($this->isComplete && ($input->is(Key::ENTER) || $input->is(Key::ESCAPE))) {
            $this->close();
            return true;
        }

        return true; // Block all other input
    }

    private function readProcessOutput(): void
    {
        if ($this->process === null) {
            return;
        }

        $output = $this->process->getIncrementalOutput();
        $errorOutput = $this->process->getIncrementalErrorOutput();

        $this->processOutputText($output);
        $this->processOutputText($errorOutput);
    }

    private function processOutputText(string $output): void
    {
        if ($output === '') {
            return;
        }

        // Strip ANSI codes
        $output = (string) \preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $output);
        $output = (string) \preg_replace('/\x1b\][^\x07]*\x07/', '', $output);

        // Split by lines and add to output
        $lines = \explode("\n", $output);
        foreach ($lines as $line) {
            $line = \trim($line);
            if ($line !== '') {
                $this->outputLines[] = $line;
            }
        }

        // Keep only last 100 lines to prevent memory issues
        if (\count($this->outputLines) > 100) {
            $this->outputLines = \array_slice($this->outputLines, -100);
        }
    }

    private function handleProcessCompletion(): void
    {
        if ($this->process === null) {
            return;
        }

        $this->exitCode = $this->process->getExitCode();
        $this->isRunning = false;
        $this->isComplete = true;
        $this->spinner->stop();

        $this->outputLines[] = '';
        $this->outputLines[] = $this->exitCode === 0
            ? 'Update completed successfully.'
            : "Update failed with exit code: {$this->exitCode}";
    }

    private function cancelProcess(): void
    {
        if ($this->process === null) {
            return;
        }

        $this->process->stop(3, \SIGTERM);
        $this->outputLines[] = '';
        $this->outputLines[] = 'Update cancelled by user.';
        $this->exitCode = -1;
        $this->isRunning = false;
        $this->isComplete = true;
        $this->spinner->stop();
    }

    private function close(): void
    {
        ($this->onClose)();
    }
}
