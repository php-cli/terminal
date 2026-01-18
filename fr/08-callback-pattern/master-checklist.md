# Feature: Improve Callback Pattern (Remove Callback Hell)

## Overview

Replace multiple nullable callback properties with a cleaner pattern. Currently, components like `TableComponent` and
`ListComponent` have multiple `$onSelect`, `$onChange` callbacks with repetitive null-check invocations.

## Stage Dependencies

```
Stage 1 (Event System) → Stage 2 (Update Components) → Stage 3 (Documentation)
```

## Development Progress

### Stage 1: Create Lightweight Event System

- [ ] Substep 1.1: Create `ComponentEvent` base class
- [ ] Substep 1.2: Create `SelectionChangedEvent` for list/table selection
- [ ] Substep 1.3: Create `ItemSelectedEvent` for Enter key selection
- [ ] Substep 1.4: Create `EventDispatcherTrait` for components
- [ ] Substep 1.5: Add typed listener registration methods

**Notes**:
**Status**: Not Started
**Completed**:

---

### Stage 2: Update Components to Use Event System

- [ ] Substep 2.1: Update `TableComponent` to use event dispatcher
- [ ] Substep 2.2: Update `ListComponent` to use event dispatcher
- [ ] Substep 2.3: Update `FileListComponent` to use new pattern
- [ ] Substep 2.4: Maintain BC with existing callback methods
- [ ] Substep 2.5: Remove repetitive null-check code

**Notes**:
**Status**: Not Started
**Completed**:

---

### Stage 3: Documentation and Examples

- [ ] Substep 3.1: Document new event pattern usage
- [ ] Substep 3.2: Update existing examples in docs
- [ ] Substep 3.3: Add migration guide from callbacks to events
- [ ] Substep 3.4: Create example custom event

**Notes**:
**Status**: Not Started
**Completed**:

---

## Codebase References

- `src/UI/Component/Display/TableComponent.php:50-60` - Callback properties
- `src/UI/Component/Display/TableComponent.php:180-185` - Null-check invocation
- `src/UI/Component/Display/ListComponent.php:30-40` - Similar pattern
- `src/Feature/FileBrowser/Component/FileListComponent.php:60-80` - Callback wiring

## Current Problem

### Repetitive Callback Pattern

```php
// Multiple nullable callbacks
private $onSelect = null;
private $onChange = null;

// Setter methods
public function onSelect(callable $callback): void
{
    $this->onSelect = $callback;
}

public function onChange(callable $callback): void
{
    $this->onChange = $callback;
}

// Repetitive null-checks everywhere
if ($this->onSelect !== null) {
    ($this->onSelect)($this->rows[$this->selectedIndex], $this->selectedIndex);
}

if ($this->onChange !== null) {
    ($this->onChange)($this->rows[$this->selectedIndex], $this->selectedIndex);
}
```

## Proposed Solutions

### Option A: No-op Default Callbacks

```php
private \Closure $onSelect;
private \Closure $onChange;

public function __construct()
{
    // No-op defaults - no null checks needed
    $this->onSelect = static fn(array $row, int $index) => null;
    $this->onChange = static fn(array $row, int $index) => null;
}

// Usage - no null check!
($this->onSelect)($this->rows[$this->selectedIndex], $this->selectedIndex);
```

### Option B: Event Dispatcher Trait (Recommended)

```php
trait EventDispatcherTrait
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];
    
    protected function addEventListener(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }
    
    protected function dispatch(string $event, mixed ...$args): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener(...$args);
        }
    }
}

// In TableComponent
use EventDispatcherTrait;

public const EVENT_SELECT = 'select';
public const EVENT_CHANGE = 'change';

public function onSelect(callable $callback): void
{
    $this->addEventListener(self::EVENT_SELECT, $callback);
}

// Dispatch - clean and simple
$this->dispatch(self::EVENT_SELECT, $row, $index);
```

### Option C: Typed Event Objects

```php
final readonly class SelectionEvent
{
    public function __construct(
        public array $row,
        public int $index,
    ) {}
}

interface SelectableComponentListener
{
    public function onSelect(SelectionEvent $event): void;
    public function onChange(SelectionEvent $event): void;
}

// In component
private ?SelectableComponentListener $listener = null;

public function setListener(SelectableComponentListener $listener): void
{
    $this->listener = $listener;
}

// Single null check
$this->listener?->onSelect(new SelectionEvent($row, $index));
```

## Recommended Implementation (Option B)

### EventDispatcherTrait

```php
<?php

declare(strict_types=1);

namespace Butschster\Commander\UI\Component\Event;

trait EventDispatcherTrait
{
    /** @var array<string, list<callable>> */
    private array $eventListeners = [];
    
    /**
     * Add event listener
     */
    protected function on(string $event, callable $listener): static
    {
        $this->eventListeners[$event][] = $listener;
        return $this;
    }
    
    /**
     * Remove all listeners for an event
     */
    protected function off(string $event): static
    {
        unset($this->eventListeners[$event]);
        return $this;
    }
    
    /**
     * Dispatch event to all listeners
     */
    protected function emit(string $event, mixed ...$args): void
    {
        foreach ($this->eventListeners[$event] ?? [] as $listener) {
            $listener(...$args);
        }
    }
    
    /**
     * Check if event has listeners
     */
    protected function hasListeners(string $event): bool
    {
        return !empty($this->eventListeners[$event]);
    }
}
```

### Updated TableComponent

```php
final class TableComponent extends AbstractComponent
{
    use EventDispatcherTrait;
    
    public const string EVENT_SELECT = 'select';
    public const string EVENT_CHANGE = 'change';
    
    // BC-compatible methods delegate to trait
    public function onSelect(callable $callback): void
    {
        $this->on(self::EVENT_SELECT, $callback);
    }
    
    public function onChange(callable $callback): void
    {
        $this->on(self::EVENT_CHANGE, $callback);
    }
    
    // In handleInput - clean dispatch
    case 'ENTER':
        $this->emit(self::EVENT_SELECT, $this->rows[$this->selectedIndex], $this->selectedIndex);
        return true;
    
    // After navigation
    if ($oldIndex !== $this->selectedIndex) {
        $this->emit(self::EVENT_CHANGE, $this->rows[$this->selectedIndex], $this->selectedIndex);
    }
}
```

## Usage Instructions

⚠️ Keep this checklist updated:

- Mark completed substeps immediately with [x]
- Add notes about deviations or challenges
- Document decisions differing from plan
- Update status when starting/completing stages
- Ensure BC with existing onSelect/onChange calls
