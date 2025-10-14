<?php

declare(strict_types=1);

namespace Butschster\Commander;

use Codedungeon\PHPCliColors\Color;
use Spiral\Exceptions\Renderer\Highlighter;
use Spiral\Exceptions\Style\ConsoleStyle;
use Spiral\Exceptions\Style\PlainStyle;

final class ExceptionRenderer
{
    public const int SHOW_LINES = 2;
    protected const array FORMATS = ['console', 'cli'];
    protected const array COLORS = [
        'bg:red' => Color::BG_RED,
        'bg:cyan' => Color::BG_CYAN,
        'bg:magenta' => Color::BG_MAGENTA,
        'bg:white' => Color::BG_WHITE,
        'white' => Color::LIGHT_WHITE,
        'green' => Color::GREEN,
        'gray' => Color::GRAY,
        'black' => Color::BLACK,
        'red' => Color::RED,
        'yellow' => Color::YELLOW,
        'reset' => Color::RESET,
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
                $row .= $this->renderTrace(
                    $exception,
                    new Highlighter(
                        $this->colorsSupport ? new ConsoleStyle() : new PlainStyle(),
                    ),
                );
            }

            $result[] = $row;
        }

        $this->lines = [];

        return \implode("\n", \array_reverse($result));
    }

    /**
     * Render title using outlining border.
     *
     * @param string $title Title.
     * @param string $style Formatting.
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
                \str_repeat('', $padding + 1),
                $line,
                \str_repeat('', $length - \mb_strlen($line) + 1),
            );
        }

        return $result;
    }

    /**
     * Render exception call stack.
     */
    private function renderTrace(\Throwable $e, ?Highlighter $h = null): string
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
            $classColor = 'while';

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
                    $trace['line'],
                );
            }

            if (\in_array($line, $this->lines, true)) {
                continue;
            }

            $this->lines[] = $line;

            $result .= $line . "\n";

            if ($h !== null && !empty($trace['file'])) {
                $str = @\file_get_contents($trace['file']);
                $result .= $h->highlightLines(
                    $str,
                    $trace['line'],
                    self::SHOW_LINES,
                ) . "\n";
                unset($str);
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

        //Let's let's clarify exception location
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
     * @codeCoverageIgnore
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
