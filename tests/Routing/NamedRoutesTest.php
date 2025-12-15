<?php

declare(strict_types=1);

use Verge\App;
use Verge\Routing\Route;
use Verge\Routing\Router;
use Verge\Routing\RouteNotFoundException;
use Verge\Verge;

use function Verge\route;

describe('Named Routes', function () {

    describe('Route::name()', function () {
        it('sets the route name', function () {
            $router = new Router();
            $route = $router->get('/users', fn () => 'users');

            $result = $route->name('users.index');

            expect($result)->toBe($route);
            expect($route->getName())->toBe('users.index');
        });

        it('returns null when no name set', function () {
            $router = new Router();
            $route = $router->get('/users', fn () => 'users');

            expect($route->getName())->toBeNull();
        });

        it('is chainable with middleware', function () {
            $router = new Router();
            $route = $router->get('/users', fn () => 'users')
                ->name('users.index')
                ->use(fn ($req, $next) => $next($req));

            expect($route->getName())->toBe('users.index');
            expect($route->getMiddleware())->toHaveCount(1);
        });
    });

    describe('Router::registerNamedRoute()', function () {
        it('registers route by name', function () {
            $router = new Router();
            $route = $router->get('/users', fn () => 'users');
            $route->name('users.index');
            $router->registerNamedRoute('users.index', $route);

            expect($router->getNamedRoute('users.index'))->toBe($route);
        });

        it('throws on duplicate names', function () {
            $router = new Router();
            $route1 = $router->get('/users', fn () => 'users');
            $route2 = $router->get('/people', fn () => 'people');

            $router->registerNamedRoute('users.index', $route1);

            expect(fn () => $router->registerNamedRoute('users.index', $route2))
                ->toThrow(InvalidArgumentException::class, "Route name 'users.index' is already registered");
        });

        it('returns null for unregistered name', function () {
            $router = new Router();

            expect($router->getNamedRoute('nonexistent'))->toBeNull();
        });
    });

    describe('Router::url()', function () {
        it('generates URL for named route', function () {
            $router = new Router();
            $route = $router->get('/users', fn () => 'users');
            $route->name('users.index');
            $router->registerNamedRoute('users.index', $route);

            expect($router->url('users.index'))->toBe('/users');
        });

        it('substitutes single parameter', function () {
            $router = new Router();
            $route = $router->get('/users/{id}', fn ($id) => $id);
            $route->name('users.show');
            $router->registerNamedRoute('users.show', $route);

            expect($router->url('users.show', ['id' => 123]))->toBe('/users/123');
        });

        it('substitutes multiple parameters', function () {
            $router = new Router();
            $route = $router->get('/posts/{postId}/comments/{commentId}', fn () => '');
            $route->name('posts.comments.show');
            $router->registerNamedRoute('posts.comments.show', $route);

            $url = $router->url('posts.comments.show', ['postId' => 42, 'commentId' => 99]);

            expect($url)->toBe('/posts/42/comments/99');
        });

        it('throws for missing route', function () {
            $router = new Router();

            expect(fn () => $router->url('missing'))
                ->toThrow(RouteNotFoundException::class, "Route 'missing' not found");
        });

        it('adds extra parameters as query string', function () {
            $router = new Router();
            $route = $router->get('/users/{id}', fn ($id) => $id);
            $route->name('users.show');
            $router->registerNamedRoute('users.show', $route);

            $url = $router->url('users.show', ['id' => 123, 'tab' => 'posts', 'page' => 2]);

            expect($url)->toBe('/users/123?tab=posts&page=2');
        });

        it('handles only query parameters', function () {
            $router = new Router();
            $route = $router->get('/search', fn () => 'search');
            $route->name('search');
            $router->registerNamedRoute('search', $route);

            $url = $router->url('search', ['q' => 'hello', 'page' => 1]);

            expect($url)->toBe('/search?q=hello&page=1');
        });
    });

    describe('App integration', function () {
        beforeEach(fn () => Verge::reset());

        it('registers named route via name parameter', function () {
            $app = new App();
            $app->get('/users/{id}', fn ($id) => $id, name: 'users.show');

            expect($app->url('users.show', ['id' => 42]))->toBe('/users/42');
        });

        it('registers named routes in groups', function () {
            $app = new App();

            $app->group('/api', function ($app) {
                $app->get('/users/{id}', fn ($id) => $id, name: 'api.users.show');
            });

            expect($app->url('api.users.show', ['id' => 123]))->toBe('/api/users/123');
        });

        it('supports all HTTP methods with names', function () {
            $app = new App();
            $app->get('/users', fn () => 'list', name: 'users.index');
            $app->post('/users', fn () => 'create', name: 'users.store');
            $app->put('/users/{id}', fn () => 'update', name: 'users.update');
            $app->patch('/users/{id}', fn () => 'patch', name: 'users.patch');
            $app->delete('/users/{id}', fn () => 'delete', name: 'users.destroy');

            expect($app->url('users.index'))->toBe('/users');
            expect($app->url('users.store'))->toBe('/users');
            expect($app->url('users.update', ['id' => 1]))->toBe('/users/1');
            expect($app->url('users.patch', ['id' => 2]))->toBe('/users/2');
            expect($app->url('users.destroy', ['id' => 3]))->toBe('/users/3');
        });
    });

    describe('route() helper', function () {
        beforeEach(fn () => Verge::reset());

        it('generates URL through facade', function () {
            $app = Verge::create();
            $app->get('/users/{id}', fn ($id) => $id, name: 'users.show');

            expect(route('users.show', ['id' => 42]))->toBe('/users/42');
        });

        it('adds query parameters', function () {
            $app = Verge::create();
            $app->get('/users/{id}', fn ($id) => $id, name: 'users.show');

            expect(route('users.show', ['id' => 5, 'tab' => 'posts']))->toBe('/users/5?tab=posts');
        });

        it('throws when no app exists', function () {
            expect(fn () => route('users.show'))
                ->toThrow(RuntimeException::class);
        });
    });

});
