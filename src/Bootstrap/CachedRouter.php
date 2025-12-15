<?php

declare(strict_types=1);

namespace Verge\Bootstrap;

use Psr\Http\Message\RequestInterface;
use Verge\Routing\Route;
use Verge\Routing\RouteMatch;
use Verge\Routing\RouteNotFoundException;
use Verge\Routing\RouterInterface;

/**
 * A read-only router that loads pre-cached route data for optimized matching.
 *
 * Static routes use O(1) hash lookup, dynamic routes are grouped by
 * segment count to minimize regex matching iterations.
 */
class CachedRouter implements RouterInterface
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $static = [];

    /** @var array<string, array<int, array<int, array<string, mixed>>>> */
    private array $dynamic = [];

    /** @var array<string, array<string, mixed>> */
    private array $named = [];

    /** @var array<string, Route[]> Reconstructed Route objects for getRoutes() */
    private array $routes = [];

    /** @var array<string, Route> */
    private array $namedRoutes = [];

    /**
     * @param array<string, mixed> $cacheData
     */
    public function __construct(array $cacheData)
    {
        /** @var array<string, array<string, array<string, mixed>>> */
        $static = $cacheData['static'] ?? [];
        $this->static = $static;

        /** @var array<string, array<int, array<int, array<string, mixed>>>> */
        $dynamic = $cacheData['dynamic'] ?? [];
        $this->dynamic = $dynamic;

        /** @var array<string, array<string, mixed>> */
        $named = $cacheData['named'] ?? [];
        $this->named = $named;

        $this->buildRouteObjects();
    }

    public function match(RequestInterface $request): RouteMatch
    {
        $method = strtoupper($request->getMethod());
        $path = $this->normalizePath($request->getUri()->getPath());

        // 1. Try static routes first (O(1) hash lookup)
        if (isset($this->static[$method][$path])) {
            $routeData = $this->static[$method][$path];
            $route = $this->createRoute($method, $path, $routeData);
            return RouteMatch::found($route, []);
        }

        // 2. Try dynamic routes grouped by segment count
        $segmentCount = $this->countSegments($path);
        $dynamicRoutes = $this->dynamic[$method][$segmentCount] ?? [];

        foreach ($dynamicRoutes as $routeData) {
            /** @var string $pattern */
            $pattern = $routeData['pattern'];
            /** @var array<int, string> $paramNames */
            $paramNames = $routeData['paramNames'];
            /** @var string $routePath */
            $routePath = $routeData['path'];

            if (preg_match($pattern, $path, $matches)) {
                $params = $this->extractParams($paramNames, $matches);
                $route = $this->createRoute($method, $routePath, $routeData);
                return RouteMatch::found($route, $params);
            }
        }

        // 3. Check dynamic routes with different segment counts (for optional params)
        foreach ($this->dynamic[$method] ?? [] as $count => $routes) {
            if ($count === $segmentCount) {
                continue; // Already checked
            }
            foreach ($routes as $routeData) {
                /** @var string $pattern */
                $pattern = $routeData['pattern'];
                /** @var array<int, string> $paramNames */
                $paramNames = $routeData['paramNames'];
                /** @var string $routePath */
                $routePath = $routeData['path'];

                if (preg_match($pattern, $path, $matches)) {
                    $params = $this->extractParams($paramNames, $matches);
                    $route = $this->createRoute($method, $routePath, $routeData);
                    return RouteMatch::found($route, $params);
                }
            }
        }

        return RouteMatch::notFound();
    }

    public function add(string $method, string $path, callable|array|string $handler): Route
    {
        throw new \RuntimeException(
            'Cannot add routes to a cached router. ' .
            'Clear the route cache to add new routes.'
        );
    }

    public function any(string $path, callable|array|string $handler): Route
    {
        throw new \RuntimeException(
            'Cannot add routes to a cached router. ' .
            'Clear the route cache to add new routes.'
        );
    }

    public function registerNamedRoute(string $name, Route $route): void
    {
        throw new \RuntimeException(
            'Cannot register named routes on a cached router. ' .
            'Clear the route cache to register new routes.'
        );
    }

    public function getNamedRoute(string $name): ?Route
    {
        return $this->namedRoutes[$name] ?? null;
    }

    public function url(string $name, array $params = []): string
    {
        $routeInfo = $this->named[$name] ?? null;

        if ($routeInfo === null) {
            throw new RouteNotFoundException("Route '{$name}' not found");
        }

        /** @var string $path */
        $path = $routeInfo['path'];
        $usedParams = [];
        /** @var array<int, string> $paramNames */
        $paramNames = $routeInfo['paramNames'];

        // Substitute path parameters
        foreach ($paramNames as $paramName) {
            if (isset($params[$paramName])) {
                // Find and replace the parameter placeholder
                $pattern = '/\{' . preg_quote($paramName, '/') . '\??(?::[^{}]*(?:\{[^{}]*\}[^{}]*)*)?\}/';
                
                $val = $params[$paramName];
                $strVal = is_scalar($val) || $val instanceof \Stringable ? (string) $val : '';
                
                $path = preg_replace($pattern, $strVal, $path) ?? $path;
                $usedParams[] = $paramName;
            }
        }

        // Remove optional parameters
        $path = $this->removeOptionalParams($path);

        // Clean up double slashes
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        // Remaining params become query string
        $queryParams = array_diff_key($params, array_flip($usedParams));
        if ($queryParams) {
            $path .= '?' . http_build_query($queryParams);
        }

        return $path;
    }

    /**
     * @return array<string, Route[]>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    private function countSegments(string $path): int
    {
        if ($path === '/') {
            return 0;
        }
        return substr_count($path, '/');
    }

    /**
     * @param array<int, string> $paramNames
     * @param array<int, string> $matches
     * @return array<string, string>
     */
    private function extractParams(array $paramNames, array $matches): array
    {
        $params = [];
        foreach ($paramNames as $index => $name) {
            $value = $matches[$index + 1] ?? null;
            if ($value !== null && $value !== '') {
                $params[$name] = $value;
            }
        }
        return $params;
    }

    /**
     * @param array<string, mixed> $routeData
     */
    private function createRoute(string $method, string $path, array $routeData): Route
    {
        /** @var callable|array{0: class-string, 1: string}|class-string $handler */
        $handler = $routeData['handler'];
        /** @var string|null $pattern */
        $pattern = $routeData['pattern'] ?? null;
        /** @var array<int, string> $paramNames */
        $paramNames = $routeData['paramNames'] ?? [];

        $route = new Route(
            $method,
            $path,
            $handler,
            $pattern ?? '#^' . preg_quote($path, '#') . '$#',
            $paramNames
        );

        // Apply middleware
        /** @var array<int, string> $middlewareList */
        $middlewareList = $routeData['middleware'] ?? [];
        foreach ($middlewareList as $middleware) {
            $route->use($middleware);
        }

        // Set name if present
        if (!empty($routeData['name']) && is_string($routeData['name'])) {
            $route->name($routeData['name']);
        }

        return $route;
    }

    private function buildRouteObjects(): void
    {
        // Build routes from static routes
        foreach ($this->static as $method => $routes) {
            if (!isset($this->routes[$method])) {
                $this->routes[$method] = [];
            }
            foreach ($routes as $path => $routeData) {
                $route = $this->createRoute($method, $path, $routeData);
                $this->routes[$method][] = $route;

                if (isset($routeData['name']) && is_string($routeData['name'])) {
                    $this->namedRoutes[$routeData['name']] = $route;
                }
            }
        }

        // Build routes from dynamic routes
        foreach ($this->dynamic as $method => $segmentGroups) {
            if (!isset($this->routes[$method])) {
                $this->routes[$method] = [];
            }
            foreach ($segmentGroups as $routes) {
                foreach ($routes as $routeData) {
                    /** @var string $routePath */
                    $routePath = $routeData['path'];
                    $route = $this->createRoute($method, $routePath, $routeData);
                    $this->routes[$method][] = $route;

                    if (isset($routeData['name']) && is_string($routeData['name'])) {
                        $this->namedRoutes[$routeData['name']] = $route;
                    }
                }
            }
        }
    }

    private function removeOptionalParams(string $path): string
    {
        $result = '';
        $i = 0;
        $len = strlen($path);

        while ($i < $len) {
            if ($path[$i] === '{') {
                $braceDepth = 1;
                $start = $i;
                $i++;

                while ($i < $len && $braceDepth > 0) {
                    if ($path[$i] === '{') {
                        $braceDepth++;
                    } elseif ($path[$i] === '}') {
                        $braceDepth--;
                    }
                    $i++;
                }

                $param = substr($path, $start, $i - $start);

                if (strpos($param, '?') !== false) {
                    if (strlen($result) > 0 && $result[strlen($result) - 1] === '/') {
                        $result = substr($result, 0, -1);
                    }
                } else {
                    $result .= $param;
                }
            } else {
                $result .= $path[$i];
                $i++;
            }
        }

        return $result;
    }
}
