<?php

declare(strict_types=1);

namespace Verge\Routing\Explorer;

use Closure;
use Verge\Routing\PathParser;
use Verge\Routing\Route;
use Verge\Routing\RouteMatcherInterface;

class RouteExplorer
{
    /** @var RouteInfo[] */
    protected array $routes = [];

    public function __construct(RouteMatcherInterface $router)
    {
        $this->routes = $this->extract($router);
    }

    /**
     * Get all routes.
     *
     * @return RouteInfo[]
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * Filter routes by HTTP method.
     *
     * @return RouteInfo[]
     */
    public function method(string $method): array
    {
        $method = strtoupper($method);
        return array_values(array_filter(
            $this->routes,
            fn (RouteInfo $r) => $r->method === $method
        ));
    }

    /**
     * Get only named routes.
     *
     * @return RouteInfo[]
     */
    public function named(): array
    {
        return array_values(array_filter(
            $this->routes,
            fn (RouteInfo $r) => $r->name !== null
        ));
    }

    /**
     * Filter routes by path prefix.
     *
     * @return RouteInfo[]
     */
    public function prefix(string $prefix): array
    {
        return array_values(array_filter(
            $this->routes,
            fn (RouteInfo $r) => str_starts_with($r->path, $prefix)
        ));
    }

    /**
     * Get count of routes.
     */
    public function count(): int
    {
        return count($this->routes);
    }

    /**
     * Convert all routes to array format.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(fn (RouteInfo $r) => $r->toArray(), $this->routes);
    }

    /**
     * Extract route information from router.
     *
     * @return RouteInfo[]
     */
    protected function extract(RouteMatcherInterface $router): array
    {
        $routes = [];

        foreach ($router->getRoutes() as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {
                $routes[] = $this->extractRouteInfo($route, $method);
            }
        }

        return $routes;
    }

    /**
     * Extract RouteInfo from a Route instance.
     */
    protected function extractRouteInfo(Route $route, string $method): RouteInfo
    {
        return new RouteInfo(
            method: $method,
            path: $route->path,
            name: $route->getName(),
            params: $this->extractParams($route),
            middleware: $this->extractMiddleware($route),
            handler: $this->extractHandler($route),
        );
    }

    /**
     * Extract parameter information from route path.
     *
     * @return ParamInfo[]
     */
    protected function extractParams(Route $route): array
    {
        return PathParser::extractParams($route->path);
    }

    /**
     * Extract middleware information.
     *
     * @return array<int, string>
     */
    protected function extractMiddleware(Route $route): array
    {
        return array_map(function (mixed $middleware): string {
            if (is_string($middleware)) {
                return $middleware;
            }
            if (is_object($middleware)) {
                return get_class($middleware);
            }
            return 'callable';
        }, $route->getMiddleware());
    }

    /**
     * Extract handler information.
     *
     * @return array{type: 'closure'}
     *       | array{type: 'controller', class: string, method: string}
     *       | array{type: 'invokable', class: string}
     *       | array{type: 'function', name: string}
     *       | array{type: 'unknown'}
     */
    protected function extractHandler(Route $route): array
    {
        $handler = $route->handler;

        // Closure
        if ($handler instanceof Closure) {
            return ['type' => 'closure'];
        }

        // [Controller::class, 'method']
        if (is_array($handler) && count($handler) === 2 && isset($handler[0]) && isset($handler[1])) {
            /** @var string $class */
            $class = $handler[0];
            /** @var string $method */
            $method = $handler[1];

            return [
                'type' => 'controller',
                'class' => $class,
                'method' => $method,
            ];
        }

        // Invokable class string
        if (is_string($handler) && class_exists($handler)) {
            return [
                'type' => 'invokable',
                'class' => $handler,
            ];
        }

        // String function name or other callable
        if (is_string($handler)) {
            return [
                'type' => 'function',
                'name' => $handler,
            ];
        }

        return ['type' => 'unknown'];
    }
}
