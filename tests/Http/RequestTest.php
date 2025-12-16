<?php

declare(strict_types=1);

use Verge\Http\Request;
use Verge\Http\StringStream;
use Verge\Http\UploadedFile;
use Verge\Http\Uri;

describe('Request', function () {

    describe('constructor', function () {
        it('creates a request with default values', function () {
            $request = Request::create();

            expect($request->method())->toBe('GET');
            expect($request->path())->toBe('/');
            expect($request->body())->toBeNull();
        });

        it('creates a request with custom values', function () {
            $request = Request::create(
                method: 'POST',
                uri: '/users',
                headers: ['Content-Type' => 'application/json'],
                body: '{"name":"John"}',
                query: ['page' => '1'],
                parsedBody: ['name' => 'John']
            );

            expect($request->method())->toBe('POST');
            expect($request->path())->toBe('/users');
            expect($request->body())->toBe('{"name":"John"}');
            expect($request->query('page'))->toBe('1');
            expect($request->input('name'))->toBe('John');
        });

        it('uppercases the HTTP method', function () {
            $request = Request::create(method: 'post');

            expect($request->method())->toBe('POST');
        });

        it('accepts a Uri object', function () {
            $uri = new Uri('/api/users?id=1');
            $request = Request::create(uri: $uri);

            expect($request->path())->toBe('/api/users');
        });
    });

    describe('Edge API methods', function () {

        describe('json()', function () {
            it('parses JSON body', function () {
                $request = Request::create(body: '{"name":"John","age":30}');

                expect($request->json())->toBe(['name' => 'John', 'age' => 30]);
            });

            it('returns empty array for null body', function () {
                $request = Request::create(body: null);

                expect($request->json())->toBe([]);
            });

            it('returns empty array for invalid JSON', function () {
                $request = Request::create(body: 'not json');

                expect($request->json())->toBe([]);
            });
        });

        describe('body()', function () {
            it('returns the raw body', function () {
                $request = Request::create(body: 'raw content');

                expect($request->body())->toBe('raw content');
            });

            it('returns null when no body', function () {
                $request = Request::create();

                expect($request->body())->toBeNull();
            });
        });

        describe('input()', function () {
            it('returns value from parsed body', function () {
                $request = Request::create(parsedBody: ['name' => 'John']);

                expect($request->input('name'))->toBe('John');
            });

            it('falls back to query params', function () {
                $request = Request::create(query: ['page' => '2']);

                expect($request->input('page'))->toBe('2');
            });

            it('prefers parsed body over query', function () {
                $request = Request::create(
                    parsedBody: ['key' => 'body'],
                    query: ['key' => 'query']
                );

                expect($request->input('key'))->toBe('body');
            });

            it('returns default when key not found', function () {
                $request = Request::create();

                expect($request->input('missing', 'default'))->toBe('default');
            });

            it('returns null by default when key not found', function () {
                $request = Request::create();

                expect($request->input('missing'))->toBeNull();
            });
        });

        describe('query()', function () {
            it('returns query parameter by key', function () {
                $request = Request::create(query: ['page' => '1', 'limit' => '10']);

                expect($request->query('page'))->toBe('1');
                expect($request->query('limit'))->toBe('10');
            });

            it('returns all query parameters when no key provided', function () {
                $request = Request::create(query: ['page' => '1', 'limit' => '10']);

                expect($request->query())->toBe(['page' => '1', 'limit' => '10']);
            });

            it('returns default when key not found', function () {
                $request = Request::create();

                expect($request->query('missing', 'default'))->toBe('default');
            });
        });

        describe('header()', function () {
            it('returns header value', function () {
                $request = Request::create(headers: ['Authorization' => 'Bearer token']);

                expect($request->header('Authorization'))->toBe('Bearer token');
            });

            it('is case-insensitive', function () {
                $request = Request::create(headers: ['Content-Type' => 'application/json']);

                expect($request->header('content-type'))->toBe('application/json');
                expect($request->header('CONTENT-TYPE'))->toBe('application/json');
            });

            it('returns null for missing header', function () {
                $request = Request::create();

                expect($request->header('X-Missing'))->toBeNull();
            });
        });

        describe('headers()', function () {
            it('returns all headers', function () {
                $request = Request::create(headers: [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer token'
                ]);

                $headers = $request->headers();

                expect($headers)->toHaveKey('content-type');
                expect($headers)->toHaveKey('authorization');
                expect($headers['content-type'])->toBe('application/json');
            });
        });

        describe('file()', function () {
            it('returns UploadedFile instance', function () {
                $request = Request::create(files: [
                    'avatar' => [
                        'name' => 'photo.jpg',
                        'type' => 'image/jpeg',
                        'tmp_name' => '/tmp/php123',
                        'error' => UPLOAD_ERR_OK,
                        'size' => 1024
                    ]
                ]);

                $file = $request->file('avatar');

                expect($file)->toBeInstanceOf(UploadedFile::class);
                expect($file->getClientFilename())->toBe('photo.jpg');
            });

            it('returns null for missing file', function () {
                $request = Request::create();

                expect($request->file('missing'))->toBeNull();
            });
        });

        describe('method()', function () {
            it('returns the HTTP method', function () {
                $request = Request::create(method: 'DELETE');

                expect($request->method())->toBe('DELETE');
            });
        });

        describe('path()', function () {
            it('returns the request path', function () {
                $request = Request::create(uri: '/api/users/123');

                expect($request->path())->toBe('/api/users/123');
            });

            it('returns path without query string', function () {
                $request = Request::create(uri: '/api/users?page=1');

                expect($request->path())->toBe('/api/users');
            });
        });

        describe('url()', function () {
            it('returns the full URL', function () {
                $request = Request::create(uri: 'https://example.com/api/users?page=1');

                expect($request->url())->toBe('https://example.com/api/users?page=1');
            });
        });
    });

    describe('PSR-7 RequestInterface', function () {

        describe('getRequestTarget()', function () {
            it('returns path for simple request', function () {
                $request = Request::create(uri: '/users');

                expect($request->getRequestTarget())->toBe('/users');
            });

            it('includes query string', function () {
                $request = Request::create(uri: '/users?page=1');

                expect($request->getRequestTarget())->toBe('/users?page=1');
            });

            it('returns / for empty path', function () {
                $request = Request::create(uri: '');

                expect($request->getRequestTarget())->toBe('/');
            });
        });

        describe('withRequestTarget()', function () {
            it('returns new instance with updated target', function () {
                $request = Request::create(uri: '/old');
                $new = $request->withRequestTarget('/new');

                expect($request->getRequestTarget())->toBe('/old');
                expect($new->getRequestTarget())->toBe('/new');
            });
        });

        describe('getMethod()', function () {
            it('returns the HTTP method', function () {
                $request = Request::create(method: 'PUT');

                expect($request->getMethod())->toBe('PUT');
            });
        });

        describe('withMethod()', function () {
            it('returns new instance with updated method', function () {
                $request = Request::create(method: 'GET');
                $new = $request->withMethod('POST');

                expect($request->getMethod())->toBe('GET');
                expect($new->getMethod())->toBe('POST');
            });

            it('uppercases the method', function () {
                $request = Request::create();
                $new = $request->withMethod('patch');

                expect($new->getMethod())->toBe('PATCH');
            });
        });

        describe('getUri()', function () {
            it('returns Uri instance', function () {
                $request = Request::create(uri: '/api/users');

                expect($request->getUri())->toBeInstanceOf(Uri::class);
                expect($request->getUri()->getPath())->toBe('/api/users');
            });
        });

        describe('withUri()', function () {
            it('returns new instance with updated URI', function () {
                $request = Request::create(uri: '/old');
                $newUri = new Uri('/new');
                $new = $request->withUri($newUri);

                expect($request->path())->toBe('/old');
                expect($new->path())->toBe('/new');
            });
        });
    });

    describe('PSR-7 MessageInterface', function () {

        describe('getProtocolVersion()', function () {
            it('returns default protocol version', function () {
                $request = Request::create();

                expect($request->getProtocolVersion())->toBe('1.1');
            });
        });

        describe('withProtocolVersion()', function () {
            it('returns new instance with updated version', function () {
                $request = Request::create();
                $new = $request->withProtocolVersion('2.0');

                expect($request->getProtocolVersion())->toBe('1.1');
                expect($new->getProtocolVersion())->toBe('2.0');
            });
        });

        describe('getHeaders()', function () {
            it('returns all headers as arrays', function () {
                $request = Request::create(headers: [
                    'Content-Type' => 'application/json',
                    'Accept' => ['text/html', 'application/json']
                ]);

                $headers = $request->getHeaders();

                expect($headers['content-type'])->toBe(['application/json']);
                expect($headers['accept'])->toBe(['text/html', 'application/json']);
            });
        });

        describe('hasHeader()', function () {
            it('returns true for existing header', function () {
                $request = Request::create(headers: ['Content-Type' => 'text/plain']);

                expect($request->hasHeader('Content-Type'))->toBeTrue();
                expect($request->hasHeader('content-type'))->toBeTrue();
            });

            it('returns false for missing header', function () {
                $request = Request::create();

                expect($request->hasHeader('X-Missing'))->toBeFalse();
            });
        });

        describe('getHeader()', function () {
            it('returns header values as array', function () {
                $request = Request::create(headers: ['Accept' => ['text/html', 'application/json']]);

                expect($request->getHeader('Accept'))->toBe(['text/html', 'application/json']);
            });

            it('returns empty array for missing header', function () {
                $request = Request::create();

                expect($request->getHeader('X-Missing'))->toBe([]);
            });
        });

        describe('getHeaderLine()', function () {
            it('returns comma-separated header values', function () {
                $request = Request::create(headers: ['Accept' => ['text/html', 'application/json']]);

                expect($request->getHeaderLine('Accept'))->toBe('text/html, application/json');
            });

            it('returns empty string for missing header', function () {
                $request = Request::create();

                expect($request->getHeaderLine('X-Missing'))->toBe('');
            });
        });

        describe('withHeader()', function () {
            it('returns new instance with header', function () {
                $request = Request::create();
                $new = $request->withHeader('X-Custom', 'value');

                expect($request->hasHeader('X-Custom'))->toBeFalse();
                expect($new->getHeader('x-custom'))->toBe(['value']);
            });

            it('replaces existing header', function () {
                $request = Request::create(headers: ['X-Custom' => 'old']);
                $new = $request->withHeader('X-Custom', 'new');

                expect($new->getHeader('x-custom'))->toBe(['new']);
            });

            it('accepts array of values', function () {
                $request = Request::create();
                $new = $request->withHeader('Accept', ['text/html', 'text/plain']);

                expect($new->getHeader('accept'))->toBe(['text/html', 'text/plain']);
            });
        });

        describe('withAddedHeader()', function () {
            it('adds to existing header', function () {
                $request = Request::create(headers: ['Accept' => 'text/html']);
                $new = $request->withAddedHeader('Accept', 'application/json');

                expect($new->getHeader('accept'))->toBe(['text/html', 'application/json']);
            });

            it('creates header if not exists', function () {
                $request = Request::create();
                $new = $request->withAddedHeader('X-Custom', 'value');

                expect($new->getHeader('x-custom'))->toBe(['value']);
            });
        });

        describe('withoutHeader()', function () {
            it('removes header', function () {
                $request = Request::create(headers: ['X-Custom' => 'value']);
                $new = $request->withoutHeader('X-Custom');

                expect($request->hasHeader('X-Custom'))->toBeTrue();
                expect($new->hasHeader('X-Custom'))->toBeFalse();
            });
        });

        describe('getBody()', function () {
            it('returns StringStream instance', function () {
                $request = Request::create(body: 'content');

                $body = $request->getBody();

                expect($body)->toBeInstanceOf(StringStream::class);
                expect((string) $body)->toBe('content');
            });

            it('returns empty stream for null body', function () {
                $request = Request::create();

                expect((string) $request->getBody())->toBe('');
            });
        });

        describe('withBody()', function () {
            it('returns new instance with updated body', function () {
                $request = Request::create(body: 'old');
                $new = $request->withBody(new StringStream('new'));

                expect($request->body())->toBe('old');
                expect($new->body())->toBe('new');
            });
        });
    });

    describe('custom with methods', function () {

        describe('withParsedBody()', function () {
            it('returns new instance with parsed body', function () {
                $request = Request::create();
                $new = $request->withParsedBody(['name' => 'John']);

                expect($request->input('name'))->toBeNull();
                expect($new->input('name'))->toBe('John');
            });
        });

        describe('withQuery()', function () {
            it('returns new instance with query params', function () {
                $request = Request::create();
                $new = $request->withQuery(['page' => '1']);

                expect($request->query('page'))->toBeNull();
                expect($new->query('page'))->toBe('1');
            });
        });
    });

});
