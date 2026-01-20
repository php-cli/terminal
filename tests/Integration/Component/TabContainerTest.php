<?php

declare(strict_types=1);

namespace Tests\Integration\Component;

use Butschster\Commander\Infrastructure\Terminal\Renderer;
use Butschster\Commander\UI\Component\Container\AbstractTab;
use Butschster\Commander\UI\Component\Container\TabContainer;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Tests\TerminalTestCase;

final class TabContainerTest extends TerminalTestCase
{
    public function testTabsRenderCorrectly(): void
    {
        $this->terminal()->setSize(80, 24);

        $this->runApp($this->createTabScreen());

        $this->assertScreenContains('Tab 1');
        $this->assertScreenContains('Tab 2');
        $this->assertScreenContains('Tab 3');
        $this->assertScreenContains('Content of Tab 1');
    }

    public function testTabSwitchingProgrammatically(): void
    {
        $this->terminal()->setSize(80, 24);

        $screen = $this->createTabScreenWithSwitch(1);
        $this->runApp($screen);

        $this->assertScreenContains('Content of Tab 2');
    }

    public function testTabSwitchingToThirdTab(): void
    {
        $this->terminal()->setSize(80, 24);

        $screen = $this->createTabScreenWithSwitch(2);
        $this->runApp($screen);

        $this->assertScreenContains('Content of Tab 3');
    }

    private function createTabScreen(): ScreenInterface
    {
        return new readonly class implements ScreenInterface {
            private TabContainer $tabs;

            public function __construct()
            {
                $this->tabs = new TabContainer();

                $this->tabs->addTab(
                    new class extends AbstractTab {
                        public function getTitle(): string
                        {
                            return 'Tab 1';
                        }

                        public function render(Renderer $renderer, int $x, int $y, ?int $width, ?int $height): void
                        {
                            $renderer->writeAt(
                                $x + 2,
                                $y + 2,
                                'Content of Tab 1',
                                $renderer->getThemeContext()->getNormalText(),
                            );
                        }
                    },
                );

                $this->tabs->addTab(
                    new class extends AbstractTab {
                        public function getTitle(): string
                        {
                            return 'Tab 2';
                        }

                        public function render(Renderer $renderer, int $x, int $y, ?int $width, ?int $height): void
                        {
                            $renderer->writeAt(
                                $x + 2,
                                $y + 2,
                                'Content of Tab 2',
                                $renderer->getThemeContext()->getNormalText(),
                            );
                        }
                    },
                );

                $this->tabs->addTab(
                    new class extends AbstractTab {
                        public function getTitle(): string
                        {
                            return 'Tab 3';
                        }

                        public function render(Renderer $renderer, int $x, int $y, ?int $width, ?int $height): void
                        {
                            $renderer->writeAt(
                                $x + 2,
                                $y + 2,
                                'Content of Tab 3',
                                $renderer->getThemeContext()->getNormalText(),
                            );
                        }
                    },
                );
            }

            public function render(
                Renderer $renderer,
                int $x = 0,
                int $y = 0,
                ?int $width = null,
                ?int $height = null,
            ): void {
                $this->tabs->render($renderer, $x, $y, $width, $height);
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
                return 'Tabs Test';
            }
        };
    }

    private function createTabScreenWithSwitch(int $tabIndex): ScreenInterface
    {
        return new readonly class($tabIndex) implements ScreenInterface {
            private TabContainer $tabs;

            public function __construct(private int $switchToTab)
            {
                $this->tabs = new TabContainer();

                $this->tabs->addTab(
                    new class extends AbstractTab {
                        public function getTitle(): string
                        {
                            return 'Tab 1';
                        }

                        public function render(Renderer $renderer, int $x, int $y, ?int $width, ?int $height): void
                        {
                            $renderer->writeAt(
                                $x + 2,
                                $y + 2,
                                'Content of Tab 1',
                                $renderer->getThemeContext()->getNormalText(),
                            );
                        }
                    },
                );

                $this->tabs->addTab(
                    new class extends AbstractTab {
                        public function getTitle(): string
                        {
                            return 'Tab 2';
                        }

                        public function render(Renderer $renderer, int $x, int $y, ?int $width, ?int $height): void
                        {
                            $renderer->writeAt(
                                $x + 2,
                                $y + 2,
                                'Content of Tab 2',
                                $renderer->getThemeContext()->getNormalText(),
                            );
                        }
                    },
                );

                $this->tabs->addTab(
                    new class extends AbstractTab {
                        public function getTitle(): string
                        {
                            return 'Tab 3';
                        }

                        public function render(Renderer $renderer, int $x, int $y, ?int $width, ?int $height): void
                        {
                            $renderer->writeAt(
                                $x + 2,
                                $y + 2,
                                'Content of Tab 3',
                                $renderer->getThemeContext()->getNormalText(),
                            );
                        }
                    },
                );

                $this->tabs->switchToTab($this->switchToTab);
            }

            public function render(
                Renderer $renderer,
                int $x = 0,
                int $y = 0,
                ?int $width = null,
                ?int $height = null,
            ): void {
                $this->tabs->render($renderer, $x, $y, $width, $height);
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
                return 'Tabs Test';
            }
        };
    }
}
