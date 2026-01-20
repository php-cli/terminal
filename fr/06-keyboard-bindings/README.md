# Feature Request: Keyboard Binding Architecture Refactoring

## Status: Ready for Implementation

## Problem

Current keyboard handling has 71+ magic string comparisons scattered across 21 files,
hardcoded F10 conflicts with GNOME Terminal, and no centralized management.

## Solution

Type-safe `Key` enum + centralized `KeyBindingRegistry` with F12 for quit.

## Documents

| Document | Description |
|----------|-------------|
| [Problem Analysis](01-problem-analysis.md) | Detailed analysis of current issues |
| [Architecture](02-architecture.md) | Proposed system design |
| [Master Checklist](master-checklist.md) | Implementation progress tracker |

## Stage Documents

| Stage | Document | Focus |
|-------|----------|-------|
| 1 | [Core Types](stage-01-core-types.md) | Key enum, KeyCombination |
| 2 | [Binding System](stage-02-binding-system.md) | KeyBinding, Registry |
| 3 | [KeyboardHandler](stage-03-keyboard-handler.md) | Integration |
| 4 | [MenuBuilder](stage-04-menu-builder.md) | Menu refactoring |
| 5 | [Application](stage-05-application.md) | Global shortcuts |
| 6 | [Screens](stage-06-screen-refactoring.md) | Component refactoring |
| 7 | [Documentation](stage-07-documentation.md) | Help & cleanup |

## Key Changes

| Before | After | Reason |
|--------|-------|--------|
| F10 for Quit | **F12** for Quit | GNOME Terminal conflict |
| Magic strings | `Key` enum | Type safety |
| Hardcoded shortcuts | `KeyBindingRegistry` | Centralized management |
| Static help text | Auto-generated | Always synchronized |

## Scope

- **8 new files** in `src/Infrastructure/Keyboard/`
- **21 files modified** (screens, components, core)
- **7 stages** of implementation
