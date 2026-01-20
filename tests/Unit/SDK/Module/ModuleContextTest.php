<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Module;

use Butschster\Commander\SDK\Container\Container;
use Butschster\Commander\SDK\Container\ContainerInterface;
use Butschster\Commander\SDK\Module\ModuleContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ModuleContext::class)]
final class ModuleContextTest extends TestCase
{
    private ContainerInterface $container;

    #[Test]
    public function it_provides_access_to_container(): void
    {
        $context = new ModuleContext($this->container);

        self::assertSame($this->container, $context->container);
    }

    #[Test]
    public function it_can_be_instantiated_with_empty_config(): void
    {
        $context = new ModuleContext($this->container);

        self::assertNull($context->config('any.key'));
    }

    #[Test]
    public function it_retrieves_top_level_config_value(): void
    {
        $context = new ModuleContext($this->container, [
            'debug' => true,
            'name' => 'app',
        ]);

        self::assertTrue($context->config('debug'));
        self::assertSame('app', $context->config('name'));
    }

    #[Test]
    public function it_retrieves_nested_config_value_using_dot_notation(): void
    {
        $context = new ModuleContext($this->container, [
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
                'credentials' => [
                    'user' => 'root',
                    'password' => 'secret',
                ],
            ],
        ]);

        self::assertSame('localhost', $context->config('database.host'));
        self::assertSame(3306, $context->config('database.port'));
        self::assertSame('root', $context->config('database.credentials.user'));
        self::assertSame('secret', $context->config('database.credentials.password'));
    }

    #[Test]
    public function it_returns_default_when_key_not_found(): void
    {
        $context = new ModuleContext($this->container, [
            'existing' => 'value',
        ]);

        self::assertNull($context->config('missing'));
        self::assertSame('default', $context->config('missing', 'default'));
        self::assertSame(42, $context->config('missing', 42));
    }

    #[Test]
    public function it_returns_default_when_nested_key_not_found(): void
    {
        $context = new ModuleContext($this->container, [
            'database' => [
                'host' => 'localhost',
            ],
        ]);

        self::assertNull($context->config('database.port'));
        self::assertSame(5432, $context->config('database.port', 5432));
        self::assertNull($context->config('database.credentials.user'));
        self::assertSame('guest', $context->config('database.credentials.user', 'guest'));
    }

    #[Test]
    public function it_returns_default_when_intermediate_key_is_not_array(): void
    {
        $context = new ModuleContext($this->container, [
            'database' => 'sqlite',
        ]);

        self::assertNull($context->config('database.host'));
        self::assertSame('fallback', $context->config('database.host', 'fallback'));
    }

    #[Test]
    public function it_can_return_array_values(): void
    {
        $context = new ModuleContext($this->container, [
            'modules' => ['core', 'auth', 'api'],
            'settings' => [
                'features' => ['dark_mode', 'notifications'],
            ],
        ]);

        self::assertSame(['core', 'auth', 'api'], $context->config('modules'));
        self::assertSame(['dark_mode', 'notifications'], $context->config('settings.features'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }
}
