<?php

declare(strict_types=1);

namespace Verge\Routing;

use Psr\Http\Message\RequestInterface;

/**
 * Read-only interface for route matching and URL generation.
 *
 * Use this interface when you only need to match requests or generate URLs.
 * For route registration, use RouterInterface instead.
 */
interface RouteMatcherInterface
{
    public function match(RequestInterface $request): RouteMatch;

    public function getNamedRoute(string $name): ?Route;

    /**
     * @param array<string, mixed> $params
     */
    public function url(string $name, array $params = []): string;

    /**
     * @return array<string, Route[]>
     */
    public function getRoutes(): array;
}
