<?php

declare(strict_types=1);

namespace Verge\Bundler;

use Closure;
use Verge\Routing\Route;

/**
 * Generates a routes.php file with closure routes replaced by handler classes.
 */
class RoutesGenerator
{
    /**
     * @param array<string, array<Route>> $routes Routes grouped by method
     * @param array<string, string> $handlers Map of "METHOD /path" => handler class
     */
    public function generate(array $routes, array $handlers): string
    {
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            '/**',
            ' * Generated routes file.',
            ' * Closure routes have been converted to handler classes.',
            ' */',
            '',
            'return function (\\Verge\\App $app): void {',
        ];

        foreach ($routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {
                $routeKey = "{$method} {$route->path}";
                $handler = $this->formatHandler($route, $handlers[$routeKey] ?? null);
                $methodLower = strtolower($method);

                // Build the route call
                $routeCall = "    \$app->{$methodLower}('{$route->path}', {$handler})";

                // Add middleware if present
                $middleware = $route->getMiddleware();
                if (!empty($middleware)) {
                    $middlewareStr = $this->formatMiddleware($middleware);
                    $routeCall .= "->use({$middlewareStr})";
                }

                // Add name if present
                $name = $route->getName();
                if ($name !== null) {
                    $routeCall .= "->name('{$name}')";
                }

                $routeCall .= ';';
                $lines[] = $routeCall;
            }
        }

        $lines[] = '};';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Format the handler for output.
     */
    private function formatHandler(Route $route, ?string $generatedHandler): string
    {
        // Use generated handler if available
        if ($generatedHandler !== null) {
            return '\\' . $generatedHandler . '::class';
        }

        // Keep original handler
        $handler = $route->handler;

        if ($handler instanceof Closure) {
            // This shouldn't happen if bundler worked correctly
            return "'__UNCONVERTED_CLOSURE__'";
        }

        if (is_array($handler)) {
            // [Controller::class, 'method']
            return "[\\{$handler[0]}::class, '{$handler[1]}']";
        }

        if (is_string($handler)) {
            // Controller::class or 'Controller@method'
            if (class_exists($handler)) {
                return '\\' . $handler . '::class';
            }
            return "'{$handler}'";
        }

        return "'{$handler}'";
    }

    /**
     * Format middleware array for output.
     */
    private function formatMiddleware(array $middleware): string
    {
        $items = [];

        foreach ($middleware as $m) {
            if (is_string($m)) {
                if (class_exists($m)) {
                    $items[] = '\\' . $m . '::class';
                } else {
                    $items[] = "'{$m}'";
                }
            } elseif (is_array($m)) {
                $items[] = "[\\{$m[0]}::class, '{$m[1]}']";
            }
        }

        if (count($items) === 1) {
            return $items[0];
        }

        return '[' . implode(', ', $items) . ']';
    }
}
