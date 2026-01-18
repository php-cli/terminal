<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Module;

/**
 * Core contract for all modules.
 *
 * Modules are self-contained units that provide screens, menus,
 * services, and key bindings to the application.
 */
interface ModuleInterface
{
    /**
     * Get module metadata (name, version, dependencies).
     *
     * Called during registration to identify the module.
     */
    public function metadata(): ModuleMetadata;

    /**
     * Called once when application boots.
     *
     * Use this to:
     * - Initialize module-specific resources
     * - Register event listeners
     * - Perform one-time setup
     *
     * @param ModuleContext $context Access to container, config, services
     */
    public function boot(ModuleContext $context): void;

    /**
     * Called when application shuts down.
     *
     * Use this to:
     * - Close connections
     * - Cleanup temporary resources
     * - Save state if needed
     */
    public function shutdown(): void;
}
