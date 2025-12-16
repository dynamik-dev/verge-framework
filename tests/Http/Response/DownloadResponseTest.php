<?php

declare(strict_types=1);

use Verge\Http\Response\DownloadResponse;
use Verge\Http\Response\FileResponse;

describe('DownloadResponse', function () {
    beforeEach(function () {
        $this->tempFile = sys_get_temp_dir() . '/test_download_' . uniqid() . '.pdf';
        file_put_contents($this->tempFile, 'fake pdf content');
    });

    afterEach(function () {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    });

    it('creates download response with Content-Disposition header', function () {
        $response = new DownloadResponse($this->tempFile);

        expect($response->getHeaderLine('content-disposition'))
            ->toContain('attachment');
    });

    it('uses basename as default filename', function () {
        $response = new DownloadResponse($this->tempFile);

        expect($response->getFilename())->toBe(basename($this->tempFile));
        expect($response->getHeaderLine('content-disposition'))
            ->toContain(basename($this->tempFile));
    });

    it('allows custom filename', function () {
        $response = new DownloadResponse($this->tempFile, 'custom-report.pdf');

        expect($response->getFilename())->toBe('custom-report.pdf');
        expect($response->getHeaderLine('content-disposition'))
            ->toContain('custom-report.pdf');
    });

    it('allows explicit content type', function () {
        $response = new DownloadResponse($this->tempFile, null, 'application/pdf');

        expect($response->getHeaderLine('content-type'))->toBe('application/pdf');
    });

    it('allows custom headers', function () {
        $response = new DownloadResponse($this->tempFile, null, null, ['x-custom' => 'value']);

        expect($response->getHeaderLine('x-custom'))->toBe('value');
    });

    it('extends FileResponse', function () {
        $response = new DownloadResponse($this->tempFile);

        expect($response)->toBeInstanceOf(FileResponse::class);
    });

    it('throws for non-existent file', function () {
        expect(fn () => new DownloadResponse('/non/existent/file.pdf'))
            ->toThrow(RuntimeException::class, 'File not found');
    });

    it('has status 200', function () {
        $response = new DownloadResponse($this->tempFile);

        expect($response->status())->toBe(200);
    });

    it('preserves file path', function () {
        $response = new DownloadResponse($this->tempFile);

        expect($response->getPath())->toBe($this->tempFile);
    });
});
