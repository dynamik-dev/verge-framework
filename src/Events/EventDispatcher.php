<?php

declare(strict_types=1);

namespace Verge\Events;

use Verge\Container;

class EventDispatcher implements EventDispatcherInterface
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
     * Emit an event to all registered listeners.
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
