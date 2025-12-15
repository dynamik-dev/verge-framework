<?php

declare(strict_types=1);

namespace Verge\Bootstrap;

use Verge\App;
use Verge\Routing\RouterInterface;

/**
 * Bootstrap cache provider for Verge.
 *
 * Provides Laravel-style caching of routes and container metadata
 * for improved performance in production environments.
 *
 * Simple usage (auto-detects production from APP_ENV):
 *   $app->configure(BootstrapCache::class)
 *       ->get('/', [HomeController::class, 'index']);
 *
 * Custom configuration:
 *   $app->configure(new BootstrapCache(
 *       path: __DIR__ . '/bootstrap/cache',
 *       enabled: true,
 *   ));
 *
 * Warming the cache (run during deployment):
 *   $app->make(BootstrapCache::class)->warm();
 *
 * Clearing the cache:
 *   $app->make(BootstrapCache::class)->clear();
 *
 * Environment variables:
 *   - VERGE_CACHE_PATH: Override default cache directory
 *   - APP_ENV=production: Auto-enables caching
 */
class BootstrapCache
{
    private ?App $app = null;
    private ?RouteCache $routeCache = null;
    private ?ContainerCache $containerCache = null;
    private string $path;
    private bool $enabled;

    public function __construct(
        ?string $path = null,
        ?bool $enabled = null
    ) {
        // Default path: check env, then use cwd/bootstrap/cache
        $envPath = $_ENV['VERGE_CACHE_PATH'] ?? null;
        $this->path = $path
            ?? (is_string($envPath) ? $envPath : null)
            ?? getcwd() . '/bootstrap/cache';

        // Default enabled: production only (check common env patterns)
        $this->enabled = $enabled
            ?? $this->detectProductionMode();
    }

    private function detectProductionMode(): bool
    {
        $env = $_ENV['APP_ENV']
            ?? $_ENV['VERGE_ENV']
            ?? $_SERVER['APP_ENV']
            ?? 'development';

        return $env === 'production' || $env === 'prod';
    }

    /**
     * Invoked when passed to $app->configure().
     */
    public function __invoke(App $app): void
    {
        $this->app = $app;

        // Register self in container for later access
        $app->instance(self::class, $this);

        if (!$this->enabled) {
            return;
        }

        $this->routeCache = new RouteCache($this->getRouteCachePath());
        $this->containerCache = new ContainerCache($this->getContainerCachePath());

        // Register cache instances
        $app->instance(RouteCache::class, $this->routeCache);
        $app->instance(ContainerCache::class, $this->containerCache);

        // If routes are cached, load CachedRouter
        if ($this->routeCache->isCached()) {
            $cachedRouter = new CachedRouter($this->routeCache->load());
            $app->routes($cachedRouter);
        }

        // If container metadata is cached, inject into container
        if ($this->containerCache->isCached()) {
            $app->container->setReflectionCache($this->containerCache->load());
        }
    }

    /**
     * Warm all caches.
     *
     * Should be called during deployment after routes are registered.
     */
    public function warm(): WarmResult
    {
        if ($this->app === null) {
            throw new \RuntimeException('BootstrapCache must be configured via $app->configure() before warming');
        }

        $this->ensureCacheDirectory();

        // Get route cache (or create new one)
        $routeCache = $this->routeCache ?? new RouteCache($this->getRouteCachePath());

        // Get current router (should be the original, not cached)
        /** @var App $app */
        $app = $this->app;
        $router = $app->container->resolve(RouterInterface::class);
        if (!$router instanceof RouterInterface) {
            throw new \RuntimeException('Resolved service does not implement RouterInterface');
        }

        // If using CachedRouter, we can't re-cache - need original routes
        if ($router instanceof CachedRouter) {
            throw new \RuntimeException(
                'Cannot warm cache from CachedRouter. ' .
                'Clear the cache first, then run your application to register routes, then warm.'
            );
        }

        // Warm route cache
        $routeResult = $routeCache->warm($router);

        // Warm container cache with handler classes
        $containerCache = $this->containerCache ?? new ContainerCache($this->getContainerCachePath());
        $containerResult = $containerCache->warm($routeResult->handlers);

        return new WarmResult($routeResult, $containerResult);
    }

    /**
     * Clear all caches.
     */
    public function clear(): void
    {
        $this->ensureCacheDirectory();

        $routeCache = $this->routeCache ?? new RouteCache($this->getRouteCachePath());
        $routeCache->clear();

        $containerCache = $this->containerCache ?? new ContainerCache($this->getContainerCachePath());
        $containerCache->clear();
    }

    /**
     * Check if caches exist.
     */
    public function isCached(): bool
    {
        $routeCache = $this->routeCache ?? new RouteCache($this->getRouteCachePath());
        return $routeCache->isCached();
    }

    /**
     * Get cache status information.
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $routePath = $this->getRouteCachePath();
        $containerPath = $this->getContainerCachePath();

        return [
            'enabled' => $this->enabled,
            'path' => $this->path,
            'routes' => [
                'cached' => file_exists($routePath),
                'path' => $routePath,
                'size' => file_exists($routePath) ? filesize($routePath) : 0,
                'modified' => file_exists($routePath) ? filemtime($routePath) : null,
            ],
            'container' => [
                'cached' => file_exists($containerPath),
                'path' => $containerPath,
                'size' => file_exists($containerPath) ? filesize($containerPath) : 0,
                'modified' => file_exists($containerPath) ? filemtime($containerPath) : null,
            ],
        ];
    }

    /**
     * Check if caching is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get the cache directory path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    private function getRouteCachePath(): string
    {
        return $this->path . '/routes.php';
    }

    private function getContainerCachePath(): string
    {
        return $this->path . '/container.php';
    }

    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }
}
