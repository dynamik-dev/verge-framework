<?php

declare(strict_types=1);

namespace Verge\Events;

interface EventDispatcherInterface
{
    /**
     * Register an event listener.
     */
    public function on(string $event, callable|string $listener): void;

    /**
     * Emit an event to all registered listeners.
     *
     * @param array<string, mixed> $payload
     */
    public function emit(string $event, array $payload = []): void;

    /**
     * Check if any listeners are registered for an event.
     */
    public function hasListeners(string $event): bool;

    /**
     * Remove all listeners for an event.
     */
    public function forget(string $event): void;
}
