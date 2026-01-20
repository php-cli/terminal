<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Exception;

use Butschster\Commander\SDK\Exception\CircularDependencyException;
use Butschster\Commander\SDK\Exception\ModuleConflictException;
use Butschster\Commander\SDK\Exception\ModuleDependencyException;
use Butschster\Commander\SDK\Exception\ModuleException;
use Butschster\Commander\SDK\Exception\ModuleNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ModuleException::class)]
#[CoversClass(ModuleNotFoundException::class)]
#[CoversClass(ModuleDependencyException::class)]
#[CoversClass(ModuleConflictException::class)]
#[CoversClass(CircularDependencyException::class)]
final class ModuleExceptionTest extends TestCase
{
    #[Test]
    public function module_exception_is_runtime_exception(): void
    {
        $exception = new ModuleException('Test error');

        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertSame('Test error', $exception->getMessage());
    }

    #[Test]
    public function module_not_found_exception_has_module_name(): void
    {
        $exception = new ModuleNotFoundException('file_browser');

        self::assertInstanceOf(ModuleException::class, $exception);
        self::assertSame('file_browser', $exception->moduleName);
        self::assertSame("Module 'file_browser' not found", $exception->getMessage());
    }

    #[Test]
    public function module_dependency_exception_has_module_and_dependency(): void
    {
        $exception = new ModuleDependencyException(
            moduleName: 'composer_manager',
            missingDependency: 'file_browser',
        );

        self::assertInstanceOf(ModuleException::class, $exception);
        self::assertSame('composer_manager', $exception->moduleName);
        self::assertSame('file_browser', $exception->missingDependency);
        self::assertSame(
            "Module 'composer_manager' requires 'file_browser' which is not registered",
            $exception->getMessage(),
        );
    }

    #[Test]
    public function module_conflict_exception_has_module_name(): void
    {
        $exception = new ModuleConflictException('core');

        self::assertInstanceOf(ModuleException::class, $exception);
        self::assertSame('core', $exception->moduleName);
        self::assertSame("Module 'core' is already registered", $exception->getMessage());
    }

    #[Test]
    public function circular_dependency_exception_has_chain(): void
    {
        $chain = ['module_a', 'module_b', 'module_c', 'module_a'];
        $exception = new CircularDependencyException($chain);

        self::assertInstanceOf(ModuleException::class, $exception);
        self::assertSame($chain, $exception->chain);
        self::assertSame(
            'Circular dependency detected: module_a -> module_b -> module_c -> module_a',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function circular_dependency_exception_with_short_chain(): void
    {
        $chain = ['module_a', 'module_a'];
        $exception = new CircularDependencyException($chain);

        self::assertSame(
            'Circular dependency detected: module_a -> module_a',
            $exception->getMessage(),
        );
    }
}
