<?php

declare(strict_types=1);

use Verge\Routing\Route;

describe('Route', function () {

    describe('constructor', function () {
        it('creates a route with all properties', function () {
            $handler = fn() => 'hello';
            $route = new Route(
                method: 'GET',
                path: '/users/{id}',
                handler: $handler,
                pattern: '#^/users/([^/]+)$#',
                paramNames: ['id']
            );

            expect($route->method)->toBe('GET');
            expect($route->path)->toBe('/users/{id}');
            expect($route->handler)->toBe($handler);
            expect($route->pattern)->toBe('#^/users/([^/]+)$#');
            expect($route->paramNames)->toBe(['id']);
        });

        it('defaults paramNames to empty array', function () {
            $route = new Route(
                method: 'GET',
                path: '/',
                handler: fn() => 'home',
                pattern: '#^/$#'
            );

            expect($route->paramNames)->toBe([]);
        });
    });

    describe('use()', function () {
        it('adds middleware to the route', function () {
            $route = new Route('GET', '/', fn() => 'hello', '#^/$#');
            $middleware = fn($req, $next) => $next($req);

            $result = $route->use($middleware);

            expect($result)->toBe($route);
            expect($route->getMiddleware())->toBe([$middleware]);
        });

        it('adds multiple middleware in order', function () {
            $route = new Route('GET', '/', fn() => 'hello', '#^/$#');
            $first = fn($req, $next) => $next($req);
            $second = fn($req, $next) => $next($req);
            $third = 'SomeMiddleware';

            $route->use($first)->use($second)->use($third);

            expect($route->getMiddleware())->toBe([$first, $second, $third]);
        });

        it('accepts string middleware', function () {
            $route = new Route('GET', '/', fn() => 'hello', '#^/$#');

            $route->use('AuthMiddleware');

            expect($route->getMiddleware())->toBe(['AuthMiddleware']);
        });
    });

    describe('getMiddleware()', function () {
        it('returns empty array by default', function () {
            $route = new Route('GET', '/', fn() => 'hello', '#^/$#');

            expect($route->getMiddleware())->toBe([]);
        });

        it('returns all added middleware', function () {
            $route = new Route('GET', '/', fn() => 'hello', '#^/$#');
            $route->use('First')->use('Second');

            expect($route->getMiddleware())->toBe(['First', 'Second']);
        });
    });

    describe('matches()', function () {
        it('matches exact path', function () {
            $route = new Route('GET', '/', fn() => 'home', '#^/$#');

            expect($route->matches('/'))->toBe([]);
            expect($route->matches('/other'))->toBeNull();
        });

        it('matches path with single parameter', function () {
            $route = new Route(
                'GET',
                '/users/{id}',
                fn() => 'user',
                '#^/users/([^/]+)$#',
                ['id']
            );

            expect($route->matches('/users/123'))->toBe(['id' => '123']);
            expect($route->matches('/users/abc'))->toBe(['id' => 'abc']);
            expect($route->matches('/users/'))->toBeNull();
            expect($route->matches('/users'))->toBeNull();
        });

        it('matches path with multiple parameters', function () {
            $route = new Route(
                'GET',
                '/posts/{postId}/comments/{commentId}',
                fn() => 'comment',
                '#^/posts/([^/]+)/comments/([^/]+)$#',
                ['postId', 'commentId']
            );

            $params = $route->matches('/posts/42/comments/99');

            expect($params)->toBe(['postId' => '42', 'commentId' => '99']);
        });

        it('returns null for non-matching path', function () {
            $route = new Route('GET', '/users', fn() => 'users', '#^/users$#');

            expect($route->matches('/posts'))->toBeNull();
            expect($route->matches('/users/123'))->toBeNull();
            expect($route->matches('/'))->toBeNull();
        });

        it('matches complex patterns', function () {
            $route = new Route(
                'GET',
                '/api/v1/users/{userId}/posts/{postId}',
                fn() => 'post',
                '#^/api/v1/users/([^/]+)/posts/([^/]+)$#',
                ['userId', 'postId']
            );

            $params = $route->matches('/api/v1/users/john/posts/hello-world');

            expect($params)->toBe(['userId' => 'john', 'postId' => 'hello-world']);
        });
    });

});
