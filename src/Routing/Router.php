<?php

declare(strict_types=1);

namespace Verge\Routing;

use Psr\Http\Message\RequestInterface;
use Verge\Routing\Exceptions\RouteNotFoundException;

class Router implements RouterInterface, RouteMatcherInterface
{
    /** @var array<string, Route[]> */
    protected array $routes = [];

    /** @var array<string, Route> */
    protected array $namedRoutes = [];

    /**
     * @param callable|array<mixed>|string $handler
     */
    public function get(string $path, callable|array|string $handler): Route
    {
        return $this->add('GET', $path, $handler);
    }

    /**
     * @param callable|array<mixed>|string $handler
     */
    public function post(string $path, callable|array|string $handler): Route
    {
        return $this->add('POST', $path, $handler);
    }

    /**
     * @param callable|array<mixed>|string $handler
     */
    public function put(string $path, callable|array|string $handler): Route
    {
        return $this->add('PUT', $path, $handler);
    }

    /**
     * @param callable|array<mixed>|string $handler
     */
    public function patch(string $path, callable|array|string $handler): Route
    {
        return $this->add('PATCH', $path, $handler);
    }

    /**
     * @param callable|array<mixed>|string $handler
     */
    public function delete(string $path, callable|array|string $handler): Route
    {
        return $this->add('DELETE', $path, $handler);
    }

    /**
     * @param callable|array<mixed>|string $handler
     */
    public function any(string $path, callable|array|string $handler): Route
    {
        $route = new Route(
            methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
            path: $path,
            handler: $handler
        );

        $this->register($route);
        return $route;
    }

    /**
     * @param callable|array<mixed>|string $handler
     */
    public function add(string $method, string $path, callable|array|string $handler): Route
    {
        $route = new Route(
            methods: [strtoupper($method)],
            path: $path,
            handler: $handler
        );

        $routes = $this->register($route);
        return $routes[0];
    }

    public function match(RequestInterface $request): RouteMatch
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        // Normalize path
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $routes = $this->routes[$method] ?? [];

        foreach ($routes as $route) {
            $params = $route->matches($path);
            if ($params !== null) {
                return RouteMatch::found($route, $params);
            }
        }

        return RouteMatch::notFound();
    }

    /**
     * @return array{string, string[]}
     */
    protected function compilePath(string $path): array
    {
        return PathParser::compile($path);
    }

    /**
     * @return array<string, Route[]>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function registerNamedRoute(string $name, Route $route): void
    {
        if (isset($this->namedRoutes[$name])) {
            throw new \InvalidArgumentException("Route name '{$name}' is already registered");
        }
        $this->namedRoutes[$name] = $route;
    }

    public function getNamedRoute(string $name): ?Route
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function url(string $name, array $params = []): string
    {
        $route = $this->namedRoutes[$name] ?? null;

        if ($route === null) {
            throw new RouteNotFoundException("Route '{$name}' not found");
        }

        $path = $route->path;
        $usedParams = [];

        // Substitute path parameters - must handle nested braces in constraints
        foreach ($route->paramNames as $paramName) {
            if (isset($params[$paramName])) {
                // Find the parameter in the path
                $pattern = '/\{' . preg_quote($paramName, '/') . '\??(?::[^{}]*(?:\{[^{}]*\}[^{}]*)*)?\}/';
                /** @phpstan-ignore-next-line */
                $path = (string) preg_replace($pattern, (string) $params[$paramName], $path);
                $usedParams[] = $paramName;
            }
        }

        // Remove any remaining optional parameters (with potential nested braces)
        // This handles {param?}, {param?:constraint}, {param?:\d{4}}, etc.
        $path = $this->removeOptionalParams($path);

        // Clean up any double slashes from removed optional params
        $path = (string) preg_replace('#/+#', '/', $path);
        $path = rtrim($path, '/') ?: '/';

        // Remaining params become query string
        $queryParams = array_diff_key($params, array_flip($usedParams));
        if ($queryParams) {
            $path .= '?' . http_build_query($queryParams);
        }

        return $path;
    }

    /**
     * Remove optional parameters from a path, handling nested braces.
     */
    protected function removeOptionalParams(string $path): string
    {
        $result = '';
        $i = 0;
        $len = strlen($path);

        while ($i < $len) {
            if ($path[$i] === '{') {
                // Find matching closing brace
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

                // Check if this is an optional parameter (contains ?)
                if (strpos($param, '?') !== false) {
                    // Remove the preceding slash if present
                    if (strlen($result) > 0 && $result[strlen($result) - 1] === '/') {
                        $result = substr($result, 0, -1);
                    }
                    // Skip this parameter (don't add to result)
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

    /**
     * @return Route[]
     */
    public function register(Route $route): array
    {
        $routes = [];

        // Ensure path is compiled
        if ($route->pattern === null) {
            [$pattern, $paramNames] = $this->compilePath($route->path);
            $route->pattern = $pattern;
            $route->paramNames = $paramNames;
        }

        foreach ($route->methods as $method) {
            $method = strtoupper($method);

            if (!isset($this->routes[$method])) {
                $this->routes[$method] = [];
            }

            // We store the same route instance for multiple methods
            // This is efficient and allows RouteInfo to see all supported methods.
            $this->routes[$method][] = $route;

            if ($route->getName() !== null) {
                // Warning: registering the same name for multiple methods might cause conflicts
                // in namedRoutes map if not handled carefully.
                $this->registerNamedRoute($route->getName(), $route);
            }

            $routes[] = $route;
        }

        return $routes;
    }
}
