<?php

declare(strict_types=1);

use Psr\Http\Client\ClientInterface;
use Verge\App;
use Verge\Http\Client\Client;
use Verge\Http\Response;
use Verge\Verge;
use Verge\Routing\RouterInterface;

use function Verge\http;
use function Verge\json;
use function Verge\make;
use function Verge\redirect;
use function Verge\response;

describe('Helper Functions', function () {

    describe('app()', function () {
        beforeEach(fn () => Verge::reset());

        it('returns an App instance', function () {
            $result = app();

            expect($result)->toBeInstanceOf(App::class);
        });

        it('creates app if none exists', function () {
            expect(Verge::app())->toBeNull();

            $result = app();

            expect($result)->toBeInstanceOf(App::class);
            expect(Verge::app())->toBe($result);
        });

        it('returns existing app if one exists', function () {
            $existing = Verge::create();

            $result = app();

            expect($result)->toBe($existing);
        });

        it('returns same instance on multiple calls', function () {
            $first = app();
            $second = app();

            expect($first)->toBe($second);
        });
    });

    describe('make()', function () {
        beforeEach(fn () => Verge::reset());

        it('resolves from container', function () {
            Verge::create();

            $router = make(RouterInterface::class);

            expect($router)->toBeInstanceOf(RouterInterface::class);
        });

        it('throws when no app exists', function () {
            expect(fn () => make(RouterInterface::class))
                ->toThrow(RuntimeException::class);
        });
    });

    describe('response()', function () {
        it('creates a Response instance', function () {
            $response = response();

            expect($response)->toBeInstanceOf(Response::class);
        });

        it('creates response with default values', function () {
            $response = response();

            expect($response->body())->toBe('');
            expect($response->status())->toBe(200);
        });

        it('creates response with body', function () {
            $response = response('Hello World');

            expect($response->body())->toBe('Hello World');
        });

        it('creates response with status', function () {
            $response = response('Created', 201);

            expect($response->status())->toBe(201);
        });

        it('creates response with headers', function () {
            $response = response('', 200, ['X-Custom' => 'value']);

            expect($response->getHeader('x-custom'))->toBe(['value']);
        });

        it('creates response with all parameters', function () {
            $response = response('Body content', 201, [
                'Content-Type' => 'text/html',
                'X-Custom' => 'header'
            ]);

            expect($response->body())->toBe('Body content');
            expect($response->status())->toBe(201);
            expect($response->getHeader('content-type'))->toBe(['text/html']);
            expect($response->getHeader('x-custom'))->toBe(['header']);
        });
    });

    describe('json()', function () {
        it('creates a Response instance', function () {
            $response = json([]);

            expect($response)->toBeInstanceOf(Response::class);
        });

        it('encodes data as JSON', function () {
            $response = json(['name' => 'John', 'age' => 30]);

            expect($response->body())->toBe('{"name":"John","age":30}');
        });

        it('sets Content-Type header', function () {
            $response = json([]);

            expect($response->getHeader('content-type'))->toBe(['application/json']);
        });

        it('uses default 200 status', function () {
            $response = json(['ok' => true]);

            expect($response->status())->toBe(200);
        });

        it('accepts custom status', function () {
            $response = json(['error' => 'Not found'], 404);

            expect($response->status())->toBe(404);
        });

        it('accepts additional headers', function () {
            $response = json([], 200, ['X-Custom' => 'value']);

            expect($response->getHeader('x-custom'))->toBe(['value']);
            expect($response->getHeader('content-type'))->toBe(['application/json']);
        });

        it('encodes nested arrays', function () {
            $response = json([
                'user' => ['name' => 'John', 'roles' => ['admin', 'user']],
                'count' => 2
            ]);

            $decoded = json_decode($response->body(), true);
            assert(is_array($decoded));

            expect($decoded['user']['name'])->toBe('John');
            expect($decoded['user']['roles'])->toBe(['admin', 'user']);
        });

        it('throws exception on invalid JSON', function () {
            $data = ['self' => null];
            $data['self'] = &$data; // Circular reference

            expect(fn () => json($data))->toThrow(InvalidArgumentException::class, 'JSON encode failed');
        });

        it('encodes empty array', function () {
            $response = json([]);

            expect($response->body())->toBe('[]');
        });

        it('encodes scalar values', function () {
            expect(json('string')->body())->toBe('"string"');
            expect(json(42)->body())->toBe('42');
            expect(json(true)->body())->toBe('true');
            expect(json(null)->body())->toBe('null');
        });
    });

    describe('redirect()', function () {
        it('creates a Response instance', function () {
            $response = redirect('/');

            expect($response)->toBeInstanceOf(Response::class);
        });

        it('sets Location header', function () {
            $response = redirect('/dashboard');

            expect($response->getHeader('location'))->toBe(['/dashboard']);
        });

        it('uses 302 status by default', function () {
            $response = redirect('/home');

            expect($response->status())->toBe(302);
        });

        it('accepts custom status', function () {
            $response = redirect('/new-location', 301);

            expect($response->status())->toBe(301);
        });

        it('has empty body', function () {
            $response = redirect('/somewhere');

            expect($response->body())->toBe('');
        });

        it('handles external URLs', function () {
            $response = redirect('https://example.com/page');

            expect($response->getHeader('location'))->toBe(['https://example.com/page']);
        });

        it('handles URLs with query strings', function () {
            $response = redirect('/search?q=test&page=1');

            expect($response->getHeader('location'))->toBe(['/search?q=test&page=1']);
        });
    });

    describe('http()', function () {
        beforeEach(fn () => Verge::reset());

        it('returns a Client instance', function () {
            Verge::create();

            $client = http();

            expect($client)->toBeInstanceOf(Client::class);
        });

        it('returns new instance each call by default', function () {
            Verge::create();

            $first = http();
            $second = http();

            expect($first)->not->toBe($second);
        });

        it('returns singleton when bound as singleton', function () {
            $app = Verge::create();
            $app->singleton(Client::class, fn () => new Client());

            $first = http();
            $second = http();

            expect($first)->toBe($second);
        });

        it('returns configured client when bound', function () {
            $app = Verge::create();
            $app->singleton(Client::class, fn () => (new Client())->withBaseUri('https://api.example.com'));

            $client = http();

            expect($client)->toBeInstanceOf(Client::class);
        });

        it('resolves PSR-18 ClientInterface to Client', function () {
            $app = Verge::create();

            $client = $app->make(ClientInterface::class);

            expect($client)->toBeInstanceOf(Client::class);
        });

        it('swapping Client binding affects ClientInterface resolution', function () {
            $app = Verge::create();
            $configured = (new Client())->withTimeout(99);
            $app->singleton(Client::class, fn () => $configured);

            $viaInterface = $app->make(ClientInterface::class);
            $viaConcrete = $app->make(Client::class);

            expect($viaInterface)->toBe($configured);
            expect($viaConcrete)->toBe($configured);
        });
    });

});
