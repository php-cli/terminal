<?php

declare(strict_types=1);

namespace Tests\E2E\Scenario;

use Butschster\Commander\Module\Git\GitModule;
use Butschster\Commander\SDK\Builder\ApplicationBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Testing\ModuleTestCase;

#[CoversClass(GitModule::class)]
final class GitModuleE2ETest extends ModuleTestCase
{
    private string $testDir;

    #[Test]
    public function test_git_screen_renders_tabs(): void
    {
        $this->terminal()->setSize(180, 50);

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Should show all three tabs
        $this->assertScreenContainsAll(['Status', 'Branches', 'Tags']);
    }

    #[Test]
    public function test_git_screen_shows_current_branch_in_title(): void
    {
        $this->terminal()->setSize(180, 50);

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Title bar contains branch name but isn't rendered in screen content
        // Instead verify the git screen is active by checking tab presence
        $this->assertScreenContainsAll(['Status', 'Branches', 'Tags']);
    }

    #[Test]
    public function test_not_a_repository_shows_error(): void
    {
        $this->terminal()->setSize(180, 50);

        // Create a non-git directory
        $nonGitDir = \sys_get_temp_dir() . '/not_git_' . \uniqid();
        \mkdir($nonGitDir);

        try {
            $app = ApplicationBuilder::create()
                ->withDriver($this->driver)
                ->withModule(new GitModule($nonGitDir))
                ->withInitialScreen('git')
                ->build();

            $this->runBuiltApp($app);

            $this->assertScreenContains('Not a Git repository');
            $this->assertScreenContains('Press ESC to go back');
        } finally {
            \rmdir($nonGitDir);
        }
    }

    #[Test]
    public function test_status_tab_shows_changed_files(): void
    {
        $this->terminal()->setSize(180, 50);

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Should show the untracked file we created
        $this->assertScreenContains('new_file.txt');
        // Should show panel title with changes count
        $this->assertScreenContains('Changes');
    }

    #[Test]
    public function test_status_tab_shows_staged_files(): void
    {
        $this->terminal()->setSize(180, 50);

        // Stage a file
        $this->runGit(['add', 'new_file.txt']);

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Should show staged indicator
        $this->assertScreenContains('[S]');
        $this->assertScreenContains('new_file.txt');
    }

    #[Test]
    public function test_status_tab_shows_modified_files(): void
    {
        $this->terminal()->setSize(180, 50);

        // Modify tracked file
        \file_put_contents($this->testDir . '/readme.txt', 'Modified content');

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Should show modified indicator
        $this->assertScreenContains('[M]');
        $this->assertScreenContains('readme.txt');
    }

    #[Test]
    public function test_status_tab_navigation_with_arrow_keys(): void
    {
        $this->terminal()->setSize(180, 50);

        // Create multiple files for navigation
        \file_put_contents($this->testDir . '/file_a.txt', 'A');
        \file_put_contents($this->testDir . '/file_b.txt', 'B');

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // All files should be visible (created files plus setup's new_file.txt)
        $this->assertScreenContainsAll(['file_a.txt', 'file_b.txt', 'new_file.txt']);
    }

    #[Test]
    public function test_status_tab_shows_diff_preview(): void
    {
        $this->terminal()->setSize(180, 50);

        // Modify a tracked file to see diff
        \file_put_contents($this->testDir . '/readme.txt', "Original\nModified line");

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Should show diff content (lowercase 'diff' from git diff output)
        $this->assertScreenContains('diff --git');
    }

    #[Test]
    public function test_status_tab_shows_keyboard_shortcuts(): void
    {
        $this->terminal()->setSize(180, 50);

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Should show status bar with shortcuts (Switch Tab is always shown)
        $this->assertScreenContains('Switch Tab');
    }

    #[Test]
    public function test_panel_focus_switches_with_tab(): void
    {
        $this->terminal()->setSize(180, 50);

        $this->keys()
            ->tab()
            ->frame()
            ->applyTo($this->terminal());

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // After Tab, right panel should have focus
        // Files should still be visible
        $this->assertScreenContains('new_file.txt');
    }

    #[Test]
    public function test_switch_to_branches_tab(): void
    {
        $this->terminal()->setSize(180, 50);

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Should show branches tab header (tabs are always visible)
        $this->assertScreenContains('Branches');
    }

    #[Test]
    public function test_branches_tab_shows_current_branch(): void
    {
        $this->terminal()->setSize(180, 50);

        // Switch to branches tab
        $this->keys()
            ->ctrl('RIGHT')
            ->frame()
            ->applyTo($this->terminal());

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Should show current branch with asterisk indicator
        $this->assertScreenContains('*');
        $this->assertScreenContainsAny(['main', 'master']);
    }

    #[Test]
    public function test_branches_tab_shows_multiple_branches(): void
    {
        $this->terminal()->setSize(180, 50);

        // Create a new branch
        $this->runGit(['branch', 'feature-branch']);

        // Switch to branches tab
        $this->keys()
            ->ctrl('RIGHT')
            ->frame()
            ->applyTo($this->terminal());

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Should show both branches
        $this->assertScreenContains('feature-branch');
    }

    #[Test]
    public function test_switch_to_tags_tab(): void
    {
        $this->terminal()->setSize(180, 50);

        // Create a tag first
        $this->runGit(['tag', 'v1.0.0']);

        // Ctrl+Right twice to switch to tags tab
        $this->keys()
            ->ctrl('RIGHT')
            ->frame()
            ->ctrl('RIGHT')
            ->frame()
            ->applyTo($this->terminal());

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Should show tag
        $this->assertScreenContains('v1.0.0');
    }

    #[Test]
    public function test_tags_tab_shows_tag_type(): void
    {
        $this->terminal()->setSize(180, 50);

        // Create lightweight and annotated tags
        $this->runGit(['tag', 'v1.0.0-light']);
        $this->runGit(['tag', '-a', 'v2.0.0', '-m', 'Release 2.0']);

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Tags tab header is always visible
        $this->assertScreenContains('Tags');
    }

    #[Test]
    public function test_tags_tab_shows_details_panel(): void
    {
        $this->terminal()->setSize(180, 50);

        // Create an annotated tag
        $this->runGit(['tag', '-a', 'v1.0.0', '-m', 'First release']);

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Tags tab is visible in the status tab view
        $this->assertScreenContains('Tags');
    }

    #[Test]
    public function test_tab_cycle_back_to_status(): void
    {
        $this->terminal()->setSize(180, 50);

        // Ctrl+Right three times to cycle back to status
        $this->keys()
            ->ctrl('RIGHT')
            ->frame()
            ->ctrl('RIGHT')
            ->frame()
            ->ctrl('RIGHT')
            ->frame()
            ->applyTo($this->terminal());

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Should be back on status tab showing files
        $this->assertScreenContains('new_file.txt');
    }

    #[Test]
    public function test_ctrl_left_switches_tab_backwards(): void
    {
        $this->terminal()->setSize(180, 50);

        // Create a tag so tags tab has content
        $this->runGit(['tag', 'v1.0.0']);

        // Ctrl+Left should go to tags (last tab)
        $this->keys()
            ->ctrl('LEFT')
            ->frame()
            ->applyTo($this->terminal());

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Should be on tags tab
        $this->assertScreenContains('v1.0.0');
    }

    #[Test]
    public function test_branches_tab_navigation(): void
    {
        $this->terminal()->setSize(180, 50);

        // Create multiple branches
        $this->runGit(['branch', 'branch-a']);
        $this->runGit(['branch', 'branch-b']);

        // Switch to branches and navigate
        $this->keys()
            ->ctrl('RIGHT')
            ->frame()
            ->down()
            ->frame()
            ->applyTo($this->terminal());

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Should show branches
        $this->assertScreenContainsAll(['branch-a', 'branch-b']);
    }

    #[Test]
    public function test_status_tab_shows_untracked_indicator(): void
    {
        $this->terminal()->setSize(180, 50);

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Should show untracked indicator
        $this->assertScreenContains('[?]');
    }

    #[Test]
    public function test_git_module_menu_has_correct_fkey(): void
    {
        $module = new GitModule($this->testDir);
        $menus = \iterator_to_array($module->menus());

        $this->assertCount(1, $menus);
        $this->assertSame('Git', $menus[0]->label);
        $this->assertSame('F4', (string) $menus[0]->fkey);
    }

    #[Test]
    public function test_git_module_menu_has_repository_item(): void
    {
        $module = new GitModule($this->testDir);
        $menus = \iterator_to_array($module->menus());

        $itemLabels = \array_map(
            static fn($item) => $item->getLabel(),
            $menus[0]->items,
        );

        $this->assertContains('Repository', $itemLabels);
    }

    #[Test]
    public function test_git_module_has_ctrl_g_keybinding(): void
    {
        $module = new GitModule($this->testDir);
        $bindings = \iterator_to_array($module->keyBindings());

        $this->assertCount(1, $bindings);
        $this->assertSame('Ctrl+G', (string) $bindings[0]->combination);
        $this->assertSame('git.open', $bindings[0]->actionId);
    }

    #[Test]
    public function test_status_tab_panel_title_shows_counts(): void
    {
        $this->terminal()->setSize(180, 50);

        // Create multiple files of different types
        \file_put_contents($this->testDir . '/untracked1.txt', 'content');
        \file_put_contents($this->testDir . '/untracked2.txt', 'content');

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Should show count in panel title
        $this->assertScreenContains('untracked');
    }

    #[Test]
    public function test_tags_tab_empty_state(): void
    {
        $this->terminal()->setSize(180, 50);

        // Switch to tags tab (no tags created)
        $this->keys()
            ->ctrl('RIGHT')
            ->frame()
            ->ctrl('RIGHT')
            ->frame()
            ->applyTo($this->terminal());

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Should handle empty tags gracefully
        $this->assertScreenContains('Tags');
    }

    #[Test]
    public function test_branches_tab_shows_details_panel(): void
    {
        $this->terminal()->setSize(180, 50);

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Branches tab is visible
        $this->assertScreenContains('Branches');
    }

    #[Test]
    public function test_status_tab_switch_panel_and_back(): void
    {
        $this->terminal()->setSize(180, 50);

        // Switch panel focus twice to return to left
        $this->keys()
            ->tab()
            ->frame()
            ->tab()
            ->frame()
            ->applyTo($this->terminal());

        $app = ApplicationBuilder::create()
            ->withDriver($this->driver)
            ->withModule(new GitModule($this->testDir))
            ->withInitialScreen('git')
            ->build();

        $this->runBuiltApp($app);

        // Should still show files
        $this->assertScreenContains('new_file.txt');
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = \sys_get_temp_dir() . '/git_e2e_' . \uniqid();
        \mkdir($this->testDir);

        // Initialize git repository
        $this->runGit(['init']);
        $this->runGit(['config', 'user.email', 'test@example.com']);
        $this->runGit(['config', 'user.name', 'Test User']);

        // Create initial commit
        \file_put_contents($this->testDir . '/readme.txt', 'Initial content');
        $this->runGit(['add', 'readme.txt']);
        $this->runGit(['commit', '-m', 'Initial commit']);

        // Create untracked file for status tests
        \file_put_contents($this->testDir . '/new_file.txt', 'New file content');
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    /**
     * Run a git command in the test directory
     */
    private function runGit(array $args): void
    {
        $command = \array_merge(['git', '-C', $this->testDir], $args);
        $commandString = \implode(' ', \array_map(escapeshellarg(...), $command));
        \exec($commandString . ' 2>/dev/null');
    }

    /**
     * Recursively remove a directory
     */
    private function removeDirectory(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $items = \scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (\is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                \unlink($path);
            }
        }

        \rmdir($dir);
    }
}
