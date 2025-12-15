<?php

declare(strict_types=1);

namespace Verge\Benchmarks;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use Verge\App;
use Verge\Http\Request;

#[BeforeMethods('setUp')]
class AppBench
{
    private App $app;

    public function setUp(): void
    {
        $this->app = new App();

        // Simulate a real service
        $this->app->singleton(UserRepository::class, fn () => new UserRepository());

        // Global middleware
        $this->app->use(function ($request, $next) {
            $response = $next($request);
            return $response->withHeader('X-Powered-By', 'Verge');
        });

        // Static routes
        $this->app->get('/', fn () => ['status' => 'ok']);
        $this->app->get('/about', fn () => 'About page');
        $this->app->get('/health', fn () => ['healthy' => true]);

        // Dynamic routes
        $this->app->get('/users/{id}', fn ($id, UserRepository $repo) => $repo->find($id));
        $this->app->get('/posts/{slug}', fn ($slug) => ['slug' => $slug]);

        // Multi-parameter routes
        $this->app->get('/users/{userId}/posts/{postId}', fn ($userId, $postId) => [
            'user' => $userId,
            'post' => $postId,
        ]);

        // Route group with middleware
        $this->app->group('/api', function (App $app) {
            $app->get('/users', fn (UserRepository $repo) => $repo->all());
            $app->post('/users', fn () => ['created' => true]);
            $app->get('/users/{id}', fn ($id, UserRepository $repo) => $repo->find($id));
            $app->put('/users/{id}', fn ($id) => ['updated' => $id]);
            $app->delete('/users/{id}', fn ($id) => ['deleted' => $id]);
        })->use(function ($request, $next) {
            // Simulate auth check
            return $next($request);
        });
    }

    #[Revs(1000)]
    #[Iterations(5)]
    public function benchStaticRoute(): void
    {
        $this->app->handle(new Request('GET', '/about'));
    }

    #[Revs(1000)]
    #[Iterations(5)]
    public function benchDynamicRoute(): void
    {
        $this->app->handle(new Request('GET', '/users/123'));
    }

    #[Revs(1000)]
    #[Iterations(5)]
    public function benchMultiParamRoute(): void
    {
        $this->app->handle(new Request('GET', '/users/42/posts/99'));
    }

    #[Revs(1000)]
    #[Iterations(5)]
    public function benchGroupedRouteWithMiddleware(): void
    {
        $this->app->handle(new Request('GET', '/api/users/123'));
    }

    #[Revs(1000)]
    #[Iterations(5)]
    public function benchContainerResolution(): void
    {
        $this->app->handle(new Request('GET', '/api/users'));
    }
}

// Simple test doubles for benchmarking
class UserRepository
{
    public function find(string $id): array
    {
        return ['id' => $id, 'name' => 'User ' . $id];
    }

    public function all(): array
    {
        return [
            ['id' => '1', 'name' => 'User 1'],
            ['id' => '2', 'name' => 'User 2'],
        ];
    }
}
