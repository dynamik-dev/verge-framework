<?php

declare(strict_types=1);

namespace Verge\Routing;

use Verge\App;

class RoutingServiceProvider
{
    public function __invoke(App $app): void
    {
        $app->singleton(RouterInterface::class, fn () => new Router());

        // RouteMatcherInterface resolves to the same Router instance
        // This binding may be replaced with CachedRouter when cache is loaded
        $app->singleton(RouteMatcherInterface::class, fn () => $app->make(RouterInterface::class));
    }
}
