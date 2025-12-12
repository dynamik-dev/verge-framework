<?php

declare(strict_types=1);

namespace Verge\Routing;

use Psr\Http\Message\RequestInterface;

interface RouterInterface
{
    public function add(string $method, string $path, callable|array|string $handler): Route;

    public function match(RequestInterface $request): RouteMatch;

    public function registerNamedRoute(string $name, Route $route): void;

    public function getNamedRoute(string $name): ?Route;

    public function url(string $name, array $params = []): string;

    /**
     * @return array<string, Route[]>
     */
    public function getRoutes(): array;
}
