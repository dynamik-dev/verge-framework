<?php

declare(strict_types=1);

namespace Verge\Routing;

/**
 * Route matching result.
 */
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

/**
 * Route metadata for introspection.
 */
class RouteInfo
{
    /**
     * @param ParamInfo[] $params
     * @param array<int, string> $middleware
     * @param array{type: 'closure'}|array{type: 'controller', class: string, method: string}|array{type: 'invokable', class: string}|array{type: 'function', name: string}|array{type: 'unknown'} $handler
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly ?string $name,
        public readonly array $params,
        public readonly array $middleware,
        public readonly array $handler,
    ) {
    }

    /**
     * @return array{
     *     method: string,
     *     path: string,
     *     name: ?string,
     *     params: array<int, array{name: string, required: bool, constraint: ?string}>,
     *     middleware: array<int, string>,
     *     handler: array{type: 'closure'}
     *            | array{type: 'controller', class: string, method: string}
     *            | array{type: 'invokable', class: string}
     *            | array{type: 'function', name: string}
     *            | array{type: 'unknown'}
     * }
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'path' => $this->path,
            'name' => $this->name,
            'params' => array_map(fn (ParamInfo $p) => $p->toArray(), $this->params),
            'middleware' => $this->middleware,
            'handler' => $this->handler,
        ];
    }
}

/**
 * Parameter metadata for introspection.
 */
readonly class ParamInfo
{
    public function __construct(
        public string $name,
        public bool $required,
        public ?string $constraint,
    ) {
    }

    /**
     * @return array{name: string, required: bool, constraint: ?string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'required' => $this->required,
            'constraint' => $this->constraint,
        ];
    }
}

/**
 * Exception thrown when a named route is not found.
 */
class RouteNotFoundException extends \RuntimeException
{
}
