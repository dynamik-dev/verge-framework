<?php

declare(strict_types=1);

namespace Verge\Console\Commands;

use Verge\App;
use Verge\Console\Output;

/**
 * List all registered routes.
 */
class RoutesListCommand
{
    public function __invoke(App $app, Output $output): int
    {
        $routes = $app->routes();
        $routeList = $routes->all();

        if (empty($routeList)) {
            $output->info('No routes registered.');
            return 0;
        }

        $rows = [];
        foreach ($routeList as $route) {
            $rows[] = [
                $route->method,
                $route->path,
                $route->name ?? '-',
                $this->formatHandler($route->handler),
                $this->formatMiddleware($route->middleware),
            ];
        }

        $output->table(
            ['Method', 'Path', 'Name', 'Handler', 'Middleware'],
            $rows
        );

        $output->line('');
        $output->success('Total: ' . count($routeList) . ' routes');

        return 0;
    }

    /**
     * @param array{type: string, class?: string, method?: string, name?: string} $handler
     */
    private function formatHandler(array $handler): string
    {
        return match ($handler['type']) {
            'closure' => '(closure)',
            'controller' => $this->classBasename($handler['class'] ?? '') . '@' . ($handler['method'] ?? ''),
            'invokable' => $this->classBasename($handler['class'] ?? ''),
            'function' => $handler['name'] ?? '(function)',
            default => '(unknown)',
        };
    }

    /**
     * @param array<int, string> $middleware
     */
    private function formatMiddleware(array $middleware): string
    {
        if (empty($middleware)) {
            return '-';
        }

        $names = array_map(fn ($m) => $this->classBasename($m), $middleware);

        return implode(', ', $names);
    }

    private function classBasename(string $class): string
    {
        return basename(str_replace('\\', '/', $class));
    }
}
