<?php

declare(strict_types=1);

namespace Verge\Routing;

use Closure;

class Routes
{
    /** @var RouteInfo[] */
    protected array $routes = [];

    public function __construct(RouterInterface $router)
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
    protected function extract(RouterInterface $router): array
    {
        $routes = [];

        foreach ($router->getRoutes() as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {
                $routes[] = $this->extractRouteInfo($route);
            }
        }

        return $routes;
    }

    /**
     * Extract RouteInfo from a Route instance.
     */
    protected function extractRouteInfo(Route $route): RouteInfo
    {
        return new RouteInfo(
            method: $route->method,
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
     * @return array<int, array{name: string, required: bool, constraint: ?string}>
     */
    protected function extractParams(Route $route): array
    {
        $params = [];

        // Parse the path to find parameters
        // Match {name}, {name?}, {name:constraint}, {name?:constraint}
        $offset = 0;
        $path = $route->path;

        while (preg_match('/\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?(?::)?/', $path, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $paramName = $match[1][0];
            $isOptional = isset($match[2][0]);
            $matchStart = $match[0][1];

            // Find the closing brace, accounting for nested braces in constraint
            $braceDepth = 1;
            $pos = $matchStart + strlen($match[0][0]);

            while ($pos < strlen($path) && $braceDepth > 0) {
                if ($path[$pos] === '{') {
                    $braceDepth++;
                } elseif ($path[$pos] === '}') {
                    $braceDepth--;
                }
                $pos++;
            }

            $fullMatch = substr($path, $matchStart, $pos - $matchStart);

            // Extract constraint if present
            $constraint = null;
            if (preg_match('/\{[^:}]+\??(:.+)\}$/', $fullMatch, $constraintMatch)) {
                $constraint = substr($constraintMatch[1], 1); // Remove leading ':'
            }

            $params[] = [
                'name' => $paramName,
                'required' => !$isOptional,
                'constraint' => $constraint,
            ];

            $offset = $pos;
        }

        return $params;
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
