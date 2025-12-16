<?php

declare(strict_types=1);

use Verge\Http\Response;
use Verge\Http\Response\RedirectResponse;

describe('RedirectResponse', function () {
    it('creates a 302 redirect by default', function () {
        $response = new RedirectResponse('/dashboard');

        expect($response->status())->toBe(302);
        expect($response->getHeaderLine('location'))->toBe('/dashboard');
    });

    it('allows custom status code', function () {
        $response = new RedirectResponse('/new-location', 301);

        expect($response->status())->toBe(301);
    });

    it('has empty body', function () {
        $response = new RedirectResponse('/dashboard');

        expect($response->body())->toBe('');
    });

    it('allows custom headers', function () {
        $response = new RedirectResponse('/dashboard', 302, ['x-custom' => 'value']);

        expect($response->getHeaderLine('x-custom'))->toBe('value');
    });

    it('extends Response', function () {
        $response = new RedirectResponse('/dashboard');

        expect($response)->toBeInstanceOf(Response::class);
    });

    it('provides target URL accessor', function () {
        $response = new RedirectResponse('https://example.com/path');

        expect($response->getTargetUrl())->toBe('https://example.com/path');
    });

    it('supports external URLs', function () {
        $response = new RedirectResponse('https://external.com/page?foo=bar');

        expect($response->getTargetUrl())->toBe('https://external.com/page?foo=bar');
    });
});
