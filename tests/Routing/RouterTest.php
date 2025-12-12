<?php

declare(strict_types=1);

use Verge\Http\Request;
use Verge\Routing\Route;
use Verge\Routing\Router;
use Verge\Routing\RouterInterface;

describe('Router', function () {

    describe('implements RouterInterface', function () {
        it('implements the interface', function () {
            $router = new Router();

            expect($router)->toBeInstanceOf(RouterInterface::class);
        });
    });

    describe('add()', function () {
        it('adds a route and returns it', function () {
            $router = new Router();
            $handler = fn() => 'hello';

            $route = $router->add('GET', '/hello', $handler);

            expect($route)->toBeInstanceOf(Route::class);
            expect($route->method)->toBe('GET');
            expect($route->path)->toBe('/hello');
            expect($route->handler)->toBe($handler);
        });

        it('uppercases the HTTP method', function () {
            $router = new Router();

            $route = $router->add('get', '/test', fn() => 'test');

            expect($route->method)->toBe('GET');
        });

        it('compiles path pattern for static routes', function () {
            $router = new Router();

            $route = $router->add('GET', '/users', fn() => 'users');

            expect($route->pattern)->toBe('#^/users$#');
            expect($route->paramNames)->toBe([]);
        });

        it('compiles path pattern with single parameter', function () {
            $router = new Router();

            $route = $router->add('GET', '/users/{id}', fn() => 'user');

            expect($route->pattern)->toBe('#^/users/([^/]+)$#');
            expect($route->paramNames)->toBe(['id']);
        });

        it('compiles path pattern with multiple parameters', function () {
            $router = new Router();

            $route = $router->add('GET', '/posts/{postId}/comments/{commentId}', fn() => 'comment');

            expect($route->pattern)->toBe('#^/posts/([^/]+)/comments/([^/]+)$#');
            expect($route->paramNames)->toBe(['postId', 'commentId']);
        });

        it('accepts array handler', function () {
            $router = new Router();
            $handler = ['UserController', 'index'];

            $route = $router->add('GET', '/users', $handler);

            expect($route->handler)->toBe($handler);
        });

        it('accepts string handler', function () {
            $router = new Router();

            $route = $router->add('GET', '/users', 'UserController');

            expect($route->handler)->toBe('UserController');
        });

        it('stores routes by method', function () {
            $router = new Router();

            $router->add('GET', '/users', fn() => 'get');
            $router->add('POST', '/users', fn() => 'post');
            $router->add('GET', '/posts', fn() => 'posts');

            $routes = $router->getRoutes();

            expect($routes)->toHaveKey('GET');
            expect($routes)->toHaveKey('POST');
            expect($routes['GET'])->toHaveCount(2);
            expect($routes['POST'])->toHaveCount(1);
        });
    });

    describe('match()', function () {
        it('matches exact path', function () {
            $router = new Router();
            $router->add('GET', '/users', fn() => 'users');

            $request = new Request('GET', '/users');
            $match = $router->match($request);

            expect($match->matched)->toBeTrue();
            expect($match->route->path)->toBe('/users');
            expect($match->params)->toBe([]);
        });

        it('matches path with parameter', function () {
            $router = new Router();
            $router->add('GET', '/users/{id}', fn() => 'user');

            $request = new Request('GET', '/users/123');
            $match = $router->match($request);

            expect($match->matched)->toBeTrue();
            expect($match->params)->toBe(['id' => '123']);
        });

        it('matches path with multiple parameters', function () {
            $router = new Router();
            $router->add('GET', '/posts/{postId}/comments/{commentId}', fn() => 'comment');

            $request = new Request('GET', '/posts/42/comments/99');
            $match = $router->match($request);

            expect($match->matched)->toBeTrue();
            expect($match->params)->toBe(['postId' => '42', 'commentId' => '99']);
        });

        it('returns not found for unmatched path', function () {
            $router = new Router();
            $router->add('GET', '/users', fn() => 'users');

            $request = new Request('GET', '/posts');
            $match = $router->match($request);

            expect($match->matched)->toBeFalse();
            expect($match->route)->toBeNull();
        });

        it('returns not found for unmatched method', function () {
            $router = new Router();
            $router->add('GET', '/users', fn() => 'users');

            $request = new Request('POST', '/users');
            $match = $router->match($request);

            expect($match->matched)->toBeFalse();
        });

        it('normalizes path with trailing slash', function () {
            $router = new Router();
            $router->add('GET', '/users', fn() => 'users');

            $request = new Request('GET', '/users/');
            $match = $router->match($request);

            expect($match->matched)->toBeTrue();
        });

        it('normalizes path without leading slash', function () {
            $router = new Router();
            $router->add('GET', '/users', fn() => 'users');

            $request = new Request('GET', 'users');
            $match = $router->match($request);

            expect($match->matched)->toBeTrue();
        });

        it('matches root path', function () {
            $router = new Router();
            $router->add('GET', '/', fn() => 'home');

            $request = new Request('GET', '/');
            $match = $router->match($request);

            expect($match->matched)->toBeTrue();
        });

        it('matches first registered route', function () {
            $router = new Router();
            $router->add('GET', '/users/{id}', fn() => 'first');
            $router->add('GET', '/users/{userId}', fn() => 'second');

            $request = new Request('GET', '/users/123');
            $match = $router->match($request);

            expect($match->matched)->toBeTrue();
            expect($match->route->path)->toBe('/users/{id}');
        });

        it('matches different HTTP methods independently', function () {
            $router = new Router();
            $router->add('GET', '/users', fn() => 'list');
            $router->add('POST', '/users', fn() => 'create');

            $getRequest = new Request('GET', '/users');
            $postRequest = new Request('POST', '/users');

            $getMatch = $router->match($getRequest);
            $postMatch = $router->match($postRequest);

            expect($getMatch->matched)->toBeTrue();
            expect($postMatch->matched)->toBeTrue();
            expect($getMatch->route)->not->toBe($postMatch->route);
        });

        it('matches complex nested paths', function () {
            $router = new Router();
            $router->add('GET', '/api/v1/users/{userId}/posts/{postId}/comments', fn() => 'comments');

            $request = new Request('GET', '/api/v1/users/john/posts/hello-world/comments');
            $match = $router->match($request);

            expect($match->matched)->toBeTrue();
            expect($match->params)->toBe(['userId' => 'john', 'postId' => 'hello-world']);
        });
    });

    describe('getRoutes()', function () {
        it('returns empty array by default', function () {
            $router = new Router();

            expect($router->getRoutes())->toBe([]);
        });

        it('returns routes grouped by method', function () {
            $router = new Router();
            $router->add('GET', '/a', fn() => 'a');
            $router->add('GET', '/b', fn() => 'b');
            $router->add('POST', '/c', fn() => 'c');

            $routes = $router->getRoutes();

            expect($routes)->toHaveKey('GET');
            expect($routes)->toHaveKey('POST');
            expect($routes['GET'])->toHaveCount(2);
            expect($routes['POST'])->toHaveCount(1);
        });
    });

});
