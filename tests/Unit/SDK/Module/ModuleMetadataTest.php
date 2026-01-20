<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Module;

use Butschster\Commander\SDK\Module\ModuleMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ModuleMetadata::class)]
final class ModuleMetadataTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated_with_required_properties(): void
    {
        $metadata = new ModuleMetadata(
            name: 'file_browser',
            title: 'File Browser',
        );

        self::assertSame('file_browser', $metadata->name);
        self::assertSame('File Browser', $metadata->title);
        self::assertSame('1.0.0', $metadata->version);
        self::assertSame([], $metadata->dependencies);
    }

    #[Test]
    public function it_can_be_instantiated_with_all_properties(): void
    {
        $metadata = new ModuleMetadata(
            name: 'composer_manager',
            title: 'Composer Manager',
            version: '2.1.0',
            dependencies: ['file_browser', 'terminal'],
        );

        self::assertSame('composer_manager', $metadata->name);
        self::assertSame('Composer Manager', $metadata->title);
        self::assertSame('2.1.0', $metadata->version);
        self::assertSame(['file_browser', 'terminal'], $metadata->dependencies);
    }

    #[Test]
    public function it_has_default_version(): void
    {
        $metadata = new ModuleMetadata(
            name: 'test_module',
            title: 'Test Module',
        );

        self::assertSame('1.0.0', $metadata->version);
    }

    #[Test]
    public function it_has_empty_dependencies_by_default(): void
    {
        $metadata = new ModuleMetadata(
            name: 'test_module',
            title: 'Test Module',
        );

        self::assertSame([], $metadata->dependencies);
    }
}
