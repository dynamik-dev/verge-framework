<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Verge\App;
use Verge\Container;
use Verge\Env\Env;
use Verge\Http\Request;
use Verge\Http\Response;
use Verge\Routing\Route;
use Verge\Routing\Router;
use Verge\Routing\RouterInterface;
use Verge\Routing\Explorer\RouteExplorer;
use Verge\Testing\TestClient;

// Test fixtures
class TestService
{
    public function greet(): string
    {
        return 'Hello from service';
    }
}

class TestController
{
    public function __construct(public TestService $service)
    {
    }

    public function index(): array
    {
        return ['message' => $this->service->greet()];
    }

    public function show(string $id): array
    {
        return ['id' => $id];
    }

    public function store(Request $request): array
    {
        return ['created' => true, 'data' => $request->json()];
    }

    public function update(string $id, Request $request): array
    {
        return ['updated' => $id, 'data' => $request->json()];
    }

    public function destroy(string $id): void
    {
        // Returns null/void -> 204
    }
}

class InvokableController
{
    public function __invoke(): string
    {
        return 'invoked';
    }
}

class TestMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $response = $next($request);
        return $response->header('X-Middleware', 'applied');
    }
}

class TestModule
{
    public function __invoke(App $app): void
    {
        $app->bind('provided-value', fn () => 'from-module');
        $app->singleton(TestService::class, fn () => new TestService());
    }
}

class UsersModule
{
    public function __invoke(App $app): void
    {
        $app->bind('users.repository', fn () => 'user-repository');
        $app->get('/users', fn () => ['users' => []]);
    }
}

class ModuleWithDependency
{
    public function __construct(public Env $env)
    {
    }

    public function __invoke(App $app): void
    {
        $app->instance('env-value', $this->env->get('TEST_MODULE_VAR', 'default'));
    }
}


class ParameterizedMiddleware
{
    public function __construct(public int $limit)
    {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $response = $next($request);
        return $response->header('X-Rate-Limit', (string) $this->limit);
    }
}

describe('App', function () {

    describe('constructor', function () {
        it('includes defaults when no container provided', function () {
            $app = new App();

            expect($app->container())->toBeInstanceOf(ContainerInterface::class);
            expect($app->has(RouterInterface::class))->toBeTrue();
            expect($app->has(Env::class))->toBeTrue();
        });

        it('accepts custom container and bootstraps it', function () {
            $container = new Container();
            $app = new App($container);

            expect($app->container())->toBe($container);
            // App always bootstraps with core services via AppBuilder
            expect($app->has(RouterInterface::class))->toBeTrue();
        });

        it('allows chaining bindings after construction', function () {
            $app = (new App())
                ->singleton('db', fn () => 'database');

            expect($app->make('db'))->toBe('database');
            expect($app->has(RouterInterface::class))->toBeTrue();
        });
    });

    describe('container methods', function () {
        describe('bind()', function () {
            it('binds value to container', function () {
                $app = new App();

                $app->bind('greeting', fn () => 'Hello');

                expect($app->make('greeting'))->toBe('Hello');
            });
        });

        describe('singleton()', function () {
            it('binds singleton to container', function () {
                $app = new App();
                $callCount = 0;

                $app->singleton('counter', function () use (&$callCount) {
                    return ++$callCount;
                });

                expect($app->make('counter'))->toBe(1);
                expect($app->make('counter'))->toBe(1);
            });
        });

        describe('make()', function () {
            it('resolves from container', function () {
                $app = new App();

                $service = $app->make(TestService::class);

                expect($service)->toBeInstanceOf(TestService::class);
            });

            it('auto-wires dependencies', function () {
                $app = new App();

                $controller = $app->make(TestController::class);

                expect($controller->service)->toBeInstanceOf(TestService::class);
            });
        });

        describe('instance()', function () {
            it('stores instance in container', function () {
                $app = new App();
                $obj = new TestService();

                $app->instance('service', $obj);

                expect($app->make('service'))->toBe($obj);
            });
        });

        describe('for()', function () {
            it('creates contextual binding', function () {
                $app = new App();

                $app->bind(TestService::class, fn () => new class () extends TestService {
                    public function greet(): string
                    {
                        return 'Hello from contextual';
                    }
                })->for(TestController::class);

                // Default binding
                $app->bind(TestService::class, fn () => new TestService());

                $controller = $app->make(TestController::class);

                expect($controller->service->greet())->toBe('Hello from contextual');
            });
        });

        describe('has()', function () {
            it('checks container for binding', function () {
                $app = new App();

                $app->bind('test', fn () => 'value');

                expect($app->has('test'))->toBeTrue();
                expect($app->has('missing'))->toBeFalse();
            });
        });

        describe('container()', function () {
            it('returns PSR-11 container', function () {
                $app = new App();

                expect($app->container())->toBeInstanceOf(ContainerInterface::class);
            });
        });
    });

    describe('middleware', function () {
        describe('use()', function () {
            it('is chainable', function () {
                $app = new App();

                $result = $app->use(fn ($r, $n) => $n($r));

                expect($result)->toBe($app);
            });
        });
    });

    describe('configuration', function () {
        describe('env()', function () {
            it('gets environment variable', function () {
                $_ENV['TEST_APP_VAR'] = 'test_value';
                $app = new App();

                expect($app->env('TEST_APP_VAR'))->toBe('test_value');

                unset($_ENV['TEST_APP_VAR']);
            });

            it('returns default for missing var', function () {
                $app = new App();

                expect($app->env('MISSING_VAR', 'default'))->toBe('default');
            });
        });

        describe('configure()', function () {
            it('calls callback with app', function () {
                $app = new App();
                $called = false;

                $app->configure(function ($a) use (&$called, $app) {
                    $called = true;
                    expect($a)->toBe($app);
                });

                expect($called)->toBeTrue();
            });

            it('is chainable', function () {
                $app = new App();

                $result = $app->configure(fn ($app) => null);

                expect($result)->toBe($app);
            });

            it('accepts module class string', function () {
                $app = new App();

                $app->configure(TestModule::class);

                expect($app->make('provided-value'))->toBe('from-module');
            });

            it('resolves module with dependencies', function () {
                $_ENV['TEST_MODULE_VAR'] = 'injected-value';
                $app = new App();

                $app->configure(ModuleWithDependency::class);

                expect($app->make('env-value'))->toBe('injected-value');

                unset($_ENV['TEST_MODULE_VAR']);
            });

            it('chains multiple modules', function () {
                $app = new App();

                $app->configure(TestModule::class)
                    ->configure(fn ($app) => $app->bind('another', fn () => 'value'));

                expect($app->make('provided-value'))->toBe('from-module');
                expect($app->make('another'))->toBe('value');
            });

            it('accepts array of modules', function () {
                $app = new App();

                $app->configure([
                    TestModule::class,
                    fn ($app) => $app->bind('second', fn () => 'second-value'),
                    fn ($app) => $app->bind('third', fn () => 'third-value'),
                ]);

                expect($app->make('provided-value'))->toBe('from-module');
                expect($app->make('second'))->toBe('second-value');
                expect($app->make('third'))->toBe('third-value');
            });
        });

        describe('module()', function () {
            it('registers a module class', function () {
                $app = new App();

                $app->module(UsersModule::class);

                expect($app->make('users.repository'))->toBe('user-repository');
            });

            it('is chainable', function () {
                $app = new App();

                $result = $app->module(UsersModule::class);

                expect($result)->toBe($app);
            });

            it('registers routes from module', function () {
                $app = new App();

                $app->module(UsersModule::class);

                $response = $app->test()->get('/users');
                expect($response->status())->toBe(200);
                expect($response->json())->toBe(['users' => []]);
            });
        });

        describe('ready()', function () {
            it('registers callback for app.ready event', function () {
                $app = new App();
                $called = false;

                $app->ready(function () use (&$called) {
                    $called = true;
                });

                // Trigger boot
                $app->test()->get('/');

                expect($called)->toBeTrue();
            });

            it('is chainable', function () {
                $app = new App();

                $result = $app->ready(fn () => null);

                expect($result)->toBe($app);
            });

            it('runs after all modules are loaded', function () {
                $app = new App();
                $order = [];

                $app->configure(function ($app) use (&$order) {
                    $order[] = 'configure';
                });

                $app->ready(function () use (&$order) {
                    $order[] = 'ready';
                });

                $app->configure(function ($app) use (&$order) {
                    $order[] = 'configure2';
                });

                // Trigger boot
                $app->test()->get('/');

                expect($order)->toBe(['configure', 'configure2', 'ready']);
            });
        });

        describe('routes()', function () {
            it('returns RouteExplorer for introspection', function () {
                $app = new App();
                $app->get('/test', fn () => 'ok');

                $routes = $app->routes();

                expect($routes)->toBeInstanceOf(RouteExplorer::class);
                expect($routes->count())->toBe(1);
            });

            it('accepts a Router instance', function () {
                $app = new App();
                $router = new Router();
                $router->get('/from-router', fn () => 'router-response');

                $app->routes($router);

                expect($app->test()->get('/from-router')->body())->toBe('router-response');
            });

            it('is chainable when given router', function () {
                $app = new App();
                $router = new Router();

                $result = $app->routes($router);

                expect($result)->toBe($app);
            });
        });
    });

    describe('handle()', function () {
        it('returns response for matched route', function () {
            $app = new App();
            $app->get('/', fn () => 'home');

            $request = new Request('GET', '/');
            $response = $app->handle($request);

            expect($response)->toBeInstanceOf(Response::class);
            expect($response->body())->toBe('home');
        });

        it('returns 404 for unmatched route', function () {
            $app = new App();

            $request = new Request('GET', '/missing');
            $response = $app->handle($request);

            expect($response->status())->toBe(404);
            expect($response->json())->toBe(['error' => 'Not Found']);
        });

        it('makes request available in container', function () {
            $app = new App();
            $capturedRequest = null;

            $app->get('/test', function () use ($app, &$capturedRequest) {
                $capturedRequest = $app->make(Request::class);
                return 'ok';
            });

            $request = new Request('GET', '/test');
            $app->handle($request);

            expect($capturedRequest)->toBe($request);
        });
    });

    describe('response preparation', function () {
        it('returns Response as-is', function () {
            $app = new App();
            $app->get('/', fn () => new Response('custom', 201));

            expect($app->test()->get('/')->status())->toBe(201);
        });

        it('converts null to 204', function () {
            $app = new App();
            $app->get('/', fn () => null);

            $response = $app->test()->get('/');

            expect($response->status())->toBe(204);
            expect($response->body())->toBe('');
        });

        it('converts array to JSON', function () {
            $app = new App();
            $app->get('/', fn () => ['key' => 'value']);

            $response = $app->test()->get('/');

            expect($response->json())->toBe(['key' => 'value']);
            expect($response->getHeader('content-type'))->toBe(['application/json']);
        });

        it('converts string to text/plain', function () {
            $app = new App();
            $app->get('/', fn () => 'Hello');

            $response = $app->test()->get('/');

            expect($response->body())->toBe('Hello');
            expect($response->getHeader('content-type'))->toBe(['text/plain']);
        });

        it('converts stringable object to text', function () {
            $app = new App();
            $app->get('/', fn () => new class () {
                public function __toString(): string
                {
                    return 'stringable';
                }
            });

            $response = $app->test()->get('/');

            expect($response->body())->toBe('stringable');
        });
    });

    describe('handler types', function () {
        it('handles closure', function () {
            $app = new App();
            $app->get('/', fn () => 'closure');

            expect($app->test()->get('/')->body())->toBe('closure');
        });

        it('handles array [Controller, method]', function () {
            $app = new App();
            $app->get('/', [TestController::class, 'index']);

            expect($app->test()->get('/')->json()['message'])->toBe('Hello from service');
        });

        it('handles invokable class string', function () {
            $app = new App();
            $app->get('/', InvokableController::class);

            expect($app->test()->get('/')->body())->toBe('invoked');
        });

        it('injects dependencies into handler', function () {
            $app = new App();
            $app->get('/', fn (TestService $service) => $service->greet());

            expect($app->test()->get('/')->body())->toBe('Hello from service');
        });

        it('injects request into handler', function () {
            $app = new App();
            $app->get('/', fn (Request $req) => $req->method());

            expect($app->test()->get('/')->body())->toBe('GET');
        });
    });

    describe('route parameters', function () {
        it('extracts single parameter', function () {
            $app = new App();
            $app->get('/users/{id}', fn ($id) => "User: $id");

            $response = $app->test()->get('/users/123');

            expect($response->body())->toBe('User: 123');
        });

        it('extracts multiple parameters', function () {
            $app = new App();
            $app->get(
                '/posts/{postId}/comments/{commentId}',
                fn ($postId, $commentId) => "Post $postId, Comment $commentId"
            );

            $response = $app->test()->get('/posts/42/comments/99');

            expect($response->body())->toBe('Post 42, Comment 99');
        });
    });

    describe('route middleware', function () {
        it('applies middleware to specific route', function () {
            $app = new App();
            $app->get('/protected', fn () => 'secret')
                ->use(fn ($req, $next) => $next($req)->header('X-Protected', 'yes'));
            $app->get('/public', fn () => 'open');

            expect($app->test()->get('/protected')->getHeader('x-protected'))->toBe(['yes']);
            expect($app->test()->get('/public')->getHeader('x-protected'))->toBe([]);
        });

        it('executes middleware in order (onion)', function () {
            $app = new App();
            $order = [];

            $app->get('/test', fn () => 'result')
                ->use(function ($req, $next) use (&$order) {
                    $order[] = 'A-before';
                    $response = $next($req);
                    $order[] = 'A-after';
                    return $response;
                })
                ->use(function ($req, $next) use (&$order) {
                    $order[] = 'B-before';
                    $response = $next($req);
                    $order[] = 'B-after';
                    return $response;
                });

            $app->test()->get('/test');

            expect($order)->toBe(['A-before', 'B-before', 'B-after', 'A-after']);
        });

        it('resolves middleware from container', function () {
            $app = new App();
            $app->get('/test', fn () => 'ok')->use(TestMiddleware::class);

            $response = $app->test()->get('/test');

            expect($response->getHeader('x-middleware'))->toBe(['applied']);
        });
    });

    describe('test()', function () {
        it('returns TestClient', function () {
            $app = new App();

            expect($app->test())->toBeInstanceOf(TestClient::class);
        });
    });

    describe('group()', function () {
        it('prefixes routes in group', function () {
            $app = new App();

            $app->group('/api', function ($app) {
                $app->get('/users', fn () => 'users list');
                $app->get('/posts', fn () => 'posts list');
            });

            expect($app->test()->get('/api/users')->body())->toBe('users list');
            expect($app->test()->get('/api/posts')->body())->toBe('posts list');
            expect($app->test()->get('/users')->status())->toBe(404);
        });

        it('supports nested groups', function () {
            $app = new App();

            $app->group('/api', function ($app) {
                $app->group('/v1', function ($app) {
                    $app->get('/users', fn () => 'v1 users');
                });
                $app->group('/v2', function ($app) {
                    $app->get('/users', fn () => 'v2 users');
                });
            });

            expect($app->test()->get('/api/v1/users')->body())->toBe('v1 users');
            expect($app->test()->get('/api/v2/users')->body())->toBe('v2 users');
        });

        it('applies middleware to group routes', function () {
            $app = new App();

            $app->group('/api', function ($app) {
                $app->get('/test', fn () => 'test');
            })->use(fn ($req, $next) => $next($req)->header('X-Group', 'yes'));

            $response = $app->test()->get('/api/test');

            expect($response->body())->toBe('test');
            expect($response->getHeader('x-group'))->toBe(['yes']);
        });

        it('applies multiple middleware to group', function () {
            $app = new App();

            $app->group('/api', function ($app) {
                $app->get('/test', fn () => 'ok');
            })->use(fn ($req, $next) => $next($req)->header('X-First', '1'))
              ->use(fn ($req, $next) => $next($req)->header('X-Second', '2'));

            $response = $app->test()->get('/api/test');

            expect($response->getHeader('x-first'))->toBe(['1']);
            expect($response->getHeader('x-second'))->toBe(['2']);
        });

        it('allows middleware instances with parameters', function () {
            $app = new App();

            $app->group('/api', function ($app) {
                $app->get('/test', fn () => 'ok');
            })->use(new ParameterizedMiddleware(limit: 100));

            $response = $app->test()->get('/api/test');

            expect($response->getHeader('x-rate-limit'))->toBe(['100']);
        });

        it('restores context after exception', function () {
            $app = new App();

            try {
                $app->group('/api', function ($app) {
                    $app->get('/before', fn () => 'before');
                    throw new \RuntimeException('Test exception');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }

            // Routes outside should not have the prefix
            $app->get('/outside', fn () => 'outside');

            expect($app->test()->get('/outside')->body())->toBe('outside');
            expect($app->test()->get('/api/before')->body())->toBe('before');
        });
    });

    describe('route() sub-app mounting', function () {
        it('mounts sub-app routes at prefix', function () {
            $api = new App();
            $api->get('/users', fn () => ['users' => []]);
            $api->get('/users/{id}', fn ($id) => ['id' => $id]);

            $app = new App();
            $app->route('/api', $api);

            expect($app->test()->get('/api/users')->json())->toBe(['users' => []]);
            expect($app->test()->get('/api/users/123')->json())->toBe(['id' => '123']);
            expect($app->test()->get('/users')->status())->toBe(404);
        });

        it('applies sub-app global middleware', function () {
            $api = new App();
            $api->use(fn ($req, $next) => $next($req)->header('X-Sub-App', 'yes'));
            $api->get('/test', fn () => 'ok');

            $app = new App();
            $app->route('/api', $api);

            $response = $app->test()->get('/api/test');

            expect($response->body())->toBe('ok');
            expect($response->getHeader('x-sub-app'))->toBe(['yes']);
        });

        it('applies sub-app route-level middleware', function () {
            $api = new App();
            $api->get('/test', fn () => 'ok')
                ->use(fn ($req, $next) => $next($req)->header('X-Route-Middleware', 'applied'));

            $app = new App();
            $app->route('/api', $api);

            $response = $app->test()->get('/api/test');

            expect($response->getHeader('x-route-middleware'))->toBe(['applied']);
        });

        it('applies parent app global middleware to mounted routes', function () {
            $api = new App();
            $api->get('/test', fn () => 'ok');

            $app = new App();
            $app->use(fn ($req, $next) => $next($req)->header('X-Parent', 'yes'));
            $app->route('/api', $api);

            $response = $app->test()->get('/api/test');

            expect($response->getHeader('x-parent'))->toBe(['yes']);
        });

        it('chains multiple sub-apps', function () {
            $api = new App();
            $api->get('/users', fn () => 'api users');

            $admin = new App();
            $admin->get('/dashboard', fn () => 'admin dashboard');

            $app = new App();
            $app->route('/api', $api)
                ->route('/admin', $admin);

            expect($app->test()->get('/api/users')->body())->toBe('api users');
            expect($app->test()->get('/admin/dashboard')->body())->toBe('admin dashboard');
        });

        it('supports nested sub-apps', function () {
            $v1 = new App();
            $v1->get('/users', fn () => 'v1 users');

            $api = new App();
            $api->route('/v1', $v1);

            $app = new App();
            $app->route('/api', $api);

            expect($app->test()->get('/api/v1/users')->body())->toBe('v1 users');
        });

        it('mounts sub-app at root prefix', function () {
            $web = new App();
            $web->get('/', fn () => 'home');
            $web->get('/about', fn () => 'about');

            $app = new App();
            $app->route('', $web);

            expect($app->test()->get('/')->body())->toBe('home');
            expect($app->test()->get('/about')->body())->toBe('about');
        });

        it('combines sub-app and inline routes', function () {
            $api = new App();
            $api->get('/users', fn () => 'users');

            $app = new App();
            $app->get('/health', fn () => 'ok');
            $app->route('/api', $api);

            expect($app->test()->get('/health')->body())->toBe('ok');
            expect($app->test()->get('/api/users')->body())->toBe('users');
        });
    });

    describe('worker mode compatibility', function () {
        it('handles multiple requests without state leakage', function () {
            $app = new App();

            $app->group('/api', function ($app) {
                $app->get('/users', fn () => 'users');
            });

            $app->get('/web', fn () => 'web');

            // Simulate multiple requests (as in worker mode)
            for ($i = 0; $i < 3; $i++) {
                expect($app->test()->get('/api/users')->body())->toBe('users');
                expect($app->test()->get('/web')->body())->toBe('web');
                expect($app->test()->get('/api/web')->status())->toBe(404);
            }
        });

        it('group middleware applies consistently across requests', function () {
            $app = new App();
            $callCount = 0;

            $app->group('/api', function ($app) {
                $app->get('/test', fn () => 'ok');
            })->use(function ($req, $next) use (&$callCount) {
                $callCount++;
                return $next($req)->header('X-Count', (string) $callCount);
            });

            // Multiple requests should each trigger middleware
            $app->test()->get('/api/test');
            $app->test()->get('/api/test');
            $response = $app->test()->get('/api/test');

            expect($callCount)->toBe(3);
            expect($response->getHeader('x-count'))->toBe(['3']);
        });
    });

});
