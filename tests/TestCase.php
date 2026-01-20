<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case with common utilities.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Assert that array has exact keys.
     *
     * @param array<string> $expectedKeys
     * @param array<mixed> $actual
     */
    protected function assertArrayHasExactKeys(array $expectedKeys, array $actual, string $message = ''): void
    {
        $actualKeys = \array_keys($actual);
        \sort($expectedKeys);
        \sort($actualKeys);

        $this->assertSame($expectedKeys, $actualKeys, $message ?: 'Array keys do not match expected');
    }

    /**
     * Get private/protected property value for testing.
     */
    protected function getPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        return $prop->getValue($object);
    }

    /**
     * Set private/protected property value for testing.
     */
    protected function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setValue($object, $value);
    }
}
