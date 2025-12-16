<?php

declare(strict_types=1);

use Verge\Http\Response;
use Verge\Http\Response\StreamResponse;

describe('StreamResponse', function () {
    it('creates stream response from resource', function () {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'test content');
        rewind($resource);

        $response = new StreamResponse($resource);

        expect($response->status())->toBe(200);
        expect($response->getHeaderLine('content-type'))->toBe('application/octet-stream');
        expect($response->getResource())->toBe($resource);

        fclose($resource);
    });

    it('allows custom content type', function () {
        $resource = fopen('php://memory', 'r');
        $response = new StreamResponse($resource, 'application/pdf');

        expect($response->getHeaderLine('content-type'))->toBe('application/pdf');

        fclose($resource);
    });

    it('allows custom status code', function () {
        $resource = fopen('php://memory', 'r');
        $response = new StreamResponse($resource, 'application/octet-stream', 206);

        expect($response->status())->toBe(206);

        fclose($resource);
    });

    it('allows custom headers', function () {
        $resource = fopen('php://memory', 'r');
        $response = new StreamResponse($resource, 'application/octet-stream', 200, ['x-custom' => 'value']);

        expect($response->getHeaderLine('x-custom'))->toBe('value');

        fclose($resource);
    });

    it('throws for non-resource argument', function () {
        expect(fn () => new StreamResponse('not a resource'))
            ->toThrow(InvalidArgumentException::class, 'StreamResponse requires a valid resource');
    });

    it('extends Response', function () {
        $resource = fopen('php://memory', 'r');
        $response = new StreamResponse($resource);

        expect($response)->toBeInstanceOf(Response::class);

        fclose($resource);
    });

    it('has empty body string', function () {
        $resource = fopen('php://memory', 'r');
        $response = new StreamResponse($resource);

        // Body is stored in resource, not string
        expect($response->body())->toBe('');

        fclose($resource);
    });
});
