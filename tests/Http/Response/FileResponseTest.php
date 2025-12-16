<?php

declare(strict_types=1);

use Verge\Http\Response;
use Verge\Http\Response\FileResponse;
use Verge\Http\Response\StreamResponse;

describe('FileResponse', function () {
    beforeEach(function () {
        $this->tempFile = sys_get_temp_dir() . '/test_file_' . uniqid() . '.txt';
        file_put_contents($this->tempFile, 'test file content');
    });

    afterEach(function () {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    });

    it('creates response from file path', function () {
        $response = new FileResponse($this->tempFile);

        expect($response->status())->toBe(200);
        expect($response->getPath())->toBe($this->tempFile);
        expect($response->getResource())->toBeResource();
    });

    it('auto-detects content type', function () {
        $response = new FileResponse($this->tempFile);

        // text/plain is common for .txt files
        expect($response->getHeaderLine('content-type'))->toContain('text/');
    });

    it('allows explicit content type', function () {
        $response = new FileResponse($this->tempFile, 'application/octet-stream');

        expect($response->getHeaderLine('content-type'))->toBe('application/octet-stream');
    });

    it('sets content-length header', function () {
        $response = new FileResponse($this->tempFile);

        expect($response->getHeaderLine('content-length'))->toBe((string) strlen('test file content'));
    });

    it('allows custom status code', function () {
        $response = new FileResponse($this->tempFile, null, 206);

        expect($response->status())->toBe(206);
    });

    it('allows custom headers', function () {
        $response = new FileResponse($this->tempFile, null, 200, ['x-custom' => 'value']);

        expect($response->getHeaderLine('x-custom'))->toBe('value');
    });

    it('throws for non-existent file', function () {
        expect(fn () => new FileResponse('/non/existent/file.txt'))
            ->toThrow(RuntimeException::class, 'File not found');
    });

    it('extends StreamResponse', function () {
        $response = new FileResponse($this->tempFile);

        expect($response)->toBeInstanceOf(StreamResponse::class);
    });
});

describe('FileResponse with different file types', function () {
    it('handles binary files', function () {
        $tempFile = sys_get_temp_dir() . '/test_binary_' . uniqid() . '.bin';
        file_put_contents($tempFile, random_bytes(100));

        $response = new FileResponse($tempFile, 'application/octet-stream');

        expect($response->getHeaderLine('content-length'))->toBe('100');

        unlink($tempFile);
    });
});
