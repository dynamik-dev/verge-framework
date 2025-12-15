<?php

declare(strict_types=1);

namespace Verge;

use Verge\Cache\CacheServiceProvider;
use Verge\Env\EnvServiceProvider;
use Verge\Events\EventsServiceProvider;
use Verge\Http\HttpServiceProvider;
use Verge\Log\LoggerServiceProvider;
use Verge\Routing\RoutingServiceProvider;

class AppBuilder
{
    public function __invoke(App $app): void
    {
        $app->configure([
            EnvServiceProvider::class,
            RoutingServiceProvider::class,
            HttpServiceProvider::class,
            EventsServiceProvider::class,
            CacheServiceProvider::class,
            LoggerServiceProvider::class,
        ]);
    }
}
