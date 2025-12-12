<?php

declare(strict_types=1);

use Verge\Http\Response;
use Verge\Http\StringStream;

describe('Response', function () {

    describe('constructor', function () {
        it('creates a response with default values', function () {
            $response = new Response();

            expect($response->status())->toBe(200);
            expect($response->body())->toBe('');
            expect($response->getReasonPhrase())->toBe('OK');
        });

        it('creates a response with custom values', function () {
            $response = new Response(
                body: '{"error":"Not Found"}',
                status: 404,
                headers: ['Content-Type' => 'application/json']
            );

            expect($response->status())->toBe(404);
            expect($response->body())->toBe('{"error":"Not Found"}');
            expect($response->getReasonPhrase())->toBe('Not Found');
            expect($response->getHeader('content-type'))->toBe(['application/json']);
        });

        it('sets reason phrase for known status codes', function () {
            expect((new Response(status: 200))->getReasonPhrase())->toBe('OK');
            expect((new Response(status: 201))->getReasonPhrase())->toBe('Created');
            expect((new Response(status: 204))->getReasonPhrase())->toBe('No Content');
            expect((new Response(status: 301))->getReasonPhrase())->toBe('Moved Permanently');
            expect((new Response(status: 302))->getReasonPhrase())->toBe('Found');
            expect((new Response(status: 400))->getReasonPhrase())->toBe('Bad Request');
            expect((new Response(status: 401))->getReasonPhrase())->toBe('Unauthorized');
            expect((new Response(status: 403))->getReasonPhrase())->toBe('Forbidden');
            expect((new Response(status: 404))->getReasonPhrase())->toBe('Not Found');
            expect((new Response(status: 500))->getReasonPhrase())->toBe('Internal Server Error');
        });

        it('sets empty reason phrase for unknown status codes', function () {
            $response = new Response(status: 999);

            expect($response->getReasonPhrase())->toBe('');
        });
    });

    describe('Edge API methods', function () {

        describe('header()', function () {
            it('returns new instance with header', function () {
                $response = new Response();
                $new = $response->header('X-Custom', 'value');

                expect($response->hasHeader('X-Custom'))->toBeFalse();
                expect($new->getHeader('x-custom'))->toBe(['value']);
            });

            it('is chainable', function () {
                $response = (new Response())
                    ->header('X-First', 'one')
                    ->header('X-Second', 'two');

                expect($response->getHeader('x-first'))->toBe(['one']);
                expect($response->getHeader('x-second'))->toBe(['two']);
            });
        });

        describe('status()', function () {
            it('returns the status code', function () {
                $response = new Response(status: 201);

                expect($response->status())->toBe(201);
            });
        });

        describe('body()', function () {
            it('returns the response body', function () {
                $response = new Response(body: 'Hello World');

                expect($response->body())->toBe('Hello World');
            });
        });

        describe('json()', function () {
            it('parses JSON body', function () {
                $response = new Response(body: '{"name":"John","age":30}');

                expect($response->json())->toBe(['name' => 'John', 'age' => 30]);
            });

            it('returns empty array for empty body', function () {
                $response = new Response(body: '');

                expect($response->json())->toBe([]);
            });

            it('returns empty array for invalid JSON', function () {
                $response = new Response(body: 'not json');

                expect($response->json())->toBe([]);
            });
        });
    });

    describe('PSR-7 ResponseInterface', function () {

        describe('getStatusCode()', function () {
            it('returns the status code', function () {
                $response = new Response(status: 404);

                expect($response->getStatusCode())->toBe(404);
            });
        });

        describe('withStatus()', function () {
            it('returns new instance with status', function () {
                $response = new Response(status: 200);
                $new = $response->withStatus(404);

                expect($response->getStatusCode())->toBe(200);
                expect($new->getStatusCode())->toBe(404);
                expect($new->getReasonPhrase())->toBe('Not Found');
            });

            it('accepts custom reason phrase', function () {
                $response = new Response();
                $new = $response->withStatus(200, 'All Good');

                expect($new->getReasonPhrase())->toBe('All Good');
            });

            it('uses default reason phrase when empty', function () {
                $response = new Response();
                $new = $response->withStatus(201, '');

                expect($new->getReasonPhrase())->toBe('Created');
            });
        });

        describe('getReasonPhrase()', function () {
            it('returns the reason phrase', function () {
                $response = new Response(status: 201);

                expect($response->getReasonPhrase())->toBe('Created');
            });
        });
    });

    describe('PSR-7 MessageInterface', function () {

        describe('getProtocolVersion()', function () {
            it('returns default protocol version', function () {
                $response = new Response();

                expect($response->getProtocolVersion())->toBe('1.1');
            });
        });

        describe('withProtocolVersion()', function () {
            it('returns new instance with version', function () {
                $response = new Response();
                $new = $response->withProtocolVersion('2.0');

                expect($response->getProtocolVersion())->toBe('1.1');
                expect($new->getProtocolVersion())->toBe('2.0');
            });
        });

        describe('getHeaders()', function () {
            it('returns all headers as arrays', function () {
                $response = new Response(headers: [
                    'Content-Type' => 'application/json',
                    'X-Custom' => ['one', 'two']
                ]);

                $headers = $response->getHeaders();

                expect($headers['content-type'])->toBe(['application/json']);
                expect($headers['x-custom'])->toBe(['one', 'two']);
            });
        });

        describe('hasHeader()', function () {
            it('returns true for existing header', function () {
                $response = new Response(headers: ['Content-Type' => 'text/plain']);

                expect($response->hasHeader('Content-Type'))->toBeTrue();
                expect($response->hasHeader('content-type'))->toBeTrue();
            });

            it('returns false for missing header', function () {
                $response = new Response();

                expect($response->hasHeader('X-Missing'))->toBeFalse();
            });
        });

        describe('getHeader()', function () {
            it('returns header values as array', function () {
                $response = new Response(headers: ['Accept' => ['text/html', 'text/plain']]);

                expect($response->getHeader('Accept'))->toBe(['text/html', 'text/plain']);
            });

            it('is case-insensitive', function () {
                $response = new Response(headers: ['Content-Type' => 'text/html']);

                expect($response->getHeader('CONTENT-TYPE'))->toBe(['text/html']);
            });

            it('returns empty array for missing header', function () {
                $response = new Response();

                expect($response->getHeader('X-Missing'))->toBe([]);
            });
        });

        describe('getHeaderLine()', function () {
            it('returns comma-separated values', function () {
                $response = new Response(headers: ['Accept' => ['text/html', 'text/plain']]);

                expect($response->getHeaderLine('Accept'))->toBe('text/html, text/plain');
            });

            it('returns empty string for missing header', function () {
                $response = new Response();

                expect($response->getHeaderLine('X-Missing'))->toBe('');
            });
        });

        describe('withHeader()', function () {
            it('returns new instance with header', function () {
                $response = new Response();
                $new = $response->withHeader('X-Custom', 'value');

                expect($response->hasHeader('X-Custom'))->toBeFalse();
                expect($new->getHeader('x-custom'))->toBe(['value']);
            });

            it('replaces existing header', function () {
                $response = new Response(headers: ['X-Custom' => 'old']);
                $new = $response->withHeader('X-Custom', 'new');

                expect($new->getHeader('x-custom'))->toBe(['new']);
            });

            it('accepts array of values', function () {
                $response = new Response();
                $new = $response->withHeader('Accept', ['text/html', 'text/plain']);

                expect($new->getHeader('accept'))->toBe(['text/html', 'text/plain']);
            });
        });

        describe('withAddedHeader()', function () {
            it('adds to existing header', function () {
                $response = new Response(headers: ['Accept' => 'text/html']);
                $new = $response->withAddedHeader('Accept', 'text/plain');

                expect($new->getHeader('accept'))->toBe(['text/html', 'text/plain']);
            });

            it('creates header if not exists', function () {
                $response = new Response();
                $new = $response->withAddedHeader('X-Custom', 'value');

                expect($new->getHeader('x-custom'))->toBe(['value']);
            });
        });

        describe('withoutHeader()', function () {
            it('removes header', function () {
                $response = new Response(headers: ['X-Custom' => 'value']);
                $new = $response->withoutHeader('X-Custom');

                expect($response->hasHeader('X-Custom'))->toBeTrue();
                expect($new->hasHeader('X-Custom'))->toBeFalse();
            });

            it('is case-insensitive', function () {
                $response = new Response(headers: ['X-Custom' => 'value']);
                $new = $response->withoutHeader('x-custom');

                expect($new->hasHeader('X-Custom'))->toBeFalse();
            });
        });

        describe('getBody()', function () {
            it('returns StringStream instance', function () {
                $response = new Response(body: 'Hello');

                $body = $response->getBody();

                expect($body)->toBeInstanceOf(StringStream::class);
                expect((string) $body)->toBe('Hello');
            });
        });

        describe('withBody()', function () {
            it('returns new instance with body', function () {
                $response = new Response(body: 'old');
                $new = $response->withBody(new StringStream('new'));

                expect($response->body())->toBe('old');
                expect($new->body())->toBe('new');
            });
        });
    });

});
