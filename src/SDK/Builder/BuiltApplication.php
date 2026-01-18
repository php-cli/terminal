<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Builder;

use Butschster\Commander\Application;
use Butschster\Commander\SDK\Container\ContainerInterface;
use Butschster\Commander\SDK\Module\ModuleRegistry;
use Butschster\Commander\UI\Screen\ScreenInterface;
use Butschster\Commander\UI\Screen\ScreenRegistry;

/**
 * Configured application ready to run.
 *
 * Wraps the core Application with module lifecycle management.
 */
final readonly class BuiltApplication
{
    public function __construct(
        private Application $app,
        private ModuleRegistry $moduleRegistry,
        private ContainerInterface $container,
        private ScreenRegistry $screenRegistry,
        private ?string $initialScreen,
        private ?\Closure $onQuit,
    ) {
        // Wire quit callback
        if ($this->onQuit !== null) {
            $this->app->onAction('app.quit', $this->onQuit);
        } else {
            $this->app->onAction('app.quit', fn() => $this->app->stop());
        }
    }

    /**
     * Run the application.
     *
     * Blocks until application exits.
     */
    public function run(): void
    {
        $initialScreen = $this->getInitialScreen();

        if ($initialScreen === null) {
            throw new \RuntimeException('No screens registered');
        }

        try {
            $this->app->run($initialScreen);
        } finally {
            $this->moduleRegistry->shutdown();
        }
    }

    /**
     * Get the DI container.
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Get the module registry.
     */
    public function getModuleRegistry(): ModuleRegistry
    {
        return $this->moduleRegistry;
    }

    /**
     * Get the screen registry.
     */
    public function getScreenRegistry(): ScreenRegistry
    {
        return $this->screenRegistry;
    }

    /**
     * Get the inner Application (for testing).
     */
    public function getInnerApplication(): Application
    {
        return $this->app;
    }

    /**
     * Get the initial screen name.
     */
    public function getInitialScreenName(): ?string
    {
        return $this->initialScreen;
    }

    private function getInitialScreen(): ?ScreenInterface
    {
        if ($this->initialScreen === null) {
            return null;
        }

        return $this->screenRegistry->getScreen($this->initialScreen);
    }
}
