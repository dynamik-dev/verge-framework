<?php

declare(strict_types=1);

namespace Verge;

use Closure;
use Verge\Routing\RouterInterface;
use Verge\Routing\RoutesBuilder;

class Verge
{
    protected static ?App $app = null;

    /**
     * Create an app with optional callback for configuration.
     */
    public static function create(?callable $callback = null): App
    {
        static::$app = App::create($callback);
        return static::$app;
    }

    /**
     * @deprecated Use Verge::create() instead
     */
    public static function build(?callable $callback = null): App
    {
        static::$app = App::build($callback);
        return static::$app;
    }

    /**
     * @deprecated Use Verge::create() instead
     */
    public static function buildDefaults(): App
    {
        return static::create();
    }

    public static function app(): ?App
    {
        return static::$app;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public static function make(string $abstract, array $parameters = []): mixed
    {
        if (static::$app === null) {
            throw new \RuntimeException('No application instance. Call Verge::build() or Verge::buildDefaults() first.');
        }

        return static::$app->make($abstract, $parameters);
    }

    public static function has(string $id): bool
    {
        if (static::$app === null) {
            return false;
        }

        return static::$app->has($id);
    }

    public static function env(string $key, mixed $default = null): mixed
    {
        if (static::$app === null) {
            throw new \RuntimeException('No application instance. Call Verge::build() or Verge::buildDefaults() first.');
        }

        return static::$app->env($key, $default);
    }

    public static function reset(): void
    {
        static::$app = null;
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function route(string $name, array $params = []): string
    {
        if (static::$app === null) {
            throw new \RuntimeException('No application instance. Call Verge::create() first.');
        }

        return static::$app->url($name, $params);
    }

    public static function routes(callable $callback): RoutesBuilder
    {
        /** @var RoutesBuilder $builder */
        $builder = make(RoutesBuilder::class);
        $callback($builder);
        return $builder;
    }
}
