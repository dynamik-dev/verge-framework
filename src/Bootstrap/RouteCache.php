<?php

declare(strict_types=1);

namespace Verge\Bootstrap;

use Closure;
use Verge\Routing\Route;
use Verge\Routing\RouterInterface;

/**
 * Handles route cache serialization and loading.
 */
class RouteCache
{
    public function __construct(
        private string $cachePath
    ) {
    }

    /**
     * Warm the route cache by extracting and optimizing routes from the router.
     */
    public function warm(RouterInterface $router): RouteCacheResult
    {
        $routes = $router->getRoutes();
        $cacheable = [];
        $skipped = [];

        foreach ($routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {
                if ($this->isCacheable($route)) {
                    $cacheable[] = $this->extractRouteData($route);
                } else {
                    $skipped[] = [
                        'method' => $route->method,
                        'path' => $route->path,
                        'reason' => $this->getSkipReason($route),
                    ];
                }
            }
        }

        $cacheData = $this->buildOptimizedCache($cacheable);
        $this->writeCacheFile($cacheData);

        return new RouteCacheResult(
            cached: count($cacheable),
            skipped: $skipped,
            handlers: $this->extractHandlerClasses($cacheable)
        );
    }

    /**
     * Load cached route data.
     * @return array<string, mixed>
     */
    public function load(): array
    {
        if (!$this->isCached()) {
            throw new \RuntimeException("Route cache not found at: {$this->cachePath}");
        }

        /** @var array<string, mixed> */
        return require $this->cachePath;
    }

    /**
     * Check if the route cache exists.
     */
    public function isCached(): bool
    {
        return file_exists($this->cachePath);
    }

    /**
     * Clear the route cache.
     */
    public function clear(): bool
    {
        if (file_exists($this->cachePath)) {
            return unlink($this->cachePath);
        }
        return true;
    }

    /**
     * Get the cache file path.
     */
    public function getPath(): string
    {
        return $this->cachePath;
    }

    /**
     * Check if a route can be cached (no closures).
     */
    private function isCacheable(Route $route): bool
    {
        // Closure handlers cannot be serialized
        if ($route->handler instanceof Closure) {
            return false;
        }

        // Check middleware for closures
        foreach ($route->getMiddleware() as $middleware) {
            if ($middleware instanceof Closure) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the reason a route was skipped.
     */
    private function getSkipReason(Route $route): string
    {
        if ($route->handler instanceof Closure) {
            return 'Handler is a Closure';
        }

        foreach ($route->getMiddleware() as $middleware) {
            if ($middleware instanceof Closure) {
                return 'Middleware contains a Closure';
            }
        }

        return 'Unknown';
    }

    /**
     * Extract route data for caching.
     * @return array<string, mixed>
     */
    private function extractRouteData(Route $route): array
    {
        return [
            'method' => $route->method,
            'path' => $route->path,
            'pattern' => $route->pattern,
            'paramNames' => $route->paramNames,
            'handler' => $route->handler,
            'middleware' => $route->getMiddleware(),
            'name' => $route->getName(),
        ];
    }

    /**
     * Build an optimized cache structure.
     * @param array<int, array<string, mixed>> $routes
     * @return array<string, mixed>
     */
    private function buildOptimizedCache(array $routes): array
    {
        $static = [];
        $dynamic = [];
        $named = [];

        foreach ($routes as $routeData) {
            /** @var string $method */
            $method = $routeData['method'];
            /** @var string $path */
            $path = $routeData['path'];

            if (empty($routeData['paramNames'])) {
                // Static route - no parameters
                if (!isset($static[$method])) {
                    $static[$method] = [];
                }
                $static[$method][$path] = $routeData;
            } else {
                // Dynamic route - group by segment count
                $segmentCount = $this->countSegments($path);
                if (!isset($dynamic[$method])) {
                    $dynamic[$method] = [];
                }
                if (!isset($dynamic[$method][$segmentCount])) {
                    $dynamic[$method][$segmentCount] = [];
                }
                $dynamic[$method][$segmentCount][] = $routeData;
            }

            // Track named routes
            $name = $routeData['name'] ?? null;
            if (!empty($name) && is_string($name)) {
                $named[$name] = [
                    'method' => $method,
                    'path' => $path,
                    'paramNames' => $routeData['paramNames'],
                ];
            }
        }

        return [
            'generated' => time(),
            'count' => count($routes),
            'static' => $static,
            'dynamic' => $dynamic,
            'named' => $named,
        ];
    }

    /**
     * Write the cache file.
     * @param array<string, mixed> $cacheData
     */
    private function writeCacheFile(array $cacheData): void
    {
        $dir = dirname($this->cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $count = $cacheData['count'] ?? 0;
        $countStr = is_scalar($count) ? (string) $count : '0';

        $content = "<?php\n\n";
        $content .= "// Generated by Verge BootstrapCache\n";
        $content .= "// Generated: " . date('Y-m-d H:i:s') . "\n";
        $content .= "// Routes: " . $countStr . "\n";
        $content .= "// DO NOT EDIT - This file is auto-generated\n\n";
        $content .= "return " . $this->exportArray($cacheData) . ";\n";

        file_put_contents($this->cachePath, $content);

        // Clear OPcache for this file if available
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->cachePath, true);
        }
    }

    /**
     * Export an array as PHP code.
     * @param array<mixed> $array
     */
    private function exportArray(array $array, int $indent = 0): string
    {
        $spaces = str_repeat('    ', $indent);
        $innerSpaces = str_repeat('    ', $indent + 1);

        $output = "[\n";

        foreach ($array as $key => $value) {
            $keyStr = is_int($key) ? '' : var_export($key, true) . ' => ';

            if (is_array($value)) {
                $output .= $innerSpaces . $keyStr . $this->exportArray($value, $indent + 1) . ",\n";
            } else {
                $output .= $innerSpaces . $keyStr . var_export($value, true) . ",\n";
            }
        }

        $output .= $spaces . "]";

        return $output;
    }

    /**
     * Count path segments.
     */
    private function countSegments(string $path): int
    {
        if ($path === '/') {
            return 0;
        }
        return substr_count($path, '/');
    }

    /**
     * Extract handler class names for container caching.
     * @param array<int, array<string, mixed>> $routes
     * @return array<int, string>
     */
    private function extractHandlerClasses(array $routes): array
    {
        $classes = [];

        foreach ($routes as $routeData) {
            $handler = $routeData['handler'];

            // [Controller::class, 'method'] format
            if (is_array($handler) && isset($handler[0]) && is_string($handler[0])) {
                $classes[] = $handler[0];
            }
            // InvokableController::class format
            elseif (is_string($handler) && class_exists($handler)) {
                $classes[] = $handler;
            }

            // Middleware classes
            $middlewareList = $routeData['middleware'] ?? [];
            if (!is_array($middlewareList)) {
                $middlewareList = [$middlewareList];
            }
            foreach ($middlewareList as $middleware) {
                if (is_string($middleware) && class_exists($middleware)) {
                    $classes[] = $middleware;
                }
            }
        }

        return array_unique($classes);
    }
}
