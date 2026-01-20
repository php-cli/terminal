<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Keyboard\Mapping;

use Butschster\Commander\Infrastructure\Keyboard\Key;
use Butschster\Commander\Infrastructure\Keyboard\Modifier;

/**
 * Registry of all key sequence mappings.
 *
 * Provides fast lookup of raw terminal sequences to logical key representations.
 * Supports multiple sequences for the same key (terminal compatibility).
 */
final class KeyMappingRegistry
{
    /** @var array<string, KeySequence> Indexed by sequence for fast lookup */
    private array $bySequence = [];

    /** @var array<string, list<KeySequence>> Indexed by key name */
    private array $byKeyName = [];

    public function __construct(bool $registerDefaults = true)
    {
        if ($registerDefaults) {
            $this->registerDefaults();
        }
    }

    /**
     * Register a key sequence mapping.
     */
    public function register(KeySequence $mapping): self
    {
        $this->bySequence[$mapping->sequence] = $mapping;
        $this->byKeyName[$mapping->toKeyName()][] = $mapping;

        return $this;
    }

    /**
     * Register multiple mappings at once.
     *
     * @param iterable<KeySequence> $mappings
     */
    public function registerAll(iterable $mappings): self
    {
        foreach ($mappings as $mapping) {
            $this->register($mapping);
        }

        return $this;
    }

    /**
     * Find mapping by raw sequence.
     */
    public function findBySequence(string $sequence): ?KeySequence
    {
        return $this->bySequence[$sequence] ?? null;
    }

    /**
     * Find all mappings for a key name.
     *
     * @return list<KeySequence>
     */
    public function findByKeyName(string $keyName): array
    {
        return $this->byKeyName[$keyName] ?? [];
    }

    /**
     * Get all mappings of a specific type.
     *
     * @return list<KeySequence>
     */
    public function getByType(SequenceType $type): array
    {
        return \array_values(\array_filter(
            $this->bySequence,
            static fn(KeySequence $m) => $m->type === $type,
        ));
    }

    /**
     * Get all mappings for a specific terminal type.
     *
     * @return list<KeySequence>
     */
    public function getByTerminal(TerminalType $terminal): array
    {
        return \array_values(\array_filter(
            $this->bySequence,
            static fn(KeySequence $m) => $m->terminal === $terminal,
        ));
    }

    /**
     * Get all registered mappings.
     *
     * @return array<string, KeySequence>
     */
    public function all(): array
    {
        return $this->bySequence;
    }

    /**
     * Get count of registered mappings.
     */
    public function count(): int
    {
        return \count($this->bySequence);
    }

    /**
     * Check if a sequence is registered.
     */
    public function has(string $sequence): bool
    {
        return isset($this->bySequence[$sequence]);
    }

    private function registerDefaults(): void
    {
        $this->registerNavigation();
        $this->registerFunctionKeys();
        $this->registerSpecialKeys();
        $this->registerCtrlCombinations();
    }

    private function registerNavigation(): void
    {
        // Arrow keys
        $this->register(KeySequence::escape("\033[A", Key::UP));
        $this->register(KeySequence::escape("\033[B", Key::DOWN));
        $this->register(KeySequence::escape("\033[C", Key::RIGHT));
        $this->register(KeySequence::escape("\033[D", Key::LEFT));

        // Ctrl+Arrow keys
        $this->register(KeySequence::escape("\033[1;5A", Key::UP, [Modifier::CTRL]));
        $this->register(KeySequence::escape("\033[1;5B", Key::DOWN, [Modifier::CTRL]));
        $this->register(KeySequence::escape("\033[1;5C", Key::RIGHT, [Modifier::CTRL]));
        $this->register(KeySequence::escape("\033[1;5D", Key::LEFT, [Modifier::CTRL]));

        // Page navigation
        $this->register(KeySequence::escape("\033[5~", Key::PAGE_UP));
        $this->register(KeySequence::escape("\033[6~", Key::PAGE_DOWN));
        $this->register(KeySequence::escape("\033[1~", Key::HOME));
        $this->register(KeySequence::escape("\033[4~", Key::END));
        $this->register(KeySequence::escape("\033[2~", Key::INSERT));
        $this->register(KeySequence::escape("\033[3~", Key::DELETE));
    }

    private function registerFunctionKeys(): void
    {
        // F1-F4 (xterm style - ESC O P/Q/R/S)
        $this->register(KeySequence::escape("\033OP", Key::F1, terminal: TerminalType::Xterm));
        $this->register(KeySequence::escape("\033OQ", Key::F2, terminal: TerminalType::Xterm));
        $this->register(KeySequence::escape("\033OR", Key::F3, terminal: TerminalType::Xterm));
        $this->register(KeySequence::escape("\033OS", Key::F4, terminal: TerminalType::Xterm));

        // F1-F4 (linux console style - ESC [ 11~ etc.)
        $this->register(KeySequence::escape("\033[11~", Key::F1, terminal: TerminalType::Linux));
        $this->register(KeySequence::escape("\033[12~", Key::F2, terminal: TerminalType::Linux));
        $this->register(KeySequence::escape("\033[13~", Key::F3, terminal: TerminalType::Linux));
        $this->register(KeySequence::escape("\033[14~", Key::F4, terminal: TerminalType::Linux));

        // F5-F12 (common across terminals)
        $this->register(KeySequence::escape("\033[15~", Key::F5));
        $this->register(KeySequence::escape("\033[17~", Key::F6));
        $this->register(KeySequence::escape("\033[18~", Key::F7));
        $this->register(KeySequence::escape("\033[19~", Key::F8));
        $this->register(KeySequence::escape("\033[20~", Key::F9));
        $this->register(KeySequence::escape("\033[21~", Key::F10));
        $this->register(KeySequence::escape("\033[23~", Key::F11));
        $this->register(KeySequence::escape("\033[24~", Key::F12));
    }

    private function registerSpecialKeys(): void
    {
        // Enter key (multiple representations for cross-platform)
        // IMPORTANT: These must be checked BEFORE Ctrl combinations
        // because \n (LF) is the same as Ctrl+J
        $this->register(KeySequence::special("\n", Key::ENTER, 'Line feed (Unix/Linux)'));
        $this->register(KeySequence::special("\r", Key::ENTER, 'Carriage return'));
        $this->register(KeySequence::special("\r\n", Key::ENTER, 'CRLF (Windows)'));

        // Tab, Escape, Backspace
        $this->register(KeySequence::special("\t", Key::TAB));
        $this->register(KeySequence::special("\033", Key::ESCAPE, 'Escape (standalone)'));
        $this->register(KeySequence::special("\177", Key::BACKSPACE, 'DEL character'));
        $this->register(KeySequence::special("\010", Key::BACKSPACE, 'BS character'));
    }

    private function registerCtrlCombinations(): void
    {
        // Ctrl+A through Ctrl+Z (ASCII codes 1-26)
        // Note: Some codes overlap with special keys and are intentionally skipped:
        // - Ctrl+H (\010) = Backspace
        // - Ctrl+I (\011) = Tab
        // - Ctrl+J (\012) = Line feed (Enter)
        // - Ctrl+M (\015) = Carriage return (Enter)

        $this->register(KeySequence::ctrl("\001", Key::A));
        $this->register(KeySequence::ctrl("\002", Key::B));
        $this->register(KeySequence::ctrl("\003", Key::C, 'Interrupt signal'));
        $this->register(KeySequence::ctrl("\004", Key::D, 'EOF signal'));
        $this->register(KeySequence::ctrl("\005", Key::E));
        $this->register(KeySequence::ctrl("\006", Key::F));
        $this->register(KeySequence::ctrl("\007", Key::G, 'Bell'));
        // \010 = Backspace (registered as special key)
        // \011 = Tab (registered as special key)
        // \012 = Enter/LF (registered as special key)
        $this->register(KeySequence::ctrl("\013", Key::K));
        $this->register(KeySequence::ctrl("\014", Key::L, 'Form feed / Clear screen'));
        // \015 = Enter/CR (registered as special key)
        $this->register(KeySequence::ctrl("\016", Key::N));
        $this->register(KeySequence::ctrl("\017", Key::O));
        $this->register(KeySequence::ctrl("\020", Key::P));
        $this->register(KeySequence::ctrl("\021", Key::Q, 'XON / Resume'));
        $this->register(KeySequence::ctrl("\022", Key::R));
        $this->register(KeySequence::ctrl("\023", Key::S, 'XOFF / Pause'));
        $this->register(KeySequence::ctrl("\024", Key::T));
        $this->register(KeySequence::ctrl("\025", Key::U));
        $this->register(KeySequence::ctrl("\026", Key::V));
        $this->register(KeySequence::ctrl("\027", Key::W));
        $this->register(KeySequence::ctrl("\030", Key::X));
        $this->register(KeySequence::ctrl("\031", Key::Y));
        $this->register(KeySequence::ctrl("\032", Key::Z, 'Suspend signal'));
    }
}
