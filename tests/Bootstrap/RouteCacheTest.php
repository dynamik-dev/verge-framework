<?php

declare(strict_types=1);

use Verge\Bootstrap\RouteCache;
use Verge\Routing\Router;

beforeEach(function () {
    $this->cachePath = sys_get_temp_dir() . '/verge-test-routes-' . uniqid() . '.php';
});

afterEach(function () {
    if (file_exists($this->cachePath)) {
        unlink($this->cachePath);
    }
});

describe('RouteCache', function () {
    describe('warm()', function () {
        it('caches routes with serializable handlers', function () {
            $router = new Router();
            $router->add('GET', '/', ['HomeController', 'index']);
            $router->add('GET', '/users', ['UserController', 'index']);

            $cache = new RouteCache($this->cachePath);
            $result = $cache->warm($router);

            expect($result->cached)->toBe(2);
            expect($result->skipped)->toBeEmpty();
            expect(file_exists($this->cachePath))->toBeTrue();
        });

        it('skips routes with closure handlers', function () {
            $router = new Router();
            $router->add('GET', '/', fn () => 'Hello');
            $router->add('GET', '/users', ['UserController', 'index']);

            $cache = new RouteCache($this->cachePath);
            $result = $cache->warm($router);

            expect($result->cached)->toBe(1);
            expect($result->skipped)->toHaveCount(1);
            expect($result->skipped[0]['path'])->toBe('/');
            expect($result->skipped[0]['reason'])->toBe('Handler is a Closure');
        });

        it('skips routes with closure middleware', function () {
            $router = new Router();
            $route = $router->add('GET', '/protected', ['UserController', 'index']);
            $route->use(fn ($request, $next) => $next($request));

            $cache = new RouteCache($this->cachePath);
            $result = $cache->warm($router);

            expect($result->cached)->toBe(0);
            expect($result->skipped)->toHaveCount(1);
            expect($result->skipped[0]['reason'])->toBe('Middleware contains a Closure');
        });

        it('caches routes with string middleware', function () {
            $router = new Router();
            $route = $router->add('GET', '/protected', ['UserController', 'index']);
            $route->use('AuthMiddleware');

            $cache = new RouteCache($this->cachePath);
            $result = $cache->warm($router);

            expect($result->cached)->toBe(1);
        });

        it('caches named routes', function () {
            $router = new Router();
            $route = $router->add('GET', '/users/{id}', ['UserController', 'show']);
            $route->name('users.show');
            $router->registerNamedRoute('users.show', $route);

            $cache = new RouteCache($this->cachePath);
            $cache->warm($router);

            $data = $cache->load();
            expect($data['named'])->toHaveKey('users.show');
            expect($data['named']['users.show']['path'])->toBe('/users/{id}');
        });

        it('returns handler classes for container caching', function () {
            $router = new Router();
            $route = $router->add('GET', '/', ['App\\Controllers\\HomeController', 'index']);
            $route->use('App\\Middleware\\AuthMiddleware');

            $cache = new RouteCache($this->cachePath);
            $result = $cache->warm($router);

            expect($result->handlers)->toBeArray();
        });

        it('separates static and dynamic routes', function () {
            $router = new Router();
            $router->add('GET', '/', ['HomeController', 'index']);
            $router->add('GET', '/users/{id}', ['UserController', 'show']);

            $cache = new RouteCache($this->cachePath);
            $cache->warm($router);

            $data = $cache->load();
            expect($data['static']['GET'])->toHaveKey('/');
            expect($data['dynamic']['GET'])->not->toBeEmpty();
        });
    });

    describe('load()', function () {
        it('loads cached data from file', function () {
            $router = new Router();
            $router->add('GET', '/', ['HomeController', 'index']);

            $cache = new RouteCache($this->cachePath);
            $cache->warm($router);

            $data = $cache->load();

            expect($data)->toHaveKey('static');
            expect($data)->toHaveKey('dynamic');
            expect($data)->toHaveKey('named');
            expect($data)->toHaveKey('generated');
        });

        it('throws when cache does not exist', function () {
            $cache = new RouteCache('/nonexistent/path.php');

            expect(fn () => $cache->load())
                ->toThrow(\RuntimeException::class, 'Route cache not found');
        });
    });

    describe('isCached()', function () {
        it('returns false when cache does not exist', function () {
            $cache = new RouteCache($this->cachePath);
            expect($cache->isCached())->toBeFalse();
        });

        it('returns true when cache exists', function () {
            $router = new Router();
            $router->add('GET', '/', ['HomeController', 'index']);

            $cache = new RouteCache($this->cachePath);
            $cache->warm($router);

            expect($cache->isCached())->toBeTrue();
        });
    });

    describe('clear()', function () {
        it('removes cache file', function () {
            $router = new Router();
            $router->add('GET', '/', ['HomeController', 'index']);

            $cache = new RouteCache($this->cachePath);
            $cache->warm($router);
            expect(file_exists($this->cachePath))->toBeTrue();

            $cache->clear();
            expect(file_exists($this->cachePath))->toBeFalse();
        });

        it('returns true when file does not exist', function () {
            $cache = new RouteCache($this->cachePath);
            expect($cache->clear())->toBeTrue();
        });
    });

    describe('getWarnings()', function () {
        it('formats skipped routes as warnings', function () {
            $router = new Router();
            $router->add('GET', '/closure', fn () => 'test');

            $cache = new RouteCache($this->cachePath);
            $result = $cache->warm($router);

            $warnings = $result->getWarnings();
            expect($warnings)->toHaveCount(1);
            expect($warnings[0])->toContain('GET /closure');
            expect($warnings[0])->toContain('Handler is a Closure');
        });
    });
});
