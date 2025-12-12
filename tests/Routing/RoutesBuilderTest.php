<?php

declare(strict_types=1);

use Verge\Routing\Router;
use Verge\Routing\RoutesBuilder;
use Verge\Http\Request;
use Verge\Http\Response;

describe('RoutesBuilder', function () {

    describe('route methods', function () {
        it('adds GET route', function () {
            $router = new Router();
            $builder = new RoutesBuilder($router);

            $route = $builder->get('/test', fn() => 'test');

            expect($route)->not->toBeNull();
            expect($router->match(new Request('GET', '/test'))->matched)->toBeTrue();
        });

        it('adds POST route', function () {
            $router = new Router();
            $builder = new RoutesBuilder($router);

            $builder->post('/test', fn() => 'test');

            expect($router->match(new Request('POST', '/test'))->matched)->toBeTrue();
        });

        it('adds PUT route', function () {
            $router = new Router();
            $builder = new RoutesBuilder($router);

            $builder->put('/test', fn() => 'test');

            expect($router->match(new Request('PUT', '/test'))->matched)->toBeTrue();
        });

        it('adds PATCH route', function () {
            $router = new Router();
            $builder = new RoutesBuilder($router);

            $builder->patch('/test', fn() => 'test');

            expect($router->match(new Request('PATCH', '/test'))->matched)->toBeTrue();
        });

        it('adds DELETE route', function () {
            $router = new Router();
            $builder = new RoutesBuilder($router);

            $builder->delete('/test', fn() => 'test');

            expect($router->match(new Request('DELETE', '/test'))->matched)->toBeTrue();
        });

        it('tracks all routes added', function () {
            $router = new Router();
            $builder = new RoutesBuilder($router);

            $builder->get('/one', fn() => 'one');
            $builder->post('/two', fn() => 'two');
            $builder->put('/three', fn() => 'three');

            expect($builder->getRoutes())->toHaveCount(3);
        });
    });

    describe('use()', function () {
        it('applies middleware to all routes', function () {
            $router = new Router();
            $builder = new RoutesBuilder($router);

            $middleware = fn($req, $next) => $next($req)->header('X-Test', 'applied');

            $builder->get('/one', fn() => 'one');
            $builder->get('/two', fn() => 'two');
            $builder->use($middleware);

            $routes = $builder->getRoutes();
            expect($routes[0]->getMiddleware())->toHaveCount(1);
            expect($routes[1]->getMiddleware())->toHaveCount(1);
        });

        it('is chainable', function () {
            $router = new Router();
            $builder = new RoutesBuilder($router);

            $result = $builder
                ->get('/test', fn() => 'test')
                ->use(fn($req, $next) => $next($req));

            // use() on route returns Route, but we're testing builder's use()
            $builder->use(fn($req, $next) => $next($req));

            expect($builder->getRoutes()[0]->getMiddleware())->toHaveCount(2);
        });

        it('allows multiple middleware', function () {
            $router = new Router();
            $builder = new RoutesBuilder($router);

            $builder->get('/test', fn() => 'test');
            $builder
                ->use(fn($req, $next) => $next($req))
                ->use(fn($req, $next) => $next($req))
                ->use(fn($req, $next) => $next($req));

            expect($builder->getRoutes()[0]->getMiddleware())->toHaveCount(3);
        });
    });

    describe('single route middleware', function () {
        it('allows middleware on individual routes', function () {
            $router = new Router();
            $builder = new RoutesBuilder($router);

            $builder->get('/public', fn() => 'public');
            $builder->get('/private', fn() => 'private')
                ->use(fn($req, $next) => $next($req));

            $routes = $builder->getRoutes();
            expect($routes[0]->getMiddleware())->toHaveCount(0);
            expect($routes[1]->getMiddleware())->toHaveCount(1);
        });

        it('combines route and builder middleware', function () {
            $router = new Router();
            $builder = new RoutesBuilder($router);

            $builder->get('/test', fn() => 'test')
                ->use(fn($req, $next) => $next($req)); // Route middleware

            $builder->use(fn($req, $next) => $next($req)); // Builder middleware

            expect($builder->getRoutes()[0]->getMiddleware())->toHaveCount(2);
        });
    });

});
