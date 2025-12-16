<?php

declare(strict_types=1);

use Verge\Http\Response;
use Verge\Http\Response\HtmlResponse;

describe('HtmlResponse', function () {
    it('creates HTML response with content', function () {
        $html = '<h1>Hello World</h1>';
        $response = new HtmlResponse($html);

        expect($response->status())->toBe(200);
        expect($response->getHeaderLine('content-type'))->toBe('text/html; charset=utf-8');
        expect($response->body())->toBe($html);
    });

    it('allows custom status code', function () {
        $response = new HtmlResponse('<h1>Not Found</h1>', 404);

        expect($response->status())->toBe(404);
    });

    it('allows custom headers', function () {
        $response = new HtmlResponse('<html></html>', 200, ['x-custom' => 'value']);

        expect($response->getHeaderLine('x-custom'))->toBe('value');
    });

    it('handles complex HTML', function () {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head><title>Test</title></head>
<body>
    <h1>Welcome</h1>
    <p>Content here</p>
</body>
</html>
HTML;

        $response = new HtmlResponse($html);

        expect($response->body())->toBe($html);
    });

    it('extends Response', function () {
        $response = new HtmlResponse('<html></html>');

        expect($response)->toBeInstanceOf(Response::class);
    });

    it('supports fluent header method', function () {
        $response = (new HtmlResponse('<html></html>'))->header('x-test', 'value');

        expect($response->getHeaderLine('x-test'))->toBe('value');
        expect($response->getHeaderLine('content-type'))->toBe('text/html; charset=utf-8');
    });
});
