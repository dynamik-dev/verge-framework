<?php

declare(strict_types=1);

use Verge\Http\StringStream;
use Verge\Http\UploadedFile;

describe('UploadedFile', function () {

    describe('constructor', function () {
        it('creates from file array', function () {
            $file = new UploadedFile([
                'name' => 'photo.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/phpXXXXXX',
                'error' => UPLOAD_ERR_OK,
                'size' => 1024,
            ]);

            expect($file->getClientFilename())->toBe('photo.jpg');
            expect($file->getClientMediaType())->toBe('image/jpeg');
            expect($file->getSize())->toBe(1024);
            expect($file->getError())->toBe(UPLOAD_ERR_OK);
        });

        it('handles missing values', function () {
            $file = new UploadedFile([]);

            expect($file->getClientFilename())->toBeNull();
            expect($file->getClientMediaType())->toBeNull();
            expect($file->getSize())->toBeNull();
            expect($file->getError())->toBe(UPLOAD_ERR_OK);
        });
    });

    describe('PSR-7 methods', function () {

        describe('getStream()', function () {
            it('returns stream with file contents', function () {
                $tmpFile = tempnam(sys_get_temp_dir(), 'test');
                file_put_contents($tmpFile, 'file content');

                $file = new UploadedFile([
                    'tmp_name' => $tmpFile,
                    'error' => UPLOAD_ERR_OK,
                ]);

                $stream = $file->getStream();

                expect($stream)->toBeInstanceOf(StringStream::class);
                expect((string) $stream)->toBe('file content');

                unlink($tmpFile);
            });

            it('throws when no tmp file', function () {
                $file = new UploadedFile([]);

                expect(fn() => $file->getStream())
                    ->toThrow(RuntimeException::class, 'No temporary file available');
            });

            it('throws when already moved', function () {
                $tmpFile = tempnam(sys_get_temp_dir(), 'test');
                file_put_contents($tmpFile, 'content');
                $targetDir = sys_get_temp_dir() . '/upload_test_' . uniqid();
                mkdir($targetDir);

                $file = new UploadedFile([
                    'tmp_name' => $tmpFile,
                    'error' => UPLOAD_ERR_OK,
                ]);

                $file->moveTo($targetDir . '/moved.txt');

                expect(fn() => $file->getStream())
                    ->toThrow(RuntimeException::class, 'already been moved');

                // Cleanup
                unlink($targetDir . '/moved.txt');
                rmdir($targetDir);
            });
        });

        describe('moveTo()', function () {
            it('moves file to target path', function () {
                $tmpFile = tempnam(sys_get_temp_dir(), 'test');
                file_put_contents($tmpFile, 'test content');
                $targetDir = sys_get_temp_dir() . '/upload_test_' . uniqid();

                $file = new UploadedFile([
                    'tmp_name' => $tmpFile,
                    'error' => UPLOAD_ERR_OK,
                ]);

                $targetPath = $targetDir . '/uploaded.txt';
                $file->moveTo($targetPath);

                expect(file_exists($targetPath))->toBeTrue();
                expect(file_get_contents($targetPath))->toBe('test content');

                // Cleanup
                unlink($targetPath);
                rmdir($targetDir);
            });

            it('creates target directory if needed', function () {
                $tmpFile = tempnam(sys_get_temp_dir(), 'test');
                file_put_contents($tmpFile, 'content');
                $targetDir = sys_get_temp_dir() . '/nested/upload_test_' . uniqid();

                $file = new UploadedFile([
                    'tmp_name' => $tmpFile,
                    'error' => UPLOAD_ERR_OK,
                ]);

                $targetPath = $targetDir . '/file.txt';
                $file->moveTo($targetPath);

                expect(file_exists($targetPath))->toBeTrue();

                // Cleanup
                unlink($targetPath);
                rmdir($targetDir);
                rmdir(dirname($targetDir));
            });

            it('throws when already moved', function () {
                $tmpFile = tempnam(sys_get_temp_dir(), 'test');
                file_put_contents($tmpFile, 'content');
                $targetDir = sys_get_temp_dir() . '/upload_test_' . uniqid();
                mkdir($targetDir);

                $file = new UploadedFile([
                    'tmp_name' => $tmpFile,
                    'error' => UPLOAD_ERR_OK,
                ]);

                $file->moveTo($targetDir . '/first.txt');

                expect(fn() => $file->moveTo($targetDir . '/second.txt'))
                    ->toThrow(RuntimeException::class, 'already been moved');

                // Cleanup
                unlink($targetDir . '/first.txt');
                rmdir($targetDir);
            });

            it('throws on upload error', function () {
                $file = new UploadedFile([
                    'tmp_name' => '/tmp/test',
                    'error' => UPLOAD_ERR_INI_SIZE,
                ]);

                expect(fn() => $file->moveTo('/tmp/target.txt'))
                    ->toThrow(RuntimeException::class, 'upload error');
            });

            it('throws when no tmp file', function () {
                $file = new UploadedFile([
                    'error' => UPLOAD_ERR_OK,
                ]);

                expect(fn() => $file->moveTo('/tmp/target.txt'))
                    ->toThrow(RuntimeException::class, 'No temporary file available');
            });
        });

        describe('getSize()', function () {
            it('returns file size', function () {
                $file = new UploadedFile(['size' => 2048]);

                expect($file->getSize())->toBe(2048);
            });

            it('returns null when not set', function () {
                $file = new UploadedFile([]);

                expect($file->getSize())->toBeNull();
            });
        });

        describe('getError()', function () {
            it('returns error code', function () {
                $file = new UploadedFile(['error' => UPLOAD_ERR_INI_SIZE]);

                expect($file->getError())->toBe(UPLOAD_ERR_INI_SIZE);
            });

            it('returns UPLOAD_ERR_OK by default', function () {
                $file = new UploadedFile([]);

                expect($file->getError())->toBe(UPLOAD_ERR_OK);
            });
        });

        describe('getClientFilename()', function () {
            it('returns client filename', function () {
                $file = new UploadedFile(['name' => 'document.pdf']);

                expect($file->getClientFilename())->toBe('document.pdf');
            });

            it('returns null when not set', function () {
                $file = new UploadedFile([]);

                expect($file->getClientFilename())->toBeNull();
            });
        });

        describe('getClientMediaType()', function () {
            it('returns media type', function () {
                $file = new UploadedFile(['type' => 'application/pdf']);

                expect($file->getClientMediaType())->toBe('application/pdf');
            });

            it('returns null when not set', function () {
                $file = new UploadedFile([]);

                expect($file->getClientMediaType())->toBeNull();
            });
        });
    });

    describe('Edge API methods', function () {

        describe('name()', function () {
            it('returns client filename', function () {
                $file = new UploadedFile(['name' => 'photo.jpg']);

                expect($file->name())->toBe('photo.jpg');
            });
        });

        describe('type()', function () {
            it('returns media type', function () {
                $file = new UploadedFile(['type' => 'image/png']);

                expect($file->type())->toBe('image/png');
            });
        });

        describe('size()', function () {
            it('returns file size', function () {
                $file = new UploadedFile(['size' => 4096]);

                expect($file->size())->toBe(4096);
            });
        });

        describe('path()', function () {
            it('returns tmp path', function () {
                $file = new UploadedFile(['tmp_name' => '/tmp/phpABCDEF']);

                expect($file->path())->toBe('/tmp/phpABCDEF');
            });

            it('returns null when not set', function () {
                $file = new UploadedFile([]);

                expect($file->path())->toBeNull();
            });
        });
    });

});
