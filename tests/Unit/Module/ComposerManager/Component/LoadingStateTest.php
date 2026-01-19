<?php

declare(strict_types=1);

namespace Tests\Unit\Module\ComposerManager\Component;

use Butschster\Commander\Module\ComposerManager\Component\LoadingState;
use PHPUnit\Framework\TestCase;

final class LoadingStateTest extends TestCase
{
    public function testIsNotLoadingByDefault(): void
    {
        $loadingState = new LoadingState();

        $this->assertFalse($loadingState->isLoading());
    }

    public function testStartEnablesLoadingState(): void
    {
        $loadingState = new LoadingState();

        $loadingState->start('Loading...');

        $this->assertTrue($loadingState->isLoading());
    }

    public function testStopDisablesLoadingState(): void
    {
        $loadingState = new LoadingState();
        $loadingState->start('Loading...');

        $loadingState->stop();

        $this->assertFalse($loadingState->isLoading());
    }

    public function testMultipleStartStopCycles(): void
    {
        $loadingState = new LoadingState();

        $loadingState->start('First');
        $this->assertTrue($loadingState->isLoading());

        $loadingState->stop();
        $this->assertFalse($loadingState->isLoading());

        $loadingState->start('Second');
        $this->assertTrue($loadingState->isLoading());

        $loadingState->stop();
        $this->assertFalse($loadingState->isLoading());
    }

    public function testUpdateDoesNotThrowWhenNotLoading(): void
    {
        $loadingState = new LoadingState();

        // Should not throw
        $loadingState->update();

        $this->assertFalse($loadingState->isLoading());
    }

    public function testUpdateDoesNotThrowWhenLoading(): void
    {
        $loadingState = new LoadingState();
        $loadingState->start('Loading...');

        // Should not throw
        $loadingState->update();

        $this->assertTrue($loadingState->isLoading());
    }

    public function testStopCanBeCalledMultipleTimes(): void
    {
        $loadingState = new LoadingState();
        $loadingState->start('Loading...');

        $loadingState->stop();
        $loadingState->stop();
        $loadingState->stop();

        $this->assertFalse($loadingState->isLoading());
    }
}
