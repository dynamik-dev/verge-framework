<?php

declare(strict_types=1);

namespace Verge\Cache;

use Verge\App;

class CacheModule
{
    public function __invoke(App $app): void
    {
        $app->driver('cache', 'memory', fn () => new Drivers\MemoryCacheDriver());
        $app->defaultDriver('cache', 'memory');

        $app->singleton(CacheInterface::class, fn () => $app->driver('cache'));
    }
}
