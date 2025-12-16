<?php

declare(strict_types=1);

use Verge\Routing\Attributes\Route;
use Verge\Routing\RouteLoader;
use Verge\Routing\Router;

class ConfigurableController
{
    public function prefix(): string
    {
        return '/api/v1';
    }

    public function middleware(): array
    {
        return ['auth'];
    }

    #[Route('GET', '/test')]
    public function test()
    {
        return 'test';
    }
}

class MixedConfigController
{
    public static function prefix(): string
    {
        return '/static';
    }

    public static function middleware(): array
    {
        return ['static_mw'];
    }

    #[Route('GET', '/test')]
    public function test()
    {
        return 'test';
    }
}

describe('Controller Configuration', function () {
    it('applies prefix and middleware from instance methods', function () {
        $router = new Router();
        $loader = new RouteLoader($router);

        $loader->registerController(ConfigurableController::class);

        $routes = $router->getRoutes()['GET'] ?? [];
        expect($routes)->toHaveCount(1);

        $route = $routes[0];
        // Expect prefix to be applied: /api/v1/test
        expect($route->path)->toBe('/api/v1/test');
        // Expect middleware to be applied
        expect($route->getMiddleware())->toContain('auth');
    });

    it('applies prefix and middleware from static methods', function () {
        $router = new Router();
        $loader = new RouteLoader($router);

        $loader->registerController(MixedConfigController::class);

        $routes = $router->getRoutes()['GET'] ?? [];
        expect($routes)->toHaveCount(1);

        $route = $routes[0];
        expect($route->path)->toBe('/static/test');
        expect($route->getMiddleware())->toContain('static_mw');
    });
});
