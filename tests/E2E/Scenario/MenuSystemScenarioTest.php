<?php

declare(strict_types=1);

namespace Tests\E2E\Scenario;

use Butschster\Commander\Infrastructure\Keyboard\KeyCombination;
use Butschster\Commander\UI\Component\Layout\MenuDropdown;
use Butschster\Commander\UI\Menu\ActionMenuItem;
use Butschster\Commander\UI\Menu\MenuDefinition;
use Butschster\Commander\UI\Menu\MenuItemInterface;
use Butschster\Commander\UI\Menu\ScreenMenuItem;
use Butschster\Commander\UI\Menu\SeparatorMenuItem;
use Butschster\Commander\UI\Menu\SubmenuMenuItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TerminalTestCase;

/**
 * E2E tests for the polymorphic menu item system.
 *
 * Tests verify that ScreenMenuItem, ActionMenuItem, SeparatorMenuItem,
 * and SubmenuMenuItem work correctly in MenuDropdown and MenuDefinition contexts.
 */
#[CoversClass(ScreenMenuItem::class)]
#[CoversClass(ActionMenuItem::class)]
#[CoversClass(SeparatorMenuItem::class)]
#[CoversClass(SubmenuMenuItem::class)]
#[CoversClass(MenuDefinition::class)]
#[CoversClass(MenuDropdown::class)]
final class MenuSystemScenarioTest extends TerminalTestCase
{
    private bool $actionExecuted = false;
    private string $lastActionLabel = '';

    // === MenuDropdown with Polymorphic Items ===

    #[Test]
    public function dropdown_renders_screen_menu_items(): void
    {
        $this->terminal()->setSize(80, 24);

        $items = [
            ScreenMenuItem::create('Open File', 'files.open', 'o'),
            ScreenMenuItem::create('Save File', 'files.save', 's'),
        ];

        $dropdown = new MenuDropdown($items, 5, 2);
        $dropdown->setFocused(true);

        $this->renderDropdown($dropdown);

        $this->assertScreenContains('Open File');
        $this->assertScreenContains('Save File');
    }

    #[Test]
    public function dropdown_renders_action_menu_items(): void
    {
        $this->terminal()->setSize(80, 24);

        $items = [
            ActionMenuItem::create('Run Task', static fn() => null, 'r'),
            ActionMenuItem::create('Stop Task', static fn() => null, 't'),
        ];

        $dropdown = new MenuDropdown($items, 5, 2);
        $dropdown->setFocused(true);

        $this->renderDropdown($dropdown);

        $this->assertScreenContains('Run Task');
        $this->assertScreenContains('Stop Task');
    }

    #[Test]
    public function dropdown_renders_separator_as_line(): void
    {
        $this->terminal()->setSize(80, 24);

        $items = [
            ActionMenuItem::create('Item Before', static fn() => null),
            SeparatorMenuItem::create(),
            ActionMenuItem::create('Item After', static fn() => null),
        ];

        $dropdown = new MenuDropdown($items, 5, 2);
        $dropdown->setFocused(true);

        $this->renderDropdown($dropdown);

        $this->assertScreenContains('Item Before');
        $this->assertScreenContains('Item After');
        // Separator should render as horizontal line characters
        $this->assertScreenContainsAny(['─', '├', '┤']);
    }

    #[Test]
    public function dropdown_renders_submenu_with_arrow_indicator(): void
    {
        $this->terminal()->setSize(80, 24);

        $items = [
            ScreenMenuItem::create('Simple Item', 'screen.simple'),
            SubmenuMenuItem::create('More Options', [
                ScreenMenuItem::create('Sub Item 1', 'screen.sub1'),
                ScreenMenuItem::create('Sub Item 2', 'screen.sub2'),
            ], 'm'),
        ];

        $dropdown = new MenuDropdown($items, 5, 2);
        $dropdown->setFocused(true);

        $this->renderDropdown($dropdown);

        $this->assertScreenContains('More Options');
        $this->assertScreenContains('►'); // Submenu indicator
    }

    #[Test]
    public function action_menu_item_executes_closure_on_selection(): void
    {
        $items = [
            ActionMenuItem::create('Execute Action', function (): void {
                $this->actionExecuted = true;
                $this->lastActionLabel = 'Execute Action';
            }, 'e'),
        ];

        $dropdown = new MenuDropdown($items, 5, 2);

        $receivedItem = null;
        $dropdown->onSelect(static function (MenuItemInterface $item) use (&$receivedItem): void {
            $receivedItem = $item;
            if ($item instanceof ActionMenuItem) {
                ($item->action)();
            }
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('ENTER');

        $this->assertTrue($this->actionExecuted, 'Action closure should have been executed');
        $this->assertSame('Execute Action', $this->lastActionLabel);
    }

    #[Test]
    public function screen_menu_item_provides_screen_name_on_selection(): void
    {
        $items = [
            ScreenMenuItem::create('File Browser', 'files.browser', 'f'),
        ];

        $dropdown = new MenuDropdown($items, 5, 2);

        $selectedScreenName = null;
        $dropdown->onSelect(static function (MenuItemInterface $item) use (&$selectedScreenName): void {
            if ($item instanceof ScreenMenuItem) {
                $selectedScreenName = $item->screenName;
            }
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('ENTER');

        $this->assertSame('files.browser', $selectedScreenName);
    }

    #[Test]
    public function separator_is_skipped_during_arrow_navigation(): void
    {
        $items = [
            ActionMenuItem::create('First', static fn() => null),
            SeparatorMenuItem::create(),
            ActionMenuItem::create('Third', static fn() => null),
        ];

        $dropdown = new MenuDropdown($items, 5, 2);

        $receivedItem = null;
        $dropdown->onSelect(static function (MenuItemInterface $item) use (&$receivedItem): void {
            $receivedItem = $item;
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('DOWN'); // Should skip separator
        $dropdown->handleInput('ENTER');

        $this->assertSame('Third', $receivedItem->getLabel());
    }

    #[Test]
    public function hotkey_selects_action_item_directly(): void
    {
        $items = [
            ActionMenuItem::create('Copy', function (): void {
                $this->actionExecuted = true;
                $this->lastActionLabel = 'Copy';
            }, 'c'),
            ActionMenuItem::create('Paste', function (): void {
                $this->actionExecuted = true;
                $this->lastActionLabel = 'Paste';
            }, 'p'),
        ];

        $dropdown = new MenuDropdown($items, 5, 2);
        $dropdown->onSelect(static function (MenuItemInterface $item): void {
            if ($item instanceof ActionMenuItem) {
                ($item->action)();
            }
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('p'); // Press 'p' hotkey

        $this->assertTrue($this->actionExecuted);
        $this->assertSame('Paste', $this->lastActionLabel);
    }

    #[Test]
    public function hotkey_uses_first_char_when_not_explicit(): void
    {
        $items = [
            ActionMenuItem::create('Save', function (): void {
                $this->actionExecuted = true;
                $this->lastActionLabel = 'Save';
            }), // No explicit hotkey - should use 's'
        ];

        $dropdown = new MenuDropdown($items, 5, 2);
        $dropdown->onSelect(static function (MenuItemInterface $item): void {
            if ($item instanceof ActionMenuItem) {
                ($item->action)();
            }
        });

        $dropdown->setFocused(true);
        $dropdown->handleInput('s'); // Press first char

        $this->assertTrue($this->actionExecuted);
        $this->assertSame('Save', $this->lastActionLabel);
    }

    // === MenuDefinition Tests ===

    #[Test]
    public function menu_definition_returns_first_non_separator_item(): void
    {
        $items = [
            SeparatorMenuItem::create(),
            SeparatorMenuItem::create(),
            ScreenMenuItem::create('First Real', 'screen.first'),
            ActionMenuItem::create('Second', static fn() => null),
        ];

        $menu = new MenuDefinition('Test', null, $items);

        $first = $menu->getFirstItem();

        $this->assertInstanceOf(ScreenMenuItem::class, $first);
        $this->assertSame('First Real', $first->getLabel());
    }

    #[Test]
    public function menu_definition_returns_null_when_only_separators(): void
    {
        $items = [
            SeparatorMenuItem::create(),
            SeparatorMenuItem::create(),
        ];

        $menu = new MenuDefinition('Test', null, $items);

        $this->assertNull($menu->getFirstItem());
    }

    #[Test]
    public function menu_definition_returns_null_when_empty(): void
    {
        $menu = new MenuDefinition('Test', null, []);

        $this->assertNull($menu->getFirstItem());
    }

    #[Test]
    public function menu_definition_with_fkey_stores_combination(): void
    {
        $fkey = KeyCombination::fromString('F1');

        $menu = new MenuDefinition(
            label: 'Files',
            fkey: $fkey,
            items: [ScreenMenuItem::create('Open', 'files.open')],
            priority: 1,
        );

        $this->assertSame('Files', $menu->label);
        $this->assertSame($fkey, $menu->fkey);
        $this->assertSame(1, $menu->priority);
    }

    // === Mixed Item Types Tests ===

    #[Test]
    public function dropdown_handles_mixed_item_types(): void
    {
        $items = [
            ScreenMenuItem::create('Navigate', 'screen.nav'),
            SeparatorMenuItem::create(),
            ActionMenuItem::create('Execute', static fn() => null),
            SeparatorMenuItem::create(),
            SubmenuMenuItem::create('More', [
                ActionMenuItem::create('Sub Action', static fn() => null),
            ]),
        ];

        $dropdown = new MenuDropdown($items, 5, 2);
        $dropdown->setFocused(true);

        // Navigate through all selectable items
        $labels = [];
        $dropdown->onSelect(static function (MenuItemInterface $item) use (&$labels): void {
            $labels[] = $item->getLabel();
        });

        // First item (Navigate)
        $dropdown->handleInput('ENTER');

        // Reset and navigate to second selectable (Execute)
        $dropdown = new MenuDropdown($items, 5, 2);
        $dropdown->setFocused(true);
        $dropdown->onSelect(static function (MenuItemInterface $item) use (&$labels): void {
            $labels[] = $item->getLabel();
        });
        $dropdown->handleInput('DOWN'); // Skip separator
        $dropdown->handleInput('ENTER');

        $this->assertContains('Navigate', $labels);
        $this->assertContains('Execute', $labels);
    }

    #[Test]
    public function submenu_items_are_accessible(): void
    {
        $subItems = [
            ScreenMenuItem::create('Sub Item 1', 'sub.1'),
            ActionMenuItem::create('Sub Item 2', static fn() => null),
        ];

        $submenu = SubmenuMenuItem::create('Parent Menu', $subItems, 'p');

        $this->assertSame('Parent Menu', $submenu->getLabel());
        $this->assertSame('p', $submenu->getHotkey());
        $this->assertCount(2, $submenu->items);
        $this->assertInstanceOf(ScreenMenuItem::class, $submenu->items[0]);
        $this->assertInstanceOf(ActionMenuItem::class, $submenu->items[1]);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->actionExecuted = false;
        $this->lastActionLabel = '';
    }

    // === Helper Methods ===

    private function renderDropdown(MenuDropdown $dropdown): void
    {
        $this->driver->initialize();
        $app = $this->createApp();
        $renderer = $app->getRenderer();

        $renderer->beginFrame();
        $dropdown->render($renderer, 0, 0, 80, 24);
        $renderer->endFrame();
    }
}
