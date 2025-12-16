<?php

declare(strict_types=1);

namespace Verge;

use Verge\Cache\CacheModule;
use Verge\Config\ConfigModule;
use Verge\Env\EnvModule;
use Verge\Events\EventsModule;
use Verge\Http\HttpModule;
use Verge\Log\LogModule;
use Verge\Routing\RoutingModule;

class AppBuilder
{
    public function __invoke(App $app): void
    {
        $app->configure([
            EnvModule::class,
            ConfigModule::class,
            RoutingModule::class,
            HttpModule::class,
            EventsModule::class,
            CacheModule::class,
            LogModule::class,
        ]);
    }
}
