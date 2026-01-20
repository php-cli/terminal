<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Layout;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\AbstractComponent;
use Butschster\Commander\UI\Component\ComponentInterface;

/**
 * Panel component with border and title
 */
final class Panel extends AbstractComponent
{
    private ?ComponentInterface $content = null;

    public function __construct(
        private string $title = '',
        ?ComponentInterface $content = null,
    ) {
        if ($content !== null) {
            $this->setContent($content);
        }
    }

    /**
     * Set panel title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * Set panel content component
     */
    public function setContent(?ComponentInterface $content): void
    {
        // Remove old content
        if ($this->content !== null) {
            $this->removeChild($this->content);
        }

        $this->content = $content;

        // Add new content
        if ($content !== null) {
            $this->addChild($content);
        }
    }

    #[\Override]
    public function render(Renderer $renderer, int $x, int $y, int $width, int $height): void
    {
        $this->setBounds($x, $y, $width, $height);
        $theme = $renderer->getThemeContext();

        // Fill interior with blue background first
        if ($width > 2 && $height > 2) {
            $renderer->fillRect(
                $x + 1,
                $y + 1,
                $width - 2,
                $height - 2,
                ' ',
                $theme->getNormalText(),
            );
        }

        // Determine border color based on focus
        $borderColor = $this->isFocused()
            ? $theme->getActiveBorder()
            : $theme->getInactiveBorder();

        // Draw border
        $renderer->drawBox($x, $y, $width, $height, $borderColor);

        // Draw title if present
        if ($this->title !== '') {
            $titleText = ' ' . $this->title . ' ';
            $titleX = $x + 2; // Offset from left border

            $renderer->writeAt($titleX, $y, $titleText, $borderColor);
        }

        // Render content inside panel
        if ($this->content !== null) {
            $contentX = $x + 1;
            $contentY = $y + 1;
            $contentWidth = $width - 2;
            $contentHeight = $height - 2;

            $this->content->render(
                $renderer,
                $contentX,
                $contentY,
                $contentWidth,
                $contentHeight,
            );
        }
    }

    #[\Override]
    public function handleInput(string $key): bool
    {
        // Delegate to content
        if ($this->content !== null && $this->content->isFocused()) {
            return $this->content->handleInput($key);
        }

        return false;
    }

    #[\Override]
    public function setFocused(bool $focused): void
    {
        parent::setFocused($focused);

        // Propagate focus to content
        if ($this->content !== null) {
            $this->content->setFocused($focused);
        }
    }

    #[\Override]
    public function getMinSize(): array
    {
        if ($this->content === null) {
            return ['width' => 10, 'height' => 3];
        }

        $contentMinSize = $this->content->getMinSize();

        return [
            'width' => $contentMinSize['width'] + 2,  // Add borders
            'height' => $contentMinSize['height'] + 2, // Add borders
        ];
    }
}
