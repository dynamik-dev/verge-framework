<?php

declare(strict_types=1);

use Verge\Http\Response;
use Verge\Http\Response\EmptyResponse;

describe('EmptyResponse', function () {
    it('creates a 204 No Content response by default', function () {
        $response = new EmptyResponse();

        expect($response->status())->toBe(204);
        expect($response->body())->toBe('');
    });

    it('allows custom status code', function () {
        $response = new EmptyResponse(202);

        expect($response->status())->toBe(202);
    });

    it('allows custom headers', function () {
        $response = new EmptyResponse(204, ['x-custom' => 'value']);

        expect($response->getHeaderLine('x-custom'))->toBe('value');
    });

    it('extends Response', function () {
        $response = new EmptyResponse();

        expect($response)->toBeInstanceOf(Response::class);
    });

    it('supports fluent header method', function () {
        $response = (new EmptyResponse())->header('x-test', 'value');

        expect($response->getHeaderLine('x-test'))->toBe('value');
    });
});
