<?php

declare(strict_types=1);

namespace Verge\Config;

use Verge\App;

class ConfigModule
{
    public function __invoke(App $app): void
    {
        $app->singleton(Config::class, fn () => new Config());
    }
}
