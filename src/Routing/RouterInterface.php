<?php

declare(strict_types=1);

namespace Verge\Routing;

/**
 * Interface for route registration (write operations).
 *
 * Extends RouteMatcherInterface to include read operations (matching, URL generation).
 */
interface RouterInterface extends RouteMatcherInterface
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
