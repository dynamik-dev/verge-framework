<?php

declare(strict_types=1);

use Verge\App;
use Verge\Routing\Explorer\RouteExplorer;
use Verge\Routing\Explorer\RouteInfo;

// Test controller for handler extraction
class TestRoutesController
{
    public function index(): string
    {
        return 'index';
    }

    public function show(string $id): string
    {
        return $id;
    }
}

// Test invokable for handler extraction
class TestInvokableHandler
{
    public function __invoke(): string
    {
        return 'invoked';
    }
}

// Test middleware
class TestRoutesMiddleware
{
    public function __invoke($request, $next)
    {
        return $next($request);
    }
}

describe('RouteExplorer', function () {

    describe('all()', function () {
        it('extracts all routes', function () {
            $app = new App();
            $app->get('/users', fn () => 'list');
            $app->post('/users', fn () => 'create');
            $app->get('/posts', fn () => 'posts');

            $routes = $app->routes()->all();

            expect($routes)->toHaveCount(3);
            expect($routes[0])->toBeInstanceOf(RouteInfo::class);
        });

        it('returns empty array when no routes', function () {
            $app = new App();

            expect($app->routes()->all())->toBe([]);
        });
    });

    describe('method()', function () {
        it('filters routes by HTTP method', function () {
            $app = new App();
            $app->get('/a', fn () => 'a');
            $app->get('/b', fn () => 'b');
            $app->post('/c', fn () => 'c');
            $app->put('/d', fn () => 'd');

            expect($app->routes()->method('GET'))->toHaveCount(2);
            expect($app->routes()->method('POST'))->toHaveCount(1);
            expect($app->routes()->method('PUT'))->toHaveCount(1);
            expect($app->routes()->method('DELETE'))->toHaveCount(0);
        });

        it('is case insensitive', function () {
            $app = new App();
            $app->get('/test', fn () => 'test');

            expect($app->routes()->method('get'))->toHaveCount(1);
            expect($app->routes()->method('Get'))->toHaveCount(1);
        });
    });

    describe('named()', function () {
        it('filters only named routes', function () {
            $app = new App();
            $app->get('/a', fn () => 'a', name: 'route.a');
            $app->get('/b', fn () => 'b');
            $app->get('/c', fn () => 'c', name: 'route.c');

            $named = $app->routes()->named();

            expect($named)->toHaveCount(2);
            expect($named[0]->name)->toBe('route.a');
            expect($named[1]->name)->toBe('route.c');
        });

        it('returns empty array when no named routes', function () {
            $app = new App();
            $app->get('/a', fn () => 'a');
            $app->get('/b', fn () => 'b');

            expect($app->routes()->named())->toBe([]);
        });
    });

    describe('prefix()', function () {
        it('filters routes by path prefix', function () {
            $app = new App();
            $app->get('/api/users', fn () => 'users');
            $app->get('/api/posts', fn () => 'posts');
            $app->get('/web/home', fn () => 'home');

            $apiRoutes = $app->routes()->prefix('/api');

            expect($apiRoutes)->toHaveCount(2);
            expect($apiRoutes[0]->path)->toBe('/api/users');
            expect($apiRoutes[1]->path)->toBe('/api/posts');
        });
    });

    describe('count()', function () {
        it('returns route count', function () {
            $app = new App();
            $app->get('/a', fn () => 'a');
            $app->get('/b', fn () => 'b');
            $app->post('/c', fn () => 'c');

            expect($app->routes()->count())->toBe(3);
        });
    });

    describe('toArray()', function () {
        it('serializes routes to array format', function () {
            $app = new App();
            $app->get('/users/{id}', fn ($id) => $id, name: 'users.show');

            $arr = $app->routes()->toArray();

            expect($arr)->toHaveCount(1);
            expect($arr[0]['method'])->toBe('GET');
            expect($arr[0]['path'])->toBe('/users/{id}');
            expect($arr[0]['name'])->toBe('users.show');
            expect($arr[0]['params'])->toBeArray();
            expect($arr[0]['middleware'])->toBeArray();
            expect($arr[0]['handler'])->toBeArray();
        });

        it('is JSON serializable', function () {
            $app = new App();
            $app->get('/test', fn () => 'test');

            $json = json_encode($app->routes()->toArray());

            expect($json)->toBeString();
            expect(json_decode($json, true))->toBeArray();
        });
    });

});

describe('RouteInfo', function () {

    describe('parameter extraction', function () {
        it('extracts required parameters', function () {
            $app = new App();
            $app->get('/users/{id}', fn ($id) => $id);

            $route = $app->routes()->all()[0];

            expect($route->params)->toHaveCount(1);
            expect($route->params[0]->name)->toBe('id');
            expect($route->params[0]->required)->toBeTrue();
            expect($route->params[0]->constraint)->toBeNull();
        });

        it('extracts optional parameters', function () {
            $app = new App();
            $app->get('/archive/{year?}', fn ($year = null) => $year);

            $route = $app->routes()->all()[0];

            expect($route->params[0]->name)->toBe('year');
            expect($route->params[0]->required)->toBeFalse();
        });

        it('extracts parameter constraints', function () {
            $app = new App();
            $app->get('/users/{id:\d+}', fn ($id) => $id);

            $route = $app->routes()->all()[0];

            expect($route->params[0]->constraint)->toBe('\d+');
        });

        it('extracts constraints with nested braces', function () {
            $app = new App();
            $app->get('/archive/{year:\d{4}}', fn ($year) => $year);

            $route = $app->routes()->all()[0];

            expect($route->params[0]->name)->toBe('year');
            expect($route->params[0]->constraint)->toBe('\d{4}');
        });

        it('extracts optional parameters with constraints', function () {
            $app = new App();
            $app->get('/archive/{year?:\d{4}}', fn ($year = null) => $year);

            $route = $app->routes()->all()[0];

            expect($route->params[0]->name)->toBe('year');
            expect($route->params[0]->required)->toBeFalse();
            expect($route->params[0]->constraint)->toBe('\d{4}');
        });

        it('extracts multiple parameters', function () {
            $app = new App();
            $app->get('/posts/{postId}/comments/{commentId}', fn ($postId, $commentId) => '');

            $route = $app->routes()->all()[0];

            expect($route->params)->toHaveCount(2);
            expect($route->params[0]->name)->toBe('postId');
            expect($route->params[1]->name)->toBe('commentId');
        });
    });

    describe('handler extraction', function () {
        it('identifies closure handlers', function () {
            $app = new App();
            $app->get('/test', fn () => 'test');

            $route = $app->routes()->all()[0];

            expect($route->handler['type'])->toBe('closure');
        });

        it('identifies controller handlers', function () {
            $app = new App();
            $app->get('/users', [TestRoutesController::class, 'index']);

            $route = $app->routes()->all()[0];
            $handler = $route->handler;

            expect($handler['type'])->toBe('controller');
            if ($handler['type'] === 'controller') {
                /** @var array{type: 'controller', class: string, method: string} $handler */
                expect($handler['class'])->toBe(TestRoutesController::class);
                expect($handler['method'])->toBe('index');
            }
        });

        it('identifies invokable handlers', function () {
            $app = new App();
            $app->get('/invoke', TestInvokableHandler::class);

            $route = $app->routes()->all()[0];
            $handler = $route->handler;

            expect($handler['type'])->toBe('invokable');
            if ($handler['type'] === 'invokable') {
                /** @var array{type: 'invokable', class: string} $handler */
                expect($handler['class'])->toBe(TestInvokableHandler::class);
            }
        });
    });

    describe('middleware extraction', function () {
        it('extracts middleware class names', function () {
            $app = new App();
            $app->get('/test', fn () => 'test', middleware: [TestRoutesMiddleware::class]);

            $route = $app->routes()->all()[0];

            expect($route->middleware)->toContain(TestRoutesMiddleware::class);
        });

        it('extracts multiple middleware', function () {
            $app = new App();
            $app->get('/test', fn () => 'test', middleware: [
                TestRoutesMiddleware::class,
                TestRoutesMiddleware::class,
            ]);

            $route = $app->routes()->all()[0];

            expect($route->middleware)->toHaveCount(2);
        });

        it('handles callable middleware', function () {
            $app = new App();
            $app->get('/test', fn () => 'test', middleware: [
                fn ($req, $next) => $next($req),
            ]);

            $route = $app->routes()->all()[0];

            expect($route->middleware[0])->toBe('Closure');
        });
    });

    describe('toArray()', function () {
        it('converts route info to array', function () {
            $app = new App();
            $app->get('/users/{id:\d+}', [TestRoutesController::class, 'show'], name: 'users.show');

            $route = $app->routes()->all()[0];
            $arr = $route->toArray();

            expect($arr)->toBe([
                'method' => 'GET',
                'path' => '/users/{id:\d+}',
                'name' => 'users.show',
                'params' => [
                    ['name' => 'id', 'required' => true, 'constraint' => '\d+'],
                ],
                'middleware' => [],
                'handler' => [
                    'type' => 'controller',
                    'class' => TestRoutesController::class,
                    'method' => 'show',
                ],
            ]);
        });
    });

});

describe('App::routes() integration', function () {

    it('returns RouteExplorer instance when called with no arguments', function () {
        $app = new App();
        $app->get('/test', fn () => 'test');

        expect($app->routes())->toBeInstanceOf(RouteExplorer::class);
    });

    it('works with route groups', function () {
        $app = new App();
        $app->group('/api', function ($app) {
            $app->get('/users', fn () => 'users');
            $app->get('/posts', fn () => 'posts');
        });

        $routes = $app->routes()->all();

        expect($routes)->toHaveCount(2);
        expect($routes[0]->path)->toBe('/api/users');
        expect($routes[1]->path)->toBe('/api/posts');
    });

    it('captures named routes in groups', function () {
        $app = new App();
        $app->group('/api', function ($app) {
            $app->get('/users', fn () => 'users', name: 'api.users');
        });

        $named = $app->routes()->named();

        expect($named)->toHaveCount(1);
        expect($named[0]->name)->toBe('api.users');
        expect($named[0]->path)->toBe('/api/users');
    });

});
