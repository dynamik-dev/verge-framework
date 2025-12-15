<?php

declare(strict_types=1);

use Verge\Routing\Route;
use Verge\Routing\RouteGroup;

describe('RouteGroup', function () {

    describe('constructor', function () {
        it('creates a group with prefix', function () {
            $group = new RouteGroup('/api');

            expect($group->getPrefix())->toBe('/api');
            expect($group->getRoutes())->toBe([]);
            expect($group->getMiddleware())->toBe([]);
        });
    });

    describe('addRoute()', function () {
        it('adds a route to the group', function () {
            $group = new RouteGroup('/api');
            $route = new Route('GET', '/api/users', fn () => 'users', '#^/api/users$#');

            $group->addRoute($route);

            expect($group->getRoutes())->toBe([$route]);
        });

        it('adds multiple routes', function () {
            $group = new RouteGroup('/api');
            $route1 = new Route('GET', '/api/users', fn () => 'users', '#^/api/users$#');
            $route2 = new Route('POST', '/api/users', fn () => 'create', '#^/api/users$#');

            $group->addRoute($route1);
            $group->addRoute($route2);

            expect($group->getRoutes())->toBe([$route1, $route2]);
        });
    });

    describe('use()', function () {
        it('adds middleware to the group', function () {
            $group = new RouteGroup('/api');
            $middleware = fn ($req, $next) => $next($req);

            $result = $group->use($middleware);

            expect($result)->toBe($group);
            expect($group->getMiddleware())->toBe([$middleware]);
        });

        it('adds middleware to existing routes', function () {
            $group = new RouteGroup('/api');
            $route = new Route('GET', '/api/users', fn () => 'users', '#^/api/users$#');
            $middleware = 'AuthMiddleware';

            $group->addRoute($route);
            $group->use($middleware);

            expect($route->getMiddleware())->toBe([$middleware]);
        });

        it('adds multiple middleware to all routes', function () {
            $group = new RouteGroup('/api');
            $route1 = new Route('GET', '/api/users', fn () => 'users', '#^/api/users$#');
            $route2 = new Route('GET', '/api/posts', fn () => 'posts', '#^/api/posts$#');

            $group->addRoute($route1);
            $group->addRoute($route2);
            $group->use('First')->use('Second');

            expect($route1->getMiddleware())->toBe(['First', 'Second']);
            expect($route2->getMiddleware())->toBe(['First', 'Second']);
        });

        it('is chainable', function () {
            $group = new RouteGroup('/api');

            $result = $group->use('First')->use('Second')->use('Third');

            expect($result)->toBe($group);
            expect($group->getMiddleware())->toBe(['First', 'Second', 'Third']);
        });
    });

    describe('getRoutes()', function () {
        it('returns empty array by default', function () {
            $group = new RouteGroup('/api');

            expect($group->getRoutes())->toBe([]);
        });

        it('returns all added routes', function () {
            $group = new RouteGroup('/api');
            $route1 = new Route('GET', '/api/a', fn () => 'a', '#^/api/a$#');
            $route2 = new Route('GET', '/api/b', fn () => 'b', '#^/api/b$#');

            $group->addRoute($route1);
            $group->addRoute($route2);

            expect($group->getRoutes())->toHaveCount(2);
            expect($group->getRoutes()[0])->toBe($route1);
            expect($group->getRoutes()[1])->toBe($route2);
        });
    });

    describe('getMiddleware()', function () {
        it('returns empty array by default', function () {
            $group = new RouteGroup('/api');

            expect($group->getMiddleware())->toBe([]);
        });

        it('returns all added middleware', function () {
            $group = new RouteGroup('/api');

            $group->use('First')->use('Second');

            expect($group->getMiddleware())->toBe(['First', 'Second']);
        });
    });

    describe('getPrefix()', function () {
        it('returns the group prefix', function () {
            expect((new RouteGroup('/api'))->getPrefix())->toBe('/api');
            expect((new RouteGroup('/admin'))->getPrefix())->toBe('/admin');
            expect((new RouteGroup('/api/v1'))->getPrefix())->toBe('/api/v1');
        });
    });

});
