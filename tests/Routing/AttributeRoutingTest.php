<?php

declare(strict_types=1);

namespace Tests\Routing;

use Verge\App;
use Verge\Http\Request;
use Verge\Http\Response;
use Verge\Routing\Attributes\Route;

class AttributeController
{
    #[Route('GET', '/attribute/simple')]
    public function simple(): string
    {
        return 'simple';
    }

    #[Route('GET', '/attribute/param/{id}')]
    public function param(string $id): string
    {
        return 'id: ' . $id;
    }

    #[Route('POST', '/attribute/json')]
    public function json(Request $request): array
    {
        return ['data' => $request->json()];
    }

    #[Route(['GET', 'POST'], '/attribute/multi')]
    public function multi(Request $request): string
    {
        return $request->method();
    }

    #[Route('GET', '/attribute/named', name: 'named.route')]
    public function named(): string
    {
        return 'named';
    }

    #[Route('GET', '/attribute/middleware', middleware: 'TestMiddleware')]
    public function middleware(): string
    {
        return 'middleware';
    }
}

class TestMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $response = $next($request);
        return $response->header('X-Attribute-Middleware', 'applied');
    }
}

describe('Attribute Routing', function () {
    it('registers multiple controllers', function () {
        $app = new App();
        // We'll register one controller in an array to verify the method works.
        $app->controllers([AttributeController::class]);

        $response = $app->test()->get('/attribute/simple');
        expect($response->body())->toBe('simple');
    });
    it('registers POST route', function () {
        $app = new App();
        $app->controller(AttributeController::class);

        $response = $app->test()->post('/attribute/json', ['foo' => 'bar']);

        expect($response->json())->toBe(['data' => ['foo' => 'bar']]);
    });

    it('registers multiple methods', function () {
        $app = new App();
        $app->controller(AttributeController::class);

        expect($app->test()->get('/attribute/multi')->body())->toBe('GET');
        expect($app->test()->post('/attribute/multi')->body())->toBe('POST');
    });

    it('registers named route', function () {
        $app = new App();
        $app->controller(AttributeController::class);

        expect($app->url('named.route'))->toBe('/attribute/named');
    });

    it('registers middleware', function () {
        $app = new App();
        $app->bind('TestMiddleware', fn () => new TestMiddleware());
        $app->controller(AttributeController::class);

        $response = $app->test()->get('/attribute/middleware');

        expect($response->getHeader('x-attribute-middleware'))->toBe(['applied']);
    });
});
