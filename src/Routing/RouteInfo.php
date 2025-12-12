<?php

declare(strict_types=1);

namespace Verge\Routing;

class RouteInfo
{
    /**
     * @param array<int, array{name: string, required: bool, constraint: ?string}> $params
     * @param array<int, string> $middleware
     * @param array{type: string, class?: string, method?: string} $handler
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly ?string $name,
        public readonly array $params,
        public readonly array $middleware,
        public readonly array $handler,
    ) {}

    /**
     * @return array{method: string, path: string, name: ?string, params: array, middleware: array, handler: array}
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'path' => $this->path,
            'name' => $this->name,
            'params' => $this->params,
            'middleware' => $this->middleware,
            'handler' => $this->handler,
        ];
    }
}
