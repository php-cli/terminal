<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Layout;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\KeyInput;
use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Theme\ColorScheme;

/**
 * Modal dialog component
 *
 * Displays a centered modal window with title, content, and customizable buttons.
 * Supports error, warning, info, and custom modal types.
 */
final class Modal extends AbstractComponent
{
    public const string TYPE_INFO = 'info';
    public const string TYPE_ERROR = 'error';
    public const string TYPE_WARNING = 'warning';
    public const string TYPE_CONFIRM = 'confirm';

    /** @var array<string, callable> Button definitions (label => callback) */
    private array $buttons = [];

    private int $selectedButtonIndex = 0;

    /** @var string[] Lines of content text */
    private array $contentLines = [];

    private int $modalWidth = 60;
    private int $modalHeight = 15;

    /** @var callable|null Callback when modal is closed */
    private $onClose = null;

    /**
     * @param string $title Modal title
     * @param string $content Modal content (can be multi-line)
     * @param string $type Modal type (info, error, warning, confirm)
     */
    public function __construct(
        private readonly string $title,
        string $content,
        private readonly string $type = self::TYPE_INFO,
    ) {
        $this->setContent($content);
        $this->setupDefaultButtons();
    }

    /**
     * Create an error modal
     */
    public static function error(string $title, string $message): self
    {
        return new self($title, $message, self::TYPE_ERROR);
    }

    /**
     * Create a warning modal
     */
    public static function warning(string $title, string $message): self
    {
        return new self($title, $message, self::TYPE_WARNING);
    }

    /**
     * Create an info modal
     */
    public static function info(string $title, string $message): self
    {
        return new self($title, $message, self::TYPE_INFO);
    }

    /**
     * Create a confirmation modal
     */
    public static function confirm(string $title, string $message): self
    {
        $modal = new self($title, $message, self::TYPE_CONFIRM);
        // Note: Default buttons are already set in setupDefaultButtons() for TYPE_CONFIRM
        // They call $this->close(true) and $this->close(false) which triggers the onClose callback
        return $modal;
    }

    /**
     * Set modal content
     */
    public function setContent(string $content): void
    {
        $this->contentLines = \explode("\n", $content);
    }

    /**
     * Set modal dimensions
     */
    public function setSize(int $width, int $height): void
    {
        $this->modalWidth = \max(30, $width);
        $this->modalHeight = \max(10, $height);
    }

    /**
     * Set custom buttons
     *
     * @param array<string, callable> $buttons Button definitions (label => callback)
     */
    public function setButtons(array $buttons): void
    {
        $this->buttons = $buttons;
        $this->selectedButtonIndex = 0;
    }

    /**
     * Add a button
     *
     * @param string $label Button label
     * @param callable $callback Callback to execute when button is pressed
     */
    public function addButton(string $label, callable $callback): void
    {
        $this->buttons[$label] = $callback;
    }

    /**
     * Set callback for when modal is closed
     *
     * @param callable(): void $callback
     */
    public function onClose(callable $callback): void
    {
        $this->onClose = $callback;
    }

    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);

        // Calculate modal position (centered)
        $modalX = $x + (int) (($width - $this->modalWidth) / 2);
        $modalY = $y + (int) (($height - $this->modalHeight) / 2);

        // Draw subtle dimmed overlay (allows content to show through)
        $this->drawOverlay($renderer, $x, $y, $width, $height);

        // Draw shadow on right and bottom edges for depth effect
        $this->drawShadow($renderer, $modalX, $modalY, $this->modalWidth, $this->modalHeight);

        // Fill modal background
        $renderer->fillRect(
            $modalX,
            $modalY,
            $this->modalWidth,
            $this->modalHeight,
            ' ',
            ColorScheme::$NORMAL_TEXT,
        );

        // Draw modal border
        $renderer->drawBox(
            $modalX,
            $modalY,
            $this->modalWidth,
            $this->modalHeight,
            ColorScheme::$ACTIVE_BORDER,
        );

        // Draw title bar with icon
        $icon = $this->getIcon();
        $titleText = " {$icon} {$this->title} ";
        $titleX = $modalX + (int) (($this->modalWidth - \mb_strlen($titleText)) / 2);
        $renderer->writeAt($titleX, $modalY, $titleText, $this->getTitleColor());

        // Draw horizontal separator after title
        $separator = \str_repeat('─', $this->modalWidth - 2);
        $renderer->writeAt($modalX + 1, $modalY + 1, $separator, ColorScheme::$INACTIVE_BORDER);

        // Draw content
        $this->drawContent($renderer, $modalX, $modalY);

        // Draw buttons
        $this->drawButtons($renderer, $modalX, $modalY);
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        if (empty($this->buttons)) {
            return false;
        }

        $input = KeyInput::from($key);
        $buttonLabels = \array_keys($this->buttons);

        return match (true) {
            // Handle navigation
            $input->is(Key::LEFT) => $this->selectedButtonIndex > 0
                ? (--$this->selectedButtonIndex !== null)
                : true,
            $input->is(Key::RIGHT), $input->is(Key::TAB) => $this->selectedButtonIndex < \count($this->buttons) - 1
                ? (++$this->selectedButtonIndex !== null)
                : true,
            // Handle selection (Enter or Space)
            $input->is(Key::ENTER), $input->isSpace() => $this->activateButton($buttonLabels),
            // Handle escape
            $input->is(Key::ESCAPE) => $this->activateLastButton($buttonLabels),
            // Quick access keys (1-9 for button indices)
            $input->isDigit() => $this->handleDigitKey($input, $buttonLabels),
            default => false,
        };
    }

    private function activateButton(array $buttonLabels): bool
    {
        $selectedLabel = $buttonLabels[$this->selectedButtonIndex];
        $callback = $this->buttons[$selectedLabel];
        $callback();
        return true;
    }

    private function activateLastButton(array $buttonLabels): bool
    {
        $lastLabel = \end($buttonLabels);
        $callback = $this->buttons[$lastLabel];
        $callback();
        return true;
    }

    private function handleDigitKey(KeyInput $input, array $buttonLabels): bool
    {
        $index = (int) $input->raw - 1;
        if ($index >= 0 && isset($buttonLabels[$index])) {
            $callback = $this->buttons[$buttonLabels[$index]];
            $callback();
            return true;
        }
        return false;
    }

    #[\Override]
    public function getMinSize(): array
    {
        return [
            'width' => $this->modalWidth + 10,  // Add margin around modal
            'height' => $this->modalHeight + 6, // Add margin around modal
        ];
    }

    /**
     * Setup default buttons based on modal type
     */
    private function setupDefaultButtons(): void
    {
        $this->buttons = match ($this->type) {
            self::TYPE_CONFIRM => [
                'Yes' => fn() => $this->close(true),
                'No' => fn() => $this->close(false),
            ],
            default => [
                'OK' => fn() => $this->close(),
            ],
        };
    }

    /**
     * Close the modal
     */
    private function close(mixed $result = null): void
    {
        if ($this->onClose !== null) {
            ($this->onClose)($result);
        }
    }

    /**
     * Get title color based on modal type
     */
    private function getTitleColor(): string
    {
        return match ($this->type) {
            self::TYPE_ERROR => ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_RED, ColorScheme::BOLD),
            self::TYPE_WARNING => ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_YELLOW, ColorScheme::BOLD),
            self::TYPE_CONFIRM => ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_CYAN, ColorScheme::BOLD),
            default => ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_BRIGHT_WHITE, ColorScheme::BOLD),
        };
    }

    /**
     * Get icon for modal type
     */
    private function getIcon(): string
    {
        return match ($this->type) {
            self::TYPE_ERROR => '✗',
            self::TYPE_WARNING => '⚠',
            self::TYPE_CONFIRM => '?',
            default => 'ℹ',
        };
    }

    /**
     * Draw dark overlay to dim background
     */
    private function drawOverlay(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        // Use dense pattern to darken background and make modal stand out
        for ($row = 0; $row < $height; $row++) {
            for ($col = 0; $col < $width; $col++) {
                // Checkerboard pattern for darkening effect
                $char = (($row + $col) % 2 === 0) ? '▓' : '▒';
                $renderer->writeAt(
                    $x + $col,
                    $y + $row,
                    $char,
                    ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_BLACK),
                );
            }
        }
    }

    /**
     * Draw shadow effect on right and bottom edges only
     */
    private function drawShadow(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $shadowColor = ColorScheme::combine(ColorScheme::BG_BLACK, ColorScheme::FG_BLACK);

        // Bottom shadow (one line below modal)
        for ($i = 1; $i <= $width; $i++) {
            $renderer->writeAt($x + $i, $y + $height, '▄', $shadowColor);
        }

        // Right shadow (one column to the right of modal)
        for ($i = 1; $i < $height; $i++) {
            $renderer->writeAt($x + $width, $y + $i, '▌', $shadowColor);
        }

        // Bottom-right corner shadow
        $renderer->writeAt($x + $width, $y + $height, '▄', $shadowColor);
    }

    /**
     * Draw modal content
     */
    private function drawContent(Renderer $renderer, int $modalX, int $modalY): void
    {
        $contentX = $modalX + 2;
        $contentY = $modalY + 3; // After title and separator
        $contentWidth = $this->modalWidth - 4;
        $contentHeight = $this->modalHeight - 6; // Reserve space for buttons

        $lineIndex = 0;

        foreach ($this->contentLines as $line) {
            if ($lineIndex >= $contentHeight) {
                break;
            }

            // Word wrap long lines
            $wrappedLines = $this->wrapLine($line, $contentWidth);

            foreach ($wrappedLines as $wrappedLine) {
                if ($lineIndex >= $contentHeight) {
                    break;
                }

                $renderer->writeAt(
                    $contentX,
                    $contentY + $lineIndex,
                    $wrappedLine,
                    ColorScheme::$NORMAL_TEXT,
                );

                $lineIndex++;
            }
        }
    }

    /**
     * Draw buttons at bottom of modal
     */
    private function drawButtons(Renderer $renderer, int $modalX, int $modalY): void
    {
        if (empty($this->buttons)) {
            return;
        }

        $buttonsY = $modalY + $this->modalHeight - 2;

        // Calculate total width of all buttons
        $totalButtonWidth = 0;
        $buttonLabels = \array_keys($this->buttons);

        foreach ($buttonLabels as $label) {
            $totalButtonWidth += \mb_strlen($label) + 6; // [  Label  ] = 6 extra chars
        }

        // Center buttons horizontally
        $startX = $modalX + (int) (($this->modalWidth - $totalButtonWidth) / 2);
        $currentX = $startX;

        foreach ($buttonLabels as $index => $label) {
            $isSelected = ($index === $this->selectedButtonIndex && $this->isFocused());

            // Button format: [  Label  ]
            $buttonText = "[  {$label}  ]";

            $buttonColor = $isSelected
                ? ColorScheme::$SELECTED_TEXT
                : ColorScheme::combine(ColorScheme::BG_BLUE, ColorScheme::FG_CYAN);

            $renderer->writeAt($currentX, $buttonsY, $buttonText, $buttonColor);

            $currentX += \mb_strlen($buttonText) + 2; // Add spacing between buttons
        }
    }

    /**
     * Word wrap a line to fit within width
     *
     * @return array<string>
     */
    private function wrapLine(string $line, int $width): array
    {
        if (\mb_strlen($line) <= $width) {
            return [$line];
        }

        $wrapped = [];
        $words = \explode(' ', $line);
        $currentLine = '';

        foreach ($words as $word) {
            $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;

            if (\mb_strlen($testLine) <= $width) {
                $currentLine = $testLine;
            } else {
                if ($currentLine !== '') {
                    $wrapped[] = $currentLine;
                }
                $currentLine = $word;

                // Handle words longer than width
                while (\mb_strlen($currentLine) > $width) {
                    $wrapped[] = \mb_substr($currentLine, 0, $width);
                    $currentLine = \mb_substr($currentLine, $width);
                }
            }
        }

        if ($currentLine !== '') {
            $wrapped[] = $currentLine;
        }

        return $wrapped;
    }
}
