<?php

declare(strict_types=1);

namespace Butschster\Commander\SDK\Provider;

use Butschster\Commander\Infrastructure\Keyboard\KeyBinding;

/**
 * Modules implement this to provide global key bindings.
 *
 * Key bindings are shortcuts that work application-wide,
 * not just within a specific screen.
 */
interface KeyBindingProviderInterface
{
    /**
     * Provide key bindings.
     *
     * Bindings map key combinations to action IDs.
     * The application handles action execution.
     *
     * @return iterable<KeyBinding>
     */
    public function keyBindings(): iterable;
}
