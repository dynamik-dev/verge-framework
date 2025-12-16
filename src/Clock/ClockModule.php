<?php

declare(strict_types=1);

namespace Verge\Clock;

use Psr\Clock\ClockInterface;
use Verge\App;

class ClockModule
{
    public function __invoke(App $app): void
    {
        $app->singleton(Clock::class, fn () => new Clock());
        $app->bind(ClockInterface::class, fn () => $app->make(Clock::class));
    }
}
