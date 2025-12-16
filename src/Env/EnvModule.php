<?php

declare(strict_types=1);

namespace Verge\Env;

use Verge\App;

class EnvModule
{
    public function __invoke(App $app): void
    {
        $app->singleton(EnvInterface::class, fn () => new Env());
    }
}
