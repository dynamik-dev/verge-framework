<?php

declare(strict_types=1);

use Verge\Http\Response;
use Verge\Http\Response\JsonResponse;

describe('JsonResponse', function () {
    it('creates JSON response with array data', function () {
        $response = new JsonResponse(['name' => 'John', 'age' => 30]);

        expect($response->status())->toBe(200);
        expect($response->getHeaderLine('content-type'))->toBe('application/json');
        expect($response->json())->toBe(['name' => 'John', 'age' => 30]);
    });

    it('allows custom status code', function () {
        $response = new JsonResponse(['created' => true], 201);

        expect($response->status())->toBe(201);
    });

    it('allows custom headers', function () {
        $response = new JsonResponse(['data' => 1], 200, ['x-custom' => 'value']);

        expect($response->getHeaderLine('x-custom'))->toBe('value');
    });

    it('encodes nested arrays', function () {
        $data = [
            'user' => [
                'name' => 'John',
                'roles' => ['admin', 'user'],
            ],
        ];

        $response = new JsonResponse($data);

        expect($response->json())->toBe($data);
    });

    it('encodes scalar values', function () {
        $response = new JsonResponse('string');
        expect($response->body())->toBe('"string"');

        $response = new JsonResponse(123);
        expect($response->body())->toBe('123');

        $response = new JsonResponse(true);
        expect($response->body())->toBe('true');

        $response = new JsonResponse(null);
        expect($response->body())->toBe('null');
    });

    it('throws on invalid JSON data', function () {
        $data = ['self' => null];
        $data['self'] = &$data; // Circular reference

        expect(fn () => new JsonResponse($data))
            ->toThrow(InvalidArgumentException::class, 'JSON encode failed');
    });

    it('extends Response', function () {
        $response = new JsonResponse([]);

        expect($response)->toBeInstanceOf(Response::class);
    });

    it('supports fluent header method', function () {
        $response = (new JsonResponse(['data' => 1]))->header('x-test', 'value');

        expect($response->getHeaderLine('x-test'))->toBe('value');
        expect($response->getHeaderLine('content-type'))->toBe('application/json');
    });
});
