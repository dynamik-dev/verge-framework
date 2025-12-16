<?php

declare(strict_types=1);

use Verge\Http\Response\DownloadResponse;
use Verge\Http\Response\FileResponse;
use Verge\Http\Response\HtmlResponse;
use Verge\Http\Response\JsonResponse;
use Verge\Http\Response\RedirectResponse;

use function Verge\download;
use function Verge\file;
use function Verge\html;
use function Verge\json;
use function Verge\redirect;

describe('Response Helper Functions', function () {
    describe('json()', function () {
        it('returns JsonResponse', function () {
            $response = json(['test' => 'data']);

            expect($response)->toBeInstanceOf(JsonResponse::class);
            expect($response->json())->toBe(['test' => 'data']);
        });

        it('accepts status code', function () {
            $response = json(['created' => true], 201);

            expect($response->status())->toBe(201);
        });

        it('accepts headers', function () {
            $response = json(['data' => 1], 200, ['x-custom' => 'value']);

            expect($response->getHeaderLine('x-custom'))->toBe('value');
        });
    });

    describe('html()', function () {
        it('returns HtmlResponse', function () {
            $response = html('<h1>Hello</h1>');

            expect($response)->toBeInstanceOf(HtmlResponse::class);
            expect($response->body())->toBe('<h1>Hello</h1>');
        });

        it('accepts status code', function () {
            $response = html('<h1>Not Found</h1>', 404);

            expect($response->status())->toBe(404);
        });

        it('accepts headers', function () {
            $response = html('<html></html>', 200, ['x-custom' => 'value']);

            expect($response->getHeaderLine('x-custom'))->toBe('value');
        });
    });

    describe('redirect()', function () {
        it('returns RedirectResponse', function () {
            $response = redirect('/dashboard');

            expect($response)->toBeInstanceOf(RedirectResponse::class);
            expect($response->getTargetUrl())->toBe('/dashboard');
        });

        it('uses 302 by default', function () {
            $response = redirect('/dashboard');

            expect($response->status())->toBe(302);
        });

        it('accepts custom status', function () {
            $response = redirect('/new-location', 301);

            expect($response->status())->toBe(301);
        });

        it('accepts headers', function () {
            $response = redirect('/dashboard', 302, ['x-custom' => 'value']);

            expect($response->getHeaderLine('x-custom'))->toBe('value');
        });
    });

    describe('file()', function () {
        beforeEach(function () {
            $this->tempFile = sys_get_temp_dir() . '/helper_test_file_' . uniqid() . '.txt';
            file_put_contents($this->tempFile, 'test content');
        });

        afterEach(function () {
            if (file_exists($this->tempFile)) {
                unlink($this->tempFile);
            }
        });

        it('returns FileResponse', function () {
            $response = file($this->tempFile);

            expect($response)->toBeInstanceOf(FileResponse::class);
            expect($response->getPath())->toBe($this->tempFile);
        });

        it('accepts content type', function () {
            $response = file($this->tempFile, 'application/octet-stream');

            expect($response->getHeaderLine('content-type'))->toBe('application/octet-stream');
        });
    });

    describe('download()', function () {
        beforeEach(function () {
            $this->tempFile = sys_get_temp_dir() . '/helper_test_download_' . uniqid() . '.pdf';
            file_put_contents($this->tempFile, 'fake pdf');
        });

        afterEach(function () {
            if (file_exists($this->tempFile)) {
                unlink($this->tempFile);
            }
        });

        it('returns DownloadResponse', function () {
            $response = download($this->tempFile);

            expect($response)->toBeInstanceOf(DownloadResponse::class);
        });

        it('accepts custom filename', function () {
            $response = download($this->tempFile, 'report.pdf');

            expect($response->getFilename())->toBe('report.pdf');
        });

        it('accepts content type', function () {
            $response = download($this->tempFile, null, 'application/pdf');

            expect($response->getHeaderLine('content-type'))->toBe('application/pdf');
        });
    });
});
