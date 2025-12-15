<?php

declare(strict_types=1);

namespace Verge\Http;

use Closure;
use Verge\Container;
use Verge\Routing\RouteMatcherInterface;

class RequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private Container $container,
    ) {}

    public function handle(Request $request): Response
    {
        try {
            $this->container->instance(Request::class, $request);

            $router = $this->container->resolve(RouteMatcherInterface::class);
            if (!$router instanceof RouteMatcherInterface) {
                throw new \RuntimeException('Resolved service is not a RouteMatcherInterface');
            }

            $match = $router->match($request);

            if (!$match->matched) {
                $json = json_encode(['error' => 'Not Found']);
                return new Response(
                    $json !== false ? $json : '{"error": "Not Found"}',
                    404,
                    ['Content-Type' => 'application/json']
                );
            }

            $route = $match->route;
            if ($route === null) {
                throw new \RuntimeException('Route matched but route object is null');
            }

            // Build middleware stack
            $middlewareStack = $route->getMiddleware();

            // Create the final handler - always returns a Response
            $handler = fn (Request $req) => $this->prepareResponse(
                $this->executeHandler($route->handler, $match->params, $req)
            );

            // Wrap handler with middleware
            $pipeline = array_reduce(
                array_reverse($middlewareStack),
                fn ($next, $middleware) => fn (Request $req) => $this->executeMiddleware($middleware, $req, $next),
                $handler
            );

            $result = $pipeline($request);
            if (!$result instanceof Response) {
                $content = is_scalar($result) || $result instanceof \Stringable ? (string) $result : '';
                return new Response($content, 200);
            }
            return $result;
        } finally {
            $this->container->forgetScopedInstances();
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function executeHandler(mixed $handler, array $params, Request $request): mixed
    {
        // Closure
        if ($handler instanceof Closure) {
            return $this->container->call($handler, $params);
        }

        // [Controller::class, 'method']
        if (is_array($handler)) {
            [$class, $method] = $handler;
            if (!is_string($class) || !is_string($method)) {
                throw new \RuntimeException('Invalid array handler: expected [class, method]');
            }
            $instance = $this->container->resolve($class);

            $callback = [$instance, $method];
            if (!is_callable($callback)) {
                throw new \RuntimeException("Method {$method} not callable on resolved instance");
            }
            return $this->container->call($callback, $params);
        }

        // Invokable class string
        if (is_string($handler) && class_exists($handler)) {
            $instance = $this->container->resolve($handler);
            if (!is_callable($instance)) {
                throw new \RuntimeException("Resolved handler {$handler} is not invokable");
            }
            return $this->container->call($instance, $params);
        }

        throw new \RuntimeException('Invalid route handler');
    }

    protected function executeMiddleware(callable|string|object $middleware, Request $request, callable $next): mixed
    {
        if (is_string($middleware)) {
            $middleware = $this->container->resolve($middleware);
        }

        if (!is_callable($middleware)) {
            throw new \RuntimeException('Middleware must be callable');
        }

        return $middleware($request, $next);
    }

    protected function prepareResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if ($result === null) {
            return new Response('', 204);
        }

        if (is_array($result)) {
            try {
                $json = json_encode($result, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \RuntimeException('Failed to encode response to JSON: ' . $e->getMessage(), 0, $e);
            }
            return new Response(
                $json,
                200,
                ['Content-Type' => 'application/json']
            );
        }

        if (is_string($result)) {
            return new Response($result, 200, ['Content-Type' => 'text/plain']);
        }

        if ($result instanceof \Stringable || (is_object($result) && method_exists($result, '__toString'))) {
            return new Response((string) $result, 200, ['Content-Type' => 'text/plain']);
        }

        throw new \RuntimeException('Unable to convert handler result to response');
    }
}
