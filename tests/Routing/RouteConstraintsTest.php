<?php

declare(strict_types=1);

use Verge\App;
use Verge\Http\Request;
use Verge\Routing\Router;

describe('Optional Route Parameters', function () {

    describe('compilePath with optional params', function () {
        it('compiles optional parameter at end of path', function () {
            $router = new Router();
            $route = $router->get('/users/{id?}', fn ($id = null) => $id);

            expect($route->matches('/users'))->toBe([]);
            expect($route->matches('/users/123'))->toBe(['id' => '123']);
        });

        it('compiles multiple optional parameters', function () {
            $router = new Router();
            $route = $router->get('/archive/{year?}/{month?}', fn ($year = null, $month = null) => '');

            expect($route->matches('/archive'))->toBe([]);
            expect($route->matches('/archive/2024'))->toBe(['year' => '2024']);
            expect($route->matches('/archive/2024/01'))->toBe(['year' => '2024', 'month' => '01']);
        });

        it('does not match when required part missing', function () {
            $router = new Router();
            $route = $router->get('/users/{id}', fn ($id) => $id);

            expect($route->matches('/users'))->toBeNull();
            expect($route->matches('/users/'))->toBeNull();
        });
    });

    describe('integration with App', function () {
        it('handles optional parameters in handlers', function () {
            $app = new App();
            $app->get('/users/{id?}', fn ($id = null) => $id ?? 'all');

            expect($app->test()->get('/users')->body())->toBe('all');
            expect($app->test()->get('/users/123')->body())->toBe('123');
        });

        it('handles multiple optional parameters', function () {
            $app = new App();
            $app->get('/archive/{year?}/{month?}', fn ($year = null, $month = null) => json_encode(['year' => $year, 'month' => $month]));

            $response1 = $app->test()->get('/archive');
            expect($response1->json())->toBe(['year' => null, 'month' => null]);

            $response2 = $app->test()->get('/archive/2024');
            expect($response2->json())->toBe(['year' => '2024', 'month' => null]);

            $response3 = $app->test()->get('/archive/2024/01');
            expect($response3->json())->toBe(['year' => '2024', 'month' => '01']);
        });
    });

});

describe('Route Constraints', function () {

    describe('inline constraints', function () {
        it('compiles numeric constraint', function () {
            $router = new Router();
            $route = $router->get('/users/{id:\d+}', fn ($id) => $id);

            expect($route->matches('/users/123'))->toBe(['id' => '123']);
            expect($route->matches('/users/abc'))->toBeNull();
        });

        it('compiles custom regex constraint', function () {
            $router = new Router();
            $route = $router->get('/posts/{slug:[a-z0-9-]+}', fn ($slug) => $slug);

            expect($route->matches('/posts/hello-world'))->toBe(['slug' => 'hello-world']);
            expect($route->matches('/posts/hello123'))->toBe(['slug' => 'hello123']);
            expect($route->matches('/posts/Hello_World'))->toBeNull();
        });

        it('compiles multiple constraints', function () {
            $router = new Router();
            $route = $router->get('/posts/{year:\d{4}}/{slug:[a-z-]+}', fn ($year, $slug) => '');

            expect($route->matches('/posts/2024/hello'))->toBe(['year' => '2024', 'slug' => 'hello']);
            expect($route->matches('/posts/24/hello'))->toBeNull();
        });

        it('compiles uuid constraint', function () {
            $router = new Router();
            $route = $router->get('/resources/{uuid:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}}', fn ($uuid) => $uuid);

            $validUuid = '550e8400-e29b-41d4-a716-446655440000';
            expect($route->matches("/resources/{$validUuid}"))->toBe(['uuid' => $validUuid]);
            expect($route->matches('/resources/not-a-uuid'))->toBeNull();
        });

        it('compiles alpha constraint', function () {
            $router = new Router();
            $route = $router->get('/users/{name:[a-zA-Z]+}', fn ($name) => $name);

            expect($route->matches('/users/john'))->toBe(['name' => 'john']);
            expect($route->matches('/users/John'))->toBe(['name' => 'John']);
            expect($route->matches('/users/john123'))->toBeNull();
        });
    });

    describe('optional + constraint combined', function () {
        it('supports optional params with inline constraints', function () {
            $router = new Router();
            // Note: {year?:\d{4}} - optional marker BEFORE the constraint
            $route = $router->get('/archive/{year?:\d{4}}', fn ($year = null) => $year ?? 'all');

            expect($route->matches('/archive'))->toBe([]);
            expect($route->matches('/archive/2024'))->toBe(['year' => '2024']);
            expect($route->matches('/archive/24'))->toBeNull();
        });

        it('supports multiple optional constrained params', function () {
            $router = new Router();
            $route = $router->get('/archive/{year?:\d{4}}/{month?:\d{2}}', fn ($year = null, $month = null) => '');

            expect($route->matches('/archive'))->toBe([]);
            expect($route->matches('/archive/2024'))->toBe(['year' => '2024']);
            expect($route->matches('/archive/2024/01'))->toBe(['year' => '2024', 'month' => '01']);
            expect($route->matches('/archive/2024/1'))->toBeNull(); // month must be 2 digits
        });
    });

    describe('integration with App', function () {
        it('validates parameters against constraints', function () {
            $app = new App();
            $app->get('/users/{id:\d+}', fn ($id) => "User $id");

            expect($app->test()->get('/users/123')->body())->toBe('User 123');
            expect($app->test()->get('/users/abc')->status())->toBe(404);
        });

        it('combines constraints with named routes', function () {
            $app = new App();
            $app->get('/users/{id:\d+}', fn ($id) => "User $id", name: 'users.show');

            expect($app->url('users.show', ['id' => 42]))->toBe('/users/42');
        });
    });

});

describe('URL Generation with Constraints', function () {

    it('generates URL stripping constraint syntax', function () {
        $router = new Router();
        $route = $router->get('/users/{id:\d+}', fn ($id) => $id);
        $route->name('users.show');
        $router->registerNamedRoute('users.show', $route);

        expect($router->url('users.show', ['id' => 123]))->toBe('/users/123');
    });

    it('generates URL with optional params provided', function () {
        $router = new Router();
        $route = $router->get('/archive/{year?:\d{4}}', fn ($year = null) => $year);
        $route->name('archive');
        $router->registerNamedRoute('archive', $route);

        expect($router->url('archive', ['year' => 2024]))->toBe('/archive/2024');
    });

    it('generates URL with optional params omitted', function () {
        $router = new Router();
        $route = $router->get('/archive/{year?:\d{4}}', fn ($year = null) => $year);
        $route->name('archive');
        $router->registerNamedRoute('archive', $route);

        expect($router->url('archive'))->toBe('/archive');
    });

    it('generates URL with mixed required and optional', function () {
        $router = new Router();
        $route = $router->get('/users/{id:\d+}/posts/{postId?}', fn ($id, $postId = null) => '');
        $route->name('users.posts');
        $router->registerNamedRoute('users.posts', $route);

        expect($router->url('users.posts', ['id' => 1]))->toBe('/users/1/posts');
        expect($router->url('users.posts', ['id' => 1, 'postId' => 5]))->toBe('/users/1/posts/5');
    });

});
