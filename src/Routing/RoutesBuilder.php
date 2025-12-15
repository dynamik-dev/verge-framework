<?php

declare(strict_types=1);

namespace Verge\Routing;

class RoutesBuilder
{
    /** @var Route[] */
    private array $routes = [];

    public function __construct(
        private RouterInterface $router
    ) {}

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
        return $this->add('ANY', $path, $handler);
    }

    /**
     * @param callable|array<mixed>|string $handler
     */
    protected function add(string $method, string $path, callable|array|string $handler): Route
    {
        $route = $this->router->add($method, $path, $handler);
        $this->routes[] = $route;
        return $route;
    }

    /**
     * Apply middleware to all routes defined in this builder.
     */
    public function use(callable|string $middleware): static
    {
        foreach ($this->routes as $route) {
            $route->use($middleware);
        }
        return $this;
    }

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
