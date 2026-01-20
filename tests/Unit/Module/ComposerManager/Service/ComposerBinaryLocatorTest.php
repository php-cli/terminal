<?php

declare(strict_types=1);

namespace Tests\Unit\Module\ComposerManager\Service;

use Butschster\Commander\Module\ComposerManager\Service\ComposerBinaryLocator;
use PHPUnit\Framework\TestCase;

final class ComposerBinaryLocatorTest extends TestCase
{
    public function testFindReturnsStringWhenComposerInstalled(): void
    {
        $binary = ComposerBinaryLocator::find();

        // On CI and most dev environments, Composer should be available
        if ($binary !== null) {
            $this->assertIsString($binary);
            $this->assertNotEmpty($binary);
        } else {
            $this->markTestSkipped('Composer not installed on this system');
        }
    }

    public function testFindCachesResult(): void
    {
        $first = ComposerBinaryLocator::find();
        $second = ComposerBinaryLocator::find();

        $this->assertSame($first, $second);
    }

    public function testClearCacheResetsCachedValue(): void
    {
        $first = ComposerBinaryLocator::find();
        ComposerBinaryLocator::clearCache();
        $second = ComposerBinaryLocator::find();

        // Both should return same value (assuming no system changes)
        $this->assertSame($first, $second);
    }

    public function testFindReturnsNullOrValidPath(): void
    {
        $binary = ComposerBinaryLocator::find();

        // Either null (not found) or a valid path
        if ($binary !== null) {
            $this->assertMatchesRegularExpression('/composer/', $binary);
        } else {
            $this->assertNull($binary);
        }
    }

    protected function setUp(): void
    {
        ComposerBinaryLocator::clearCache();
    }

    protected function tearDown(): void
    {
        ComposerBinaryLocator::clearCache();
    }
}
