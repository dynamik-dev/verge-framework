<?php

declare(strict_types=1);

namespace Verge\Routing;

use Verge\App;

class RoutingModule
{
    public function __invoke(App $app): void
    {
        $app->singleton(RouterInterface::class, fn () => new Router());

        // RouteMatcherInterface resolves to the same Router instance
        // This binding may be replaced with CachedRouter when cache is loaded
        $app->singleton(RouteMatcherInterface::class, fn () => $app->make(RouterInterface::class));

        // URL signing for secure route signatures
        $app->singleton(UrlSigner::class, function () use ($app) {
            /** @var string $key */
            $key = $app->config('app.key', '');
            if ($key === '') {
                throw new \RuntimeException('APP_KEY must be set in config to use URL signing');
            }
            return new UrlSigner($key);
        });
    }
}
