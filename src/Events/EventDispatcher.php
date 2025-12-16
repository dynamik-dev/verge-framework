<?php

declare(strict_types=1);

namespace Verge\Events;

use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Verge\Container;

/**
 * Event dispatcher supporting both string-based events and PSR-14 object events.
 */
class EventDispatcher implements EventDispatcherInterface, PsrEventDispatcherInterface
{
    /** @var array<string, array<callable|string>> */
    protected array $listeners = [];

    public function __construct(
        protected Container $container
    ) {
    }

    /**
     * Register an event listener.
     */
    public function on(string $event, callable|string $listener): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        $this->listeners[$event][] = $listener;
    }

    /**
     * PSR-14: Dispatch an event object to all registered listeners.
     *
     * @template T of object
     * @param T $event
     * @return T
     */
    public function dispatch(object $event): object
    {
        $eventClass = $event::class;

        // Get listeners for this event class and its parents/interfaces
        $listeners = $this->getListenersForClass($eventClass);

        foreach ($listeners as $listener) {
            // Check if event propagation is stopped
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }

            $this->callObjectListener($listener, $event);
        }

        return $event;
    }

    /**
     * Emit an event to all registered listeners (string-based API).
     *
     * @param array<string, mixed> $payload
     */
    public function emit(string $event, array $payload = []): void
    {
        // Call exact match listeners
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $this->call($listener, $event, $payload);
        }

        // Call wildcard listeners
        foreach ($this->listeners as $pattern => $listeners) {
            if ($this->matchesWildcard($pattern, $event)) {
                foreach ($listeners as $listener) {
                    $this->call($listener, $event, $payload);
                }
            }
        }
    }

    /**
     * Check if any listeners are registered for an event.
     */
    public function hasListeners(string $event): bool
    {
        if (!empty($this->listeners[$event])) {
            return true;
        }

        // Check wildcard patterns
        foreach ($this->listeners as $pattern => $listeners) {
            if (!empty($listeners) && $this->matchesWildcard($pattern, $event)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove all listeners for an event.
     */
    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }

    /**
     * Get all registered listeners for an event.
     *
     * @return array<callable|string>
     */
    public function getListeners(string $event): array
    {
        return $this->listeners[$event] ?? [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function call(callable|string $listener, string $event, array $payload): void
    {
        // Resolve class string through container
        if (is_string($listener)) {
            $listener = $this->container->resolve($listener);
        }

        // Call listener with event name and payload
        if (is_callable($listener)) {
            $listener($event, $payload);
        }
    }

    /**
     * Call a listener with an event object (PSR-14 style).
     */
    protected function callObjectListener(callable|string $listener, object $event): void
    {
        // Resolve class string through container
        if (is_string($listener)) {
            $listener = $this->container->resolve($listener);
        }

        // Call listener with event object
        if (is_callable($listener)) {
            $listener($event);
        }
    }

    /**
     * Get all listeners that should receive an event of the given class.
     *
     * @return array<callable|string>
     */
    protected function getListenersForClass(string $eventClass): array
    {
        $listeners = [];

        // Exact class match
        if (isset($this->listeners[$eventClass])) {
            $listeners = array_merge($listeners, $this->listeners[$eventClass]);
        }

        // Check parent classes
        $parents = class_parents($eventClass);
        if ($parents !== false) {
            foreach ($parents as $parent) {
                if (isset($this->listeners[$parent])) {
                    $listeners = array_merge($listeners, $this->listeners[$parent]);
                }
            }
        }

        // Check interfaces
        $interfaces = class_implements($eventClass);
        if ($interfaces !== false) {
            foreach ($interfaces as $interface) {
                if (isset($this->listeners[$interface])) {
                    $listeners = array_merge($listeners, $this->listeners[$interface]);
                }
            }
        }

        return $listeners;
    }

    protected function matchesWildcard(string $pattern, string $event): bool
    {
        // Skip exact matches (handled separately)
        if ($pattern === $event) {
            return false;
        }

        // Global wildcard
        if ($pattern === '*') {
            return true;
        }

        // Namespace wildcard (e.g., 'user.*')
        if (str_ends_with($pattern, '.*')) {
            $namespace = substr($pattern, 0, -2);
            return str_starts_with($event, $namespace . '.');
        }

        return false;
    }
}
