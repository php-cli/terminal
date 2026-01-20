<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Git;

use Butschster\Commander\Module\Git\GitModule;
use Butschster\Commander\Module\Git\Screen\GitScreen;
use Butschster\Commander\Module\Git\Service\GitService;
use Butschster\Commander\SDK\Container\Container;
use Butschster\Commander\SDK\Container\ServiceDefinition;
use Butschster\Commander\SDK\Provider\KeyBindingProviderInterface;
use Butschster\Commander\SDK\Provider\MenuProviderInterface;
use Butschster\Commander\SDK\Provider\ScreenProviderInterface;
use Butschster\Commander\SDK\Provider\ServiceProviderInterface;
use Butschster\Commander\UI\Screen\ScreenManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GitModule::class)]
final class GitModuleTest extends TestCase
{
    #[Test]
    public function it_has_correct_metadata(): void
    {
        $module = new GitModule();
        $metadata = $module->metadata();

        $this->assertSame('git', $metadata->name);
        $this->assertSame('Git', $metadata->title);
        $this->assertSame('1.0.0', $metadata->version);
        $this->assertEmpty($metadata->dependencies);
    }

    #[Test]
    public function it_implements_all_provider_interfaces(): void
    {
        $module = new GitModule();

        $this->assertInstanceOf(ServiceProviderInterface::class, $module);
        $this->assertInstanceOf(ScreenProviderInterface::class, $module);
        $this->assertInstanceOf(MenuProviderInterface::class, $module);
        $this->assertInstanceOf(KeyBindingProviderInterface::class, $module);
    }

    #[Test]
    public function it_provides_git_service(): void
    {
        $module = new GitModule('/tmp');
        $services = \iterator_to_array($module->services());

        $this->assertCount(1, $services);
        $this->assertInstanceOf(ServiceDefinition::class, $services[0]);
        $this->assertSame(GitService::class, $services[0]->id);
        $this->assertTrue($services[0]->singleton);
    }

    #[Test]
    public function it_provides_git_screen(): void
    {
        $module = new GitModule('/tmp');

        $container = new Container();
        $container->singleton(
            GitService::class,
            static fn() => new GitService('/tmp'),
        );
        $container->instance(ScreenManager::class, new ScreenManager());

        $screens = \iterator_to_array($module->screens($container));

        $this->assertCount(1, $screens);
        $this->assertInstanceOf(GitScreen::class, $screens[0]);
    }

    #[Test]
    public function it_provides_git_menu(): void
    {
        $module = new GitModule();
        $menus = \iterator_to_array($module->menus());

        $this->assertCount(1, $menus);
        $this->assertSame('Git', $menus[0]->label);
        $this->assertSame('F4', (string) $menus[0]->fkey);
        $this->assertSame(40, $menus[0]->priority);
    }

    #[Test]
    public function it_provides_key_bindings(): void
    {
        $module = new GitModule();
        $bindings = \iterator_to_array($module->keyBindings());

        $this->assertCount(1, $bindings);
        $this->assertSame('git.open', $bindings[0]->actionId);
        $this->assertSame('Ctrl+G', (string) $bindings[0]->combination);
    }

    #[Test]
    public function it_uses_custom_repository_path(): void
    {
        $customPath = '/custom/path';
        $module = new GitModule($customPath);

        $container = new Container();
        $container->instance(ScreenManager::class, new ScreenManager());

        // Register services
        foreach ($module->services() as $definition) {
            $container->singleton($definition->id, $definition->factory);
        }

        /** @var GitService $service */
        $service = $container->get(GitService::class);

        $this->assertSame($customPath, $service->getRepositoryPath());
    }

    #[Test]
    public function it_defaults_to_cwd_when_no_path_provided(): void
    {
        $module = new GitModule();

        $container = new Container();
        $container->instance(ScreenManager::class, new ScreenManager());

        // Register services
        foreach ($module->services() as $definition) {
            $container->singleton($definition->id, $definition->factory);
        }

        /** @var GitService $service */
        $service = $container->get(GitService::class);

        $this->assertSame(\getcwd(), $service->getRepositoryPath());
    }
}
