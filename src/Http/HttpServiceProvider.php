<?php

declare(strict_types=1);

namespace Verge\Http;

use Psr\Http\Client\ClientInterface;
use Verge\App;
use Verge\Http\Client\Client;

class HttpServiceProvider
{
    public function __invoke(App $app): void
    {
        $app->singleton(RequestHandlerInterface::class, fn () => new RequestHandler(
            $app->container,
        ));

        $app->bind(Client::class, fn () => new Client());
        $app->bind(ClientInterface::class, fn () => $app->make(Client::class));
    }
}
