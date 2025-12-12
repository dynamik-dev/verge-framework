<?php

declare(strict_types=1);

use Verge\App;
use Verge\Bootstrap\BootstrapCache;
use Verge\Bootstrap\CachedRouter;
use Verge\Routing\RouterInterface;

beforeEach(function () {
    $this->cacheDir = sys_get_temp_dir() . '/verge-test-cache-' . uniqid();
    mkdir($this->cacheDir, 0755, true);
});

afterEach(function () {
    // Clean up cache files
    if (is_dir($this->cacheDir)) {
        array_map('unlink', glob($this->cacheDir . '/*') ?: []);
        rmdir($this->cacheDir);
    }
});

describe('BootstrapCache', function () {
    describe('__invoke()', function () {
        it('registers itself in the container', function () {
            $app = new App();
            $cache = new BootstrapCache(path: $this->cacheDir);

            $app->configure($cache);

            expect($app->make(BootstrapCache::class))->toBe($cache);
        });

        it('does nothing when disabled', function () {
            $app = new App();
            $cache = new BootstrapCache(path: $this->cacheDir, enabled: false);

            $app->configure($cache);

            // Router should still be the regular Router, not CachedRouter
            expect($app->container->resolve(RouterInterface::class))
                ->toBeInstanceOf(\Verge\Routing\Router::class);
        });

        it('uses CachedRouter when route cache exists', function () {
            // First, warm the cache
            $app1 = new App();
            $app1->get('/', ['TestHomeController', 'index']);

            $cache1 = new BootstrapCache(path: $this->cacheDir);
            $app1->configure($cache1);
            $cache1->warm();

            // Create a new app and verify it uses cached router
            $app2 = new App();
            $cache2 = new BootstrapCache(path: $this->cacheDir);
            $app2->configure($cache2);

            // Routes method returns a Routes object, but we can check the router indirectly
            expect($cache2->isCached())->toBeTrue();
        });
    });

    describe('warm()', function () {
        it('caches routes and returns result', function () {
            $app = new App();
            $app->get('/', ['TestHomeController', 'index']);
            $app->get('/users/{id}', ['TestUserController', 'show']);

            $cache = new BootstrapCache(path: $this->cacheDir);
            $app->configure($cache);

            $result = $cache->warm();

            expect($result->routes->cached)->toBe(2);
            expect($result->summary())->toContain('Routes cached: 2');
        });

        it('warns about closure handlers', function () {
            $app = new App();
            $app->get('/', fn() => 'Hello');
            $app->get('/users', ['TestUserController', 'index']);

            $cache = new BootstrapCache(path: $this->cacheDir);
            $app->configure($cache);

            $result = $cache->warm();

            expect($result->routes->cached)->toBe(1);
            expect($result->routes->skipped)->toHaveCount(1);
            expect($result->hasWarnings())->toBeTrue();
        });

        it('throws when called before configure()', function () {
            $cache = new BootstrapCache(path: $this->cacheDir);

            expect(fn() => $cache->warm())
                ->toThrow(\RuntimeException::class, 'must be configured');
        });
    });

    describe('clear()', function () {
        it('removes cache files', function () {
            $app = new App();
            $app->get('/', ['TestHomeController', 'index']);

            $cache = new BootstrapCache(path: $this->cacheDir);
            $app->configure($cache);
            $cache->warm();

            expect(file_exists($this->cacheDir . '/routes.php'))->toBeTrue();

            $cache->clear();

            expect(file_exists($this->cacheDir . '/routes.php'))->toBeFalse();
        });
    });

    describe('isCached()', function () {
        it('returns false when no cache exists', function () {
            $cache = new BootstrapCache(path: $this->cacheDir);
            expect($cache->isCached())->toBeFalse();
        });

        it('returns true after warming', function () {
            $app = new App();
            $app->get('/', ['TestHomeController', 'index']);

            $cache = new BootstrapCache(path: $this->cacheDir);
            $app->configure($cache);
            $cache->warm();

            expect($cache->isCached())->toBeTrue();
        });
    });

    describe('status()', function () {
        it('returns cache status information', function () {
            $app = new App();
            $app->get('/', ['TestHomeController', 'index']);

            $cache = new BootstrapCache(path: $this->cacheDir);
            $app->configure($cache);

            $status = $cache->status();

            expect($status)->toHaveKey('enabled');
            expect($status)->toHaveKey('path');
            expect($status)->toHaveKey('routes');
            expect($status)->toHaveKey('container');
            expect($status['routes']['cached'])->toBeFalse();
        });

        it('shows cache details after warming', function () {
            $app = new App();
            $app->get('/', ['TestHomeController', 'index']);

            $cache = new BootstrapCache(path: $this->cacheDir);
            $app->configure($cache);
            $cache->warm();

            $status = $cache->status();

            expect($status['routes']['cached'])->toBeTrue();
            expect($status['routes']['size'])->toBeGreaterThan(0);
            expect($status['routes']['modified'])->toBeInt();
        });
    });

    describe('integration with App', function () {
        it('handles requests correctly with cached routes', function () {
            // First, create and warm cache
            $app1 = new App();
            $app1->get('/', ['CacheTestHomeController', 'index']);

            $cache1 = new BootstrapCache(path: $this->cacheDir, enabled: true);
            $app1->configure($cache1);
            $cache1->warm();

            // Create new app with cached routes
            $app2 = new App();
            $cache2 = new BootstrapCache(path: $this->cacheDir, enabled: true);
            $app2->configure($cache2);

            $response = $app2->test()->get('/');

            expect($response->status())->toBe(200);
            expect($response->json())->toBe(['status' => 'ok']);
        });

        it('preserves route parameters with cached routes', function () {
            // Warm cache
            $app1 = new App();
            $app1->get('/users/{id}', ['CacheTestUserShowController', 'index']);

            $cache1 = new BootstrapCache(path: $this->cacheDir, enabled: true);
            $app1->configure($cache1);
            $cache1->warm();

            // Test with cached routes
            $app2 = new App();
            $cache2 = new BootstrapCache(path: $this->cacheDir, enabled: true);
            $app2->configure($cache2);

            $response = $app2->test()->get('/users/123');

            expect($response->status())->toBe(200);
            expect($response->json())->toBe(['id' => '123']);
        });

        it('preserves middleware with cached routes', function () {
            // Warm cache
            $app1 = new App();
            $app1->get('/protected', ['CacheTestProtectedController', 'index'], ['CacheTestMiddleware']);

            $cache1 = new BootstrapCache(path: $this->cacheDir, enabled: true);
            $app1->configure($cache1);
            $cache1->warm();

            // Test with cached routes
            $app2 = new App();
            $cache2 = new BootstrapCache(path: $this->cacheDir, enabled: true);
            $app2->configure($cache2);

            $response = $app2->test()->get('/protected');

            expect($response->status())->toBe(200);
        });
    });
});

// Test helper classes
class CacheTestHomeController
{
    public function index()
    {
        return ['status' => 'ok'];
    }
}

class CacheTestUserShowController
{
    public function index($id)
    {
        return ['id' => $id];
    }
}

class CacheTestProtectedController
{
    public function index()
    {
        return ['protected' => true];
    }
}

class CacheTestMiddleware
{
    public function __invoke($request, $next)
    {
        return $next($request);
    }
}
