<?php

declare(strict_types=1);

namespace Verge\Routing;

class RouteMatch
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public readonly bool $matched,
        public readonly ?Route $route = null,
        public readonly array $params = []
    ) {
    }

    public static function notFound(): self
    {
        return new self(false);
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function found(Route $route, array $params = []): self
    {
        return new self(true, $route, $params);
    }
}
