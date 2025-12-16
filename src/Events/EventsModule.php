<?php

declare(strict_types=1);

namespace Verge\Events;

use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Verge\App;

class EventsModule
{
    public function __invoke(App $app): void
    {
        $app->singleton(EventDispatcherInterface::class, fn () => new EventDispatcher($app->container));
        $app->bind(PsrEventDispatcherInterface::class, fn () => $app->make(EventDispatcherInterface::class));
    }
}
