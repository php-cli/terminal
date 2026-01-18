<?php

declare(strict_types=1);

namespace Butschster\Commander\Infrastructure\Keyboard;

/**
 * Enum representing all keyboard keys supported by the application.
 *
 * Maps to the string constants used in KeyboardHandler::KEY_MAPPINGS.
 */
enum Key: string
{
    // Navigation keys
    case UP = 'UP';
    case DOWN = 'DOWN';
    case LEFT = 'LEFT';
    case RIGHT = 'RIGHT';
    case HOME = 'HOME';
    case END = 'END';
    case PAGE_UP = 'PAGE_UP';
    case PAGE_DOWN = 'PAGE_DOWN';

    // Function keys
    case F1 = 'F1';
    case F2 = 'F2';
    case F3 = 'F3';
    case F4 = 'F4';
    case F5 = 'F5';
    case F6 = 'F6';
    case F7 = 'F7';
    case F8 = 'F8';
    case F9 = 'F9';
    case F10 = 'F10';
    case F11 = 'F11';
    case F12 = 'F12';

    // Special keys
    case ENTER = 'ENTER';
    case ESCAPE = 'ESCAPE';
    case TAB = 'TAB';
    case SPACE = 'SPACE';
    case BACKSPACE = 'BACKSPACE';
    case DELETE = 'DELETE';
    case INSERT = 'INSERT';

    // Letters (A-Z) for Ctrl+Letter combinations
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
    case E = 'E';
    case F = 'F';
    case G = 'G';
    case H = 'H';
    case I = 'I';
    case J = 'J';
    case K = 'K';
    case L = 'L';
    case M = 'M';
    case N = 'N';
    case O = 'O';
    case P = 'P';
    case Q = 'Q';
    case R = 'R';
    case S = 'S';
    case T = 'T';
    case U = 'U';
    case V = 'V';
    case W = 'W';
    case X = 'X';
    case Y = 'Y';
    case Z = 'Z';

    // Digits (0-9) for Modal quick-select, prefixed with D to avoid PHP naming conflicts
    case D0 = '0';
    case D1 = '1';
    case D2 = '2';
    case D3 = '3';
    case D4 = '4';
    case D5 = '5';
    case D6 = '6';
    case D7 = '7';
    case D8 = '8';
    case D9 = '9';

    /**
     * Try to create a Key from a raw KeyboardHandler string.
     */
    public static function tryFromRaw(string $rawKey): ?self
    {
        // Handle CTRL_ prefixed keys - extract the letter
        if (\str_starts_with($rawKey, 'CTRL_')) {
            $letter = \substr($rawKey, 5);
            if (\strlen($letter) === 1 && \ctype_alpha($letter)) {
                return self::tryFrom(\strtoupper($letter));
            }
            // Handle CTRL_UP, CTRL_DOWN, etc.
            return self::tryFrom($letter);
        }

        // Direct mapping for most keys
        return self::tryFrom($rawKey);
    }

    /**
     * Check if this is a navigation key (arrows, home, end, page up/down).
     */
    public function isNavigation(): bool
    {
        return match ($this) {
            self::UP, self::DOWN, self::LEFT, self::RIGHT,
            self::HOME, self::END, self::PAGE_UP, self::PAGE_DOWN => true,
            default => false,
        };
    }

    /**
     * Check if this is a function key (F1-F12).
     */
    public function isFunctionKey(): bool
    {
        return match ($this) {
            self::F1, self::F2, self::F3, self::F4, self::F5, self::F6,
            self::F7, self::F8, self::F9, self::F10, self::F11, self::F12 => true,
            default => false,
        };
    }

    /**
     * Check if this is a letter key (A-Z).
     */
    public function isLetter(): bool
    {
        return match ($this) {
            self::A, self::B, self::C, self::D, self::E, self::F, self::G,
            self::H, self::I, self::J, self::K, self::L, self::M, self::N,
            self::O, self::P, self::Q, self::R, self::S, self::T, self::U,
            self::V, self::W, self::X, self::Y, self::Z => true,
            default => false,
        };
    }

    /**
     * Check if this is a digit key (0-9).
     */
    public function isDigit(): bool
    {
        return match ($this) {
            self::D0, self::D1, self::D2, self::D3, self::D4,
            self::D5, self::D6, self::D7, self::D8, self::D9 => true,
            default => false,
        };
    }
}
