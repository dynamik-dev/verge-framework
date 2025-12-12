<?php

declare(strict_types=1);

use Verge\App;
use Verge\Http\Response;
use Verge\Testing\TestClient;

function createTestApp(): App
{
    $app = App::buildDefaults();

    $app->routes(function ($router) {
        $router->get('/', fn() => 'home');
        $router->get('/json', fn() => ['status' => 'ok']);
        $router->get('/users/{id}', fn($id) => ['id' => $id]);
        $router->post('/users', fn(\Verge\Http\Request $req) => [
            'created' => true,
            'data' => $req->json()
        ]);
        $router->put('/users/{id}', fn($id, \Verge\Http\Request $req) => [
            'updated' => $id,
            'data' => $req->json()
        ]);
        $router->patch('/users/{id}', fn($id) => ['patched' => $id]);
        $router->delete('/users/{id}', fn($id) => null);
        $router->get('/headers', fn(\Verge\Http\Request $req) => [
            'auth' => $req->header('Authorization'),
            'custom' => $req->header('X-Custom')
        ]);
        $router->get('/cookies', fn(\Verge\Http\Request $req) => [
            'cookie' => $req->header('Cookie')
        ]);
        $router->get('/query', fn(\Verge\Http\Request $req) => [
            'page' => $req->query('page'),
            'limit' => $req->query('limit')
        ]);
    });

    return $app;
}

describe('TestClient', function () {

    describe('constructor', function () {
        it('creates a test client for an app', function () {
            $app = createTestApp();
            $client = new TestClient($app);

            expect($client)->toBeInstanceOf(TestClient::class);
        });

        it('can be created via App::test()', function () {
            $app = createTestApp();
            $client = $app->test();

            expect($client)->toBeInstanceOf(TestClient::class);
        });
    });

    describe('get()', function () {
        it('makes GET request', function () {
            $app = createTestApp();
            $response = $app->test()->get('/');

            expect($response)->toBeInstanceOf(Response::class);
            expect($response->body())->toBe('home');
        });

        it('makes GET request with query params', function () {
            $app = createTestApp();
            $response = $app->test()->get('/query', ['page' => '2', 'limit' => '10']);

            expect($response->json())->toBe([
                'page' => '2',
                'limit' => '10'
            ]);
        });

        it('gets JSON response', function () {
            $app = createTestApp();
            $response = $app->test()->get('/json');

            expect($response->json())->toBe(['status' => 'ok']);
        });

        it('gets route with parameter', function () {
            $app = createTestApp();
            $response = $app->test()->get('/users/123');

            expect($response->json())->toBe(['id' => '123']);
        });
    });

    describe('post()', function () {
        it('makes POST request', function () {
            $app = createTestApp();
            $response = $app->test()->post('/users', ['name' => 'John']);

            expect($response->json())->toBe([
                'created' => true,
                'data' => ['name' => 'John']
            ]);
        });

        it('sends JSON body', function () {
            $app = createTestApp();
            $response = $app->test()->post('/users', [
                'name' => 'Jane',
                'email' => 'jane@example.com'
            ]);

            expect($response->json()['data'])->toBe([
                'name' => 'Jane',
                'email' => 'jane@example.com'
            ]);
        });
    });

    describe('put()', function () {
        it('makes PUT request', function () {
            $app = createTestApp();
            $response = $app->test()->put('/users/42', ['name' => 'Updated']);

            expect($response->json())->toBe([
                'updated' => '42',
                'data' => ['name' => 'Updated']
            ]);
        });
    });

    describe('patch()', function () {
        it('makes PATCH request', function () {
            $app = createTestApp();
            $response = $app->test()->patch('/users/99');

            expect($response->json())->toBe(['patched' => '99']);
        });
    });

    describe('delete()', function () {
        it('makes DELETE request', function () {
            $app = createTestApp();
            $response = $app->test()->delete('/users/1');

            expect($response->status())->toBe(204);
        });
    });

    describe('withHeader()', function () {
        it('adds header to request', function () {
            $app = createTestApp();
            $response = $app->test()
                ->withHeader('Authorization', 'Bearer token123')
                ->get('/headers');

            expect($response->json()['auth'])->toBe('Bearer token123');
        });

        it('adds multiple headers', function () {
            $app = createTestApp();
            $response = $app->test()
                ->withHeader('Authorization', 'Bearer abc')
                ->withHeader('X-Custom', 'custom-value')
                ->get('/headers');

            expect($response->json())->toBe([
                'auth' => 'Bearer abc',
                'custom' => 'custom-value'
            ]);
        });

        it('returns new instance (immutable)', function () {
            $app = createTestApp();
            $client = $app->test();
            $withHeader = $client->withHeader('X-Test', 'value');

            expect($withHeader)->not->toBe($client);
        });

        it('does not modify original client', function () {
            $app = createTestApp();
            $client = $app->test();
            $client->withHeader('Authorization', 'Bearer token');
            $response = $client->get('/headers');

            expect($response->json()['auth'])->toBeNull();
        });
    });

    describe('withCookie()', function () {
        it('adds cookie to request', function () {
            $app = createTestApp();
            $response = $app->test()
                ->withCookie('session', 'abc123')
                ->get('/cookies');

            expect($response->json()['cookie'])->toBe('session=abc123');
        });

        it('adds multiple cookies', function () {
            $app = createTestApp();
            $response = $app->test()
                ->withCookie('session', 'abc')
                ->withCookie('token', 'xyz')
                ->get('/cookies');

            $cookie = $response->json()['cookie'];
            expect($cookie)->toContain('session=abc');
            expect($cookie)->toContain('token=xyz');
        });

        it('returns new instance (immutable)', function () {
            $app = createTestApp();
            $client = $app->test();
            $withCookie = $client->withCookie('test', 'value');

            expect($withCookie)->not->toBe($client);
        });
    });

    describe('chaining', function () {
        it('chains headers and cookies', function () {
            $app = App::buildDefaults();
            $app->routes(fn($r) => $r->get('/all', fn(\Verge\Http\Request $req) => [
                'auth' => $req->header('Authorization'),
                'cookie' => $req->header('Cookie')
            ]));

            $response = $app->test()
                ->withHeader('Authorization', 'Bearer token')
                ->withCookie('session', 'sess123')
                ->get('/all');

            expect($response->json()['auth'])->toBe('Bearer token');
            expect($response->json()['cookie'])->toBe('session=sess123');
        });
    });

    describe('404 handling', function () {
        it('returns 404 for unknown routes', function () {
            $app = createTestApp();
            $response = $app->test()->get('/unknown');

            expect($response->status())->toBe(404);
        });
    });

});
