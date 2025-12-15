<?php

declare(strict_types=1);

use Verge\Bootstrap\CachedRouter;
use Verge\Http\Request;
use Verge\Routing\RouteMatch;

describe('CachedRouter', function () {
    describe('static route matching', function () {
        it('matches static routes with O(1) lookup', function () {
            $cacheData = [
                'static' => [
                    'GET' => [
                        '/' => [
                            'handler' => ['HomeController', 'index'],
                            'middleware' => [],
                            'name' => 'home',
                        ],
                        '/users' => [
                            'handler' => ['UserController', 'index'],
                            'middleware' => [],
                            'name' => 'users.index',
                        ],
                    ],
                ],
                'dynamic' => [],
                'named' => [
                    'home' => ['method' => 'GET', 'path' => '/', 'paramNames' => []],
                    'users.index' => ['method' => 'GET', 'path' => '/users', 'paramNames' => []],
                ],
            ];

            $router = new CachedRouter($cacheData);

            $request = new Request('GET', '/');
            $match = $router->match($request);

            expect($match->matched)->toBeTrue();
            expect($match->route->handler)->toBe(['HomeController', 'index']);
            expect($match->route->getName())->toBe('home');
        });

        it('returns not found for unmatched static routes', function () {
            $cacheData = [
                'static' => [
                    'GET' => [
                        '/' => ['handler' => ['HomeController', 'index'], 'middleware' => []],
                    ],
                ],
                'dynamic' => [],
                'named' => [],
            ];

            $router = new CachedRouter($cacheData);
            $request = new Request('GET', '/not-found');
            $match = $router->match($request);

            expect($match->matched)->toBeFalse();
        });
    });

    describe('dynamic route matching', function () {
        it('matches dynamic routes with parameters', function () {
            $cacheData = [
                'static' => [],
                'dynamic' => [
                    'GET' => [
                        2 => [
                            [
                                'path' => '/users/{id}',
                                'pattern' => '#^/users/([^/]+)$#',
                                'paramNames' => ['id'],
                                'handler' => ['UserController', 'show'],
                                'middleware' => [],
                                'name' => 'users.show',
                            ],
                        ],
                    ],
                ],
                'named' => [
                    'users.show' => ['method' => 'GET', 'path' => '/users/{id}', 'paramNames' => ['id']],
                ],
            ];

            $router = new CachedRouter($cacheData);
            $request = new Request('GET', '/users/123');
            $match = $router->match($request);

            expect($match->matched)->toBeTrue();
            expect($match->params)->toBe(['id' => '123']);
            expect($match->route->handler)->toBe(['UserController', 'show']);
        });

        it('matches routes with multiple parameters', function () {
            $cacheData = [
                'static' => [],
                'dynamic' => [
                    'GET' => [
                        3 => [
                            [
                                'path' => '/users/{userId}/posts/{postId}',
                                'pattern' => '#^/users/([^/]+)/posts/([^/]+)$#',
                                'paramNames' => ['userId', 'postId'],
                                'handler' => ['PostController', 'show'],
                                'middleware' => [],
                                'name' => null,
                            ],
                        ],
                    ],
                ],
                'named' => [],
            ];

            $router = new CachedRouter($cacheData);
            $request = new Request('GET', '/users/42/posts/99');
            $match = $router->match($request);

            expect($match->matched)->toBeTrue();
            expect($match->params)->toBe(['userId' => '42', 'postId' => '99']);
        });
    });

    describe('url generation', function () {
        it('generates URLs for named routes', function () {
            $cacheData = [
                'static' => [],
                'dynamic' => [],
                'named' => [
                    'users.show' => ['method' => 'GET', 'path' => '/users/{id}', 'paramNames' => ['id']],
                ],
            ];

            $router = new CachedRouter($cacheData);
            $url = $router->url('users.show', ['id' => '123']);

            expect($url)->toBe('/users/123');
        });

        it('throws for unknown named routes', function () {
            $cacheData = ['static' => [], 'dynamic' => [], 'named' => []];
            $router = new CachedRouter($cacheData);

            expect(fn () => $router->url('unknown'))
                ->toThrow(\Verge\Routing\RouteNotFoundException::class);
        });

        it('appends extra params as query string', function () {
            $cacheData = [
                'static' => [],
                'dynamic' => [],
                'named' => [
                    'users.index' => ['method' => 'GET', 'path' => '/users', 'paramNames' => []],
                ],
            ];

            $router = new CachedRouter($cacheData);
            $url = $router->url('users.index', ['page' => '2', 'limit' => '10']);

            expect($url)->toBe('/users?page=2&limit=10');
        });
    });

    describe('interface compliance', function () {
        it('implements RouteMatcherInterface', function () {
            $router = new CachedRouter(['static' => [], 'dynamic' => [], 'named' => []]);

            expect($router)->toBeInstanceOf(\Verge\Routing\RouteMatcherInterface::class);
        });

        it('does not implement RouterInterface (read-only by design)', function () {
            $router = new CachedRouter(['static' => [], 'dynamic' => [], 'named' => []]);

            expect($router)->not->toBeInstanceOf(\Verge\Routing\RouterInterface::class);
        });
    });

    describe('getRoutes()', function () {
        it('returns reconstructed Route objects', function () {
            $cacheData = [
                'static' => [
                    'GET' => [
                        '/' => ['handler' => ['HomeController', 'index'], 'middleware' => [], 'name' => 'home'],
                    ],
                    'POST' => [
                        '/users' => ['handler' => ['UserController', 'store'], 'middleware' => [], 'name' => null],
                    ],
                ],
                'dynamic' => [
                    'GET' => [
                        2 => [
                            [
                                'path' => '/users/{id}',
                                'pattern' => '#^/users/([^/]+)$#',
                                'paramNames' => ['id'],
                                'handler' => ['UserController', 'show'],
                                'middleware' => [],
                                'name' => 'users.show',
                            ],
                        ],
                    ],
                ],
                'named' => [],
            ];

            $router = new CachedRouter($cacheData);
            $routes = $router->getRoutes();

            expect($routes)->toHaveKey('GET');
            expect($routes)->toHaveKey('POST');
            expect($routes['GET'])->toHaveCount(2); // static + dynamic
            expect($routes['POST'])->toHaveCount(1);
        });
    });

    describe('middleware handling', function () {
        it('applies cached middleware to routes', function () {
            $cacheData = [
                'static' => [
                    'GET' => [
                        '/admin' => [
                            'handler' => ['AdminController', 'index'],
                            'middleware' => ['AuthMiddleware', 'AdminMiddleware'],
                            'name' => null,
                        ],
                    ],
                ],
                'dynamic' => [],
                'named' => [],
            ];

            $router = new CachedRouter($cacheData);
            $request = new Request('GET', '/admin');
            $match = $router->match($request);

            expect($match->route->getMiddleware())->toBe(['AuthMiddleware', 'AdminMiddleware']);
        });
    });
});
