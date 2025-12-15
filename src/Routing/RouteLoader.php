<?php

declare(strict_types=1);

namespace Verge\Routing;

use ReflectionClass;
use ReflectionMethod;
use Verge\Routing\Attribute\Route as RouteAttribute;

class RouteLoader
{
    public function __construct(protected RouterInterface $router)
    {
    }

    /**
     * Register routes from a controller class.
     *
     * @param class-string|object $controller
     */
    public function registerController(string|object $controller): void
    {
        $reflection = new ReflectionClass($controller);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        // Resolve controller-level configuration
        $controllerPrefix = '';
        $controllerMiddleware = [];

        $instance = is_object($controller) ? $controller : null;
        $resolveInstance = function () use (&$instance, $reflection) {
            if ($instance === null) {
                $instance = $reflection->newInstance();
            }
            return $instance;
        };

        // Check for prefix configuration
        if ($reflection->hasMethod('prefix')) {
            $prefixMethod = $reflection->getMethod('prefix');
            if ($prefixMethod->isPublic() && !$prefixMethod->isAbstract() && empty($prefixMethod->getAttributes(RouteAttribute::class))) {
                $target = $prefixMethod->isStatic() ? null : $resolveInstance();
                $result = $prefixMethod->invoke($target);
                $controllerPrefix = is_string($result) ? $result : '';
            }
        }

        // Check for middleware configuration
        if ($reflection->hasMethod('middleware')) {
            $middlewareMethod = $reflection->getMethod('middleware');
            if ($middlewareMethod->isPublic() && !$middlewareMethod->isAbstract() && empty($middlewareMethod->getAttributes(RouteAttribute::class))) {
                $target = $middlewareMethod->isStatic() ? null : $resolveInstance();
                $controllerMiddleware = (array) $middlewareMethod->invoke($target);
            }
        }

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(RouteAttribute::class);

            foreach ($attributes as $attribute) {
                /** @var RouteAttribute $routeAttr */
                $routeAttr = $attribute->newInstance();

                $httpMethods = (array) $routeAttr->method;

                // Apply prefix
                $path = $routeAttr->path;
                if ($controllerPrefix !== '') {
                    $path = rtrim($controllerPrefix, '/') . '/' . ltrim($path, '/');
                }

                if ($path === '') {
                    $path = '/';
                }

                $handler = [$controller, $method->getName()];
                $middleware = array_merge($controllerMiddleware, (array) $routeAttr->middleware);

                $route = new Route(
                    methods: (array) $routeAttr->method,
                    path: $path,
                    handler: $handler
                );

                if ($routeAttr->name !== null) {
                    $route->name($routeAttr->name);
                }

                foreach ($middleware as $mw) {
                    if (is_callable($mw) || is_string($mw) || is_object($mw)) {
                        $route->use($mw);
                    }
                }

                $this->router->register($route);
            }
        }
    }
    /**
     * Register multiple controllers.
     *
     * @param array<class-string|object> $controllers
     */
    public function registerControllers(array $controllers): void
    {
        foreach ($controllers as $controller) {
            $this->registerController($controller);
        }
    }
}
