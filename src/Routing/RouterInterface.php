<?php

declare(strict_types=1);

namespace Verge\Routing;

/**
 * Interface for route registration (write operations).
 *
 * For read operations (matching, URL generation), see RouteMatcherInterface.
 */
interface RouterInterface
{
    /**
     * @param callable|array<mixed>|string $handler
     */
    public function add(string $method, string $path, callable|array|string $handler): Route;

    /**
     * @param callable|array<mixed>|string $handler
     */
    public function any(string $path, callable|array|string $handler): Route;

    public function registerNamedRoute(string $name, Route $route): void;

    /**
     * @return Route[]
     */
    public function register(Route $route): array;
}
