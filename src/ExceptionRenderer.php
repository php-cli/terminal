<?php

declare(strict_types=1);

namespace Butschster\Commander;

final class ExceptionRenderer
{
    public const int SHOW_LINES = 2;

    // ANSI escape codes for colors
    protected const array COLORS = [
        'bg:red' => "\033[41m",
        'bg:cyan' => "\033[46m",
        'bg:magenta' => "\033[45m",
        'bg:white' => "\033[47m",
        'white' => "\033[97m",
        'green' => "\033[32m",
        'gray' => "\033[90m",
        'black' => "\033[30m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'reset' => "\033[0m",
    ];

    private array $lines = [];
    private readonly bool $colorsSupport;

    /**
     * @param bool|resource|null $stream
     */
    public function __construct(mixed $stream = null)
    {
        $stream ??= \defined('\STDOUT') ? \STDOUT : \fopen('php://stdout', 'wb');

        $this->colorsSupport = $this->isColorsSupported($stream);
    }

    public function render(
        \Throwable $exception,
        bool $verbose = false,
    ): string {
        // Restore terminal on error
        echo "\033[?25h";  // Show cursor
        echo "\033[?1049l"; // Exit alternate screen
        echo "\033[0m";     // Reset colors
        echo "\033[2J\033[H"; // Clear screen and move to top

        $exceptions = [$exception];
        $currentE = $exception;

        while ($exception = $exception->getPrevious()) {
            $exceptions[] = $exception;
        }

        $exceptions = \array_reverse($exceptions);

        $result = [];
        $rootDir = \getcwd();

        foreach ($exceptions as $exception) {
            $prefix = $currentE === $exception ? '' : 'Previous: ';
            $row = $this->renderHeader(
                \sprintf("%s[%s]\n%s", $prefix, $exception::class, $exception->getMessage()),
                $exception instanceof \Error ? 'bg:magenta,white' : 'bg:red,white',
            );

            $file = \str_starts_with($exception->getFile(), $rootDir)
                ? \substr($exception->getFile(), \strlen($rootDir) + 1)
                : $exception->getFile();

            $row .= $this->format(
                "<yellow>in</reset> <green>%s</reset><yellow>:</reset><white>%s</reset>\n",
                $file,
                $exception->getLine(),
            );

            if ($verbose) {
                $row .= $this->renderTrace($exception);
            }

            $result[] = $row;
        }

        $this->lines = [];

        return \implode("\n", \array_reverse($result));
    }

    /**
     * Render title using outlining border.
     */
    private function renderHeader(string $title, string $style, int $padding = 0): string
    {
        $result = '';

        $lines = \explode("\n", \str_replace("\r", '', $title));

        $length = 0;
        \array_walk($lines, static function ($v) use (&$length): void {
            $length = \max($length, \mb_strlen($v));
        });

        $length += $padding;

        foreach ($lines as $line) {
            $result .= $this->format(
                "<{$style}>%s%s%s</reset>\n",
                \str_repeat(' ', $padding + 1),
                $line,
                \str_repeat(' ', $length - \mb_strlen($line) + 1),
            );
        }

        return $result;
    }

    /**
     * Render exception call stack.
     */
    private function renderTrace(\Throwable $e): string
    {
        $stacktrace = $this->getStacktrace($e);
        if (empty($stacktrace)) {
            return '';
        }

        $result = "\n";
        $rootDir = \getcwd();

        $pad = \strlen((string) \count($stacktrace));

        foreach ($stacktrace as $i => $trace) {
            $file = isset($trace['file']) ? (string) $trace['file'] : null;
            $classColor = 'white';

            if ($file !== null) {
                \str_starts_with($file, $rootDir) and $file = \substr($file, \strlen($rootDir) + 1);
                $classColor = \str_starts_with($file, 'vendor/') ? 'gray' : 'white';
            }

            if (isset($trace['type'], $trace['class'])) {
                $line = $this->format(
                    "<$classColor>%s.</reset> <white>%s%s%s()</reset>",
                    \str_pad((string) ((int) $i + 1), $pad, ' ', \STR_PAD_LEFT),
                    $trace['class'],
                    $trace['type'],
                    $trace['function'],
                );
            } else {
                $line = $this->format(
                    ' <white>%s()</reset>',
                    $trace['function'],
                );
            }
            if ($file !== null) {
                $line .= $this->format(
                    ' <yellow>at</reset> <green>%s</reset><yellow>:</reset><white>%s</reset>',
                    $file,
                    $trace['line'] ?? '?',
                );
            }

            if (\in_array($line, $this->lines, true)) {
                continue;
            }

            $this->lines[] = $line;

            $result .= $line . "\n";

            // Show code snippet if file exists
            if (!empty($trace['file']) && \is_readable($trace['file']) && isset($trace['line'])) {
                $result .= $this->renderCodeSnippet($trace['file'], (int) $trace['line']) . "\n";
            }
        }

        return $result;
    }

    /**
     * Render code snippet around the error line.
     */
    private function renderCodeSnippet(string $file, int $line): string
    {
        $lines = @\file($file);
        if ($lines === false) {
            return '';
        }

        $start = \max(0, $line - self::SHOW_LINES - 1);
        $end = \min(\count($lines), $line + self::SHOW_LINES);

        $result = '';
        $padLength = \strlen((string) $end);

        for ($i = $start; $i < $end; $i++) {
            $lineNum = $i + 1;
            $code = \rtrim($lines[$i]);
            $isErrorLine = $lineNum === $line;

            $lineNumStr = \str_pad((string) $lineNum, $padLength, ' ', \STR_PAD_LEFT);

            if ($isErrorLine) {
                $result .= $this->format(
                    "    <bg:red,white> %s </reset> <white>%s</reset>\n",
                    $lineNumStr,
                    $code,
                );
            } else {
                $result .= $this->format(
                    "    <gray> %s </reset> <gray>%s</reset>\n",
                    $lineNumStr,
                    $code,
                );
            }
        }

        return $result;
    }

    /**
     * Normalized exception stacktrace.
     */
    private function getStacktrace(\Throwable $e): array
    {
        $stacktrace = $e->getTrace();
        if (empty($stacktrace)) {
            return [];
        }

        // Clarify exception location
        $header = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ] + $stacktrace[0];

        if ($stacktrace[0] !== $header) {
            \array_unshift($stacktrace, $header);
        }

        return $stacktrace;
    }

    /**
     * Format string and apply color formatting (if enabled).
     */
    private function format(string $format, mixed ...$args): string
    {
        if (!$this->colorsSupport) {
            $format = \preg_replace('/<[^>]+>/', '', $format);
        } else {
            $format = \preg_replace_callback('/(<([^>]+)>)/', static function ($partial) {
                $style = '';
                foreach (\explode(',', \trim($partial[2], '/')) as $color) {
                    if (isset(self::COLORS[$color])) {
                        $style .= self::COLORS[$color];
                    }
                }

                return $style;
            }, $format);
        }

        return \sprintf($format, ...$args);
    }

    /**
     * Returns true if the STDOUT supports colorization.
     * @link https://github.com/symfony/Console/blob/master/Output/StreamOutput.php#L94
     */
    private function isColorsSupported(mixed $stream = STDOUT): bool
    {
        if (\getenv('TERM_PROGRAM') === 'Hyper') {
            return true;
        }

        try {
            if (\DIRECTORY_SEPARATOR === '\\') {
                return (\function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support($stream))
                    || \getenv('ANSICON') !== false
                    || \getenv('ConEmuANSI') === 'ON'
                    || \getenv('TERM') === 'xterm';
            }

            return @\stream_isatty($stream);
        } catch (\Throwable) {
            return false;
        }
    }
}
