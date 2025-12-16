<?php

declare(strict_types=1);

namespace Verge\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Verge\App;
use Verge\Http\Client\Client;

class HttpModule
{
    public function __invoke(App $app): void
    {
        $app->singleton(RequestHandlerInterface::class, fn () => new RequestHandler(
            $app->container,
        ));

        $app->bind(Client::class, fn () => new Client());
        $app->bind(ClientInterface::class, fn () => $app->make(Client::class));

        // PSR-17 HTTP Factories
        $app->singleton(HttpFactory::class, fn () => new HttpFactory());
        $app->bind(RequestFactoryInterface::class, fn () => $app->make(HttpFactory::class));
        $app->bind(ResponseFactoryInterface::class, fn () => $app->make(HttpFactory::class));
        $app->bind(ServerRequestFactoryInterface::class, fn () => $app->make(HttpFactory::class));
        $app->bind(StreamFactoryInterface::class, fn () => $app->make(HttpFactory::class));
        $app->bind(UploadedFileFactoryInterface::class, fn () => $app->make(HttpFactory::class));
        $app->bind(UriFactoryInterface::class, fn () => $app->make(HttpFactory::class));
    }
}
