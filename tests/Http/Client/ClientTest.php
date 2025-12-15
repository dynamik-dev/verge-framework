<?php

declare(strict_types=1);

use Psr\Http\Client\ClientInterface;
use Verge\Http\Client\Client;
use Verge\Http\Client\ClientException;
use Verge\Http\Client\NetworkException;
use Verge\Http\Client\RequestException;
use Verge\Http\Request;
use Verge\Http\Response;

describe('Client', function () {
    describe('PSR-18 compliance', function () {
        it('implements ClientInterface', function () {
            $client = new Client();

            expect($client)->toBeInstanceOf(ClientInterface::class);
        });

        it('has sendRequest method', function () {
            $client = new Client();

            expect(method_exists($client, 'sendRequest'))->toBeTrue();
        });
    });

    describe('configuration', function () {
        it('creates immutable clone with withBaseUri', function () {
            $client = new Client();
            $configured = $client->withBaseUri('https://api.example.com');

            expect($configured)->not->toBe($client);
            expect($configured)->toBeInstanceOf(Client::class);
        });

        it('creates immutable clone with withTimeout', function () {
            $client = new Client();
            $configured = $client->withTimeout(60);

            expect($configured)->not->toBe($client);
        });

        it('creates immutable clone with withConnectTimeout', function () {
            $client = new Client();
            $configured = $client->withConnectTimeout(5);

            expect($configured)->not->toBe($client);
        });

        it('creates immutable clone with withVerifyPeer', function () {
            $client = new Client();
            $configured = $client->withVerifyPeer(false);

            expect($configured)->not->toBe($client);
        });

        it('creates immutable clone with withDefaultHeaders', function () {
            $client = new Client();
            $configured = $client->withDefaultHeaders(['Authorization' => 'Bearer token']);

            expect($configured)->not->toBe($client);
        });

        it('creates immutable clone with withDefaultHeader', function () {
            $client = new Client();
            $configured = $client->withDefaultHeader('X-API-Key', 'secret');

            expect($configured)->not->toBe($client);
        });

        it('allows chaining configuration', function () {
            $client = (new Client())
                ->withBaseUri('https://api.example.com')
                ->withTimeout(60)
                ->withConnectTimeout(5)
                ->withDefaultHeader('Authorization', 'Bearer token')
                ->withVerifyPeer(false);

            expect($client)->toBeInstanceOf(Client::class);
        });
    });

    describe('convenience methods', function () {
        it('has get method', function () {
            expect(method_exists(Client::class, 'get'))->toBeTrue();
        });

        it('has post method', function () {
            expect(method_exists(Client::class, 'post'))->toBeTrue();
        });

        it('has put method', function () {
            expect(method_exists(Client::class, 'put'))->toBeTrue();
        });

        it('has patch method', function () {
            expect(method_exists(Client::class, 'patch'))->toBeTrue();
        });

        it('has delete method', function () {
            expect(method_exists(Client::class, 'delete'))->toBeTrue();
        });

        it('has postJson method', function () {
            expect(method_exists(Client::class, 'postJson'))->toBeTrue();
        });

        it('has putJson method', function () {
            expect(method_exists(Client::class, 'putJson'))->toBeTrue();
        });

        it('has patchJson method', function () {
            expect(method_exists(Client::class, 'patchJson'))->toBeTrue();
        });
    });
});

describe('ClientException', function () {
    it('implements PSR-18 ClientExceptionInterface', function () {
        $exception = new ClientException('test');

        expect($exception)->toBeInstanceOf(\Psr\Http\Client\ClientExceptionInterface::class);
    });

    it('extends RuntimeException', function () {
        $exception = new ClientException('test');

        expect($exception)->toBeInstanceOf(\RuntimeException::class);
    });
});

describe('NetworkException', function () {
    it('implements PSR-18 NetworkExceptionInterface', function () {
        $request = new Request('GET', '/');
        $exception = new NetworkException($request, 'Connection failed');

        expect($exception)->toBeInstanceOf(\Psr\Http\Client\NetworkExceptionInterface::class);
    });

    it('provides access to the request', function () {
        $request = new Request('GET', '/test');
        $exception = new NetworkException($request, 'Timeout');

        expect($exception->getRequest())->toBe($request);
    });

    it('extends ClientException', function () {
        $request = new Request('GET', '/');
        $exception = new NetworkException($request, 'error');

        expect($exception)->toBeInstanceOf(ClientException::class);
    });
});

describe('RequestException', function () {
    it('implements PSR-18 RequestExceptionInterface', function () {
        $request = new Request('GET', '/');
        $exception = new RequestException($request, 'Invalid URL');

        expect($exception)->toBeInstanceOf(\Psr\Http\Client\RequestExceptionInterface::class);
    });

    it('provides access to the request', function () {
        $request = new Request('POST', '/api/data');
        $exception = new RequestException($request, 'Bad request');

        expect($exception->getRequest())->toBe($request);
    });

    it('extends ClientException', function () {
        $request = new Request('GET', '/');
        $exception = new RequestException($request, 'error');

        expect($exception)->toBeInstanceOf(ClientException::class);
    });
});

describe('Client network behavior', function () {
    it('throws NetworkException on connection failure', function () {
        $client = (new Client())
            ->withTimeout(1)
            ->withConnectTimeout(1);

        // Use an invalid host to trigger network error
        expect(fn () => $client->get('http://invalid.host.that.does.not.exist.local/'))
            ->toThrow(NetworkException::class);
    });

    it('returns a Response object for any HTTP response', function () {
        $client = new Client();

        try {
            $response = $client->get('https://arter.dev');
            expect($response)->toBeInstanceOf(Response::class);
            expect($response->status())->toBeGreaterThanOrEqual(200);
            expect($response->status())->toBeLessThan(600);
        } catch (NetworkException $e) {
            // Network may not be available in CI
            $this->markTestSkipped('Network not available: ' . $e->getMessage());
        }
    });
});
