<?php

declare(strict_types=1);

namespace Verge\Http;

use Verge\App;

class HttpServiceProvider
{
    public function __invoke(App $app): void
    {
        $app->singleton(RequestHandlerInterface::class, fn () => new RequestHandler(
            $app->container,
        ));
    }
}
