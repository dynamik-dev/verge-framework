<?php

declare(strict_types=1);

namespace Verge\Routing;

use Psr\Http\Message\RequestInterface;

class Router implements RouterInterface
{
    /** @var array<string, Route[]> */
    protected array $routes = [];

    /** @var array<string, Route> */
    protected array $namedRoutes = [];

    public function get(string $path, callable|array|string $handler): Route
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array|string $handler): Route
    {
        return $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable|array|string $handler): Route
    {
        return $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, callable|array|string $handler): Route
    {
        return $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, callable|array|string $handler): Route
    {
        return $this->add('DELETE', $path, $handler);
    }

    public function any(string $path, callable|array|string $handler): Route
    {
        $route = null;
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $route = $this->add($method, $path, $handler);
        }
        return $route;
    }

    public function add(string $method, string $path, callable|array|string $handler): Route
    {
        $method = strtoupper($method);
        [$pattern, $paramNames] = $this->compilePath($path);

        $route = new Route($method, $path, $handler, $pattern, $paramNames);

        if (!isset($this->routes[$method])) {
            $this->routes[$method] = [];
        }

        $this->routes[$method][] = $route;

        return $route;
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
        $paramNames = [];
        $pattern = $path;

        // Parse parameters manually to handle nested braces in constraints
        $offset = 0;
        while (preg_match('/\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?(?::)?/', $pattern, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $fullMatchStart = $match[0][1];
            $paramName = $match[1][0];
            $isOptional = isset($match[2][0]) && $match[2][0] === '?';

            // Find the closing brace, accounting for nested braces in constraint
            $braceDepth = 1;
            $pos = $fullMatchStart + strlen($match[0][0]);
            while ($pos < strlen($pattern) && $braceDepth > 0) {
                if ($pattern[$pos] === '{') {
                    $braceDepth++;
                } elseif ($pattern[$pos] === '}') {
                    $braceDepth--;
                }
                $pos++;
            }

            $fullMatchEnd = $pos;
            $fullMatch = substr($pattern, $fullMatchStart, $fullMatchEnd - $fullMatchStart);

            // Extract constraint if present
            $constraint = '[^/]+';
            if (preg_match('/\{[^:}]+\??(:.+)\}$/', $fullMatch, $constraintMatch)) {
                $constraint = substr($constraintMatch[1], 1); // Remove leading ':'
            }

            $paramNames[] = $paramName;

            // For optional parameters, include the preceding slash in the optional group
            $replaceStart = $fullMatchStart;
            if ($isOptional && $fullMatchStart > 0 && $pattern[$fullMatchStart - 1] === '/') {
                $replaceStart = $fullMatchStart - 1;
                $replacement = "(?:/($constraint))?";
            } elseif ($isOptional) {
                $replacement = "(?:/($constraint))?";
            } else {
                $replacement = "($constraint)";
            }

            $pattern = substr($pattern, 0, $replaceStart) . $replacement . substr($pattern, $fullMatchEnd);
            $offset = $replaceStart + strlen($replacement);
        }

        $pattern = '#^' . $pattern . '$#';

        return [$pattern, $paramNames];
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
                $path = preg_replace($pattern, (string) $params[$paramName], $path);
                $usedParams[] = $paramName;
            }
        }

        // Remove any remaining optional parameters (with potential nested braces)
        // This handles {param?}, {param?:constraint}, {param?:\d{4}}, etc.
        $path = $this->removeOptionalParams($path);

        // Clean up any double slashes from removed optional params
        $path = preg_replace('#/+#', '/', $path);
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
}
