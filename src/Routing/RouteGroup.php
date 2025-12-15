<?php

declare(strict_types=1);

namespace Verge\Routing;

use Verge\Concerns\HasMiddleware;

class RouteGroup
{
    use HasMiddleware;

    /** @var Route[] */
    protected array $routes = [];

    public function __construct(
        protected string $prefix
    ) {
    }

    public function addRoute(Route $route): void
    {
        $this->routes[] = $route;
    }

    public function use(callable|string $middleware): static
    {
        $this->middleware[] = $middleware;

        // Apply middleware to all routes in the group
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

    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
