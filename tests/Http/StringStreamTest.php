<?php

declare(strict_types=1);

use Verge\Http\StringStream;

describe('StringStream', function () {

    describe('constructor', function () {
        it('creates an empty stream', function () {
            $stream = new StringStream();

            expect((string) $stream)->toBe('');
            expect($stream->getSize())->toBe(0);
        });

        it('creates a stream with content', function () {
            $stream = new StringStream('Hello World');

            expect((string) $stream)->toBe('Hello World');
            expect($stream->getSize())->toBe(11);
        });
    });

    describe('__toString()', function () {
        it('returns the full content', function () {
            $stream = new StringStream('test content');

            expect((string) $stream)->toBe('test content');
        });

        it('returns content regardless of position', function () {
            $stream = new StringStream('test content');
            $stream->seek(5);

            expect((string) $stream)->toBe('test content');
        });
    });

    describe('close()', function () {
        it('clears content and resets position', function () {
            $stream = new StringStream('content');
            $stream->seek(3);
            $stream->close();

            expect((string) $stream)->toBe('');
            expect($stream->tell())->toBe(0);
        });
    });

    describe('detach()', function () {
        it('returns null and clears stream', function () {
            $stream = new StringStream('content');

            expect($stream->detach())->toBeNull();
            expect((string) $stream)->toBe('');
        });
    });

    describe('getSize()', function () {
        it('returns content length', function () {
            expect((new StringStream(''))->getSize())->toBe(0);
            expect((new StringStream('hello'))->getSize())->toBe(5);
            expect((new StringStream('hello world'))->getSize())->toBe(11);
        });
    });

    describe('tell()', function () {
        it('returns current position', function () {
            $stream = new StringStream('hello');

            expect($stream->tell())->toBe(0);

            $stream->read(2);
            expect($stream->tell())->toBe(2);

            $stream->read(3);
            expect($stream->tell())->toBe(5);
        });
    });

    describe('eof()', function () {
        it('returns false at start', function () {
            $stream = new StringStream('hello');

            expect($stream->eof())->toBeFalse();
        });

        it('returns true at end', function () {
            $stream = new StringStream('hello');
            $stream->read(5);

            expect($stream->eof())->toBeTrue();
        });

        it('returns true for empty stream', function () {
            $stream = new StringStream('');

            expect($stream->eof())->toBeTrue();
        });
    });

    describe('isSeekable()', function () {
        it('returns true', function () {
            $stream = new StringStream();

            expect($stream->isSeekable())->toBeTrue();
        });
    });

    describe('seek()', function () {
        it('seeks with SEEK_SET', function () {
            $stream = new StringStream('hello world');
            $stream->seek(6, SEEK_SET);

            expect($stream->tell())->toBe(6);
            expect($stream->read(5))->toBe('world');
        });

        it('seeks with SEEK_CUR', function () {
            $stream = new StringStream('hello world');
            $stream->seek(3, SEEK_SET);
            $stream->seek(3, SEEK_CUR);

            expect($stream->tell())->toBe(6);
        });

        it('seeks with SEEK_END', function () {
            $stream = new StringStream('hello world');
            $stream->seek(-5, SEEK_END);

            expect($stream->tell())->toBe(6);
            expect($stream->read(5))->toBe('world');
        });

        it('clamps position to bounds', function () {
            $stream = new StringStream('hello');

            $stream->seek(-10, SEEK_SET);
            expect($stream->tell())->toBe(0);

            $stream->seek(100, SEEK_SET);
            expect($stream->tell())->toBe(5);
        });
    });

    describe('rewind()', function () {
        it('resets position to start', function () {
            $stream = new StringStream('hello');
            $stream->read(3);

            expect($stream->tell())->toBe(3);

            $stream->rewind();
            expect($stream->tell())->toBe(0);
        });
    });

    describe('isWritable()', function () {
        it('returns true', function () {
            $stream = new StringStream();

            expect($stream->isWritable())->toBeTrue();
        });
    });

    describe('write()', function () {
        it('writes to empty stream', function () {
            $stream = new StringStream();
            $length = $stream->write('hello');

            expect($length)->toBe(5);
            expect((string) $stream)->toBe('hello');
        });

        it('writes at current position', function () {
            $stream = new StringStream('hello world');
            $stream->seek(6);
            $stream->write('there');

            expect((string) $stream)->toBe('hello there');
        });

        it('overwrites content', function () {
            $stream = new StringStream('hello');
            $stream->seek(0);
            $stream->write('hi');

            expect((string) $stream)->toBe('hillo');
        });

        it('advances position after write', function () {
            $stream = new StringStream();
            $stream->write('hello');

            expect($stream->tell())->toBe(5);
        });

        it('returns bytes written', function () {
            $stream = new StringStream();

            expect($stream->write('hello'))->toBe(5);
            expect($stream->write(' world'))->toBe(6);
        });
    });

    describe('isReadable()', function () {
        it('returns true', function () {
            $stream = new StringStream();

            expect($stream->isReadable())->toBeTrue();
        });
    });

    describe('read()', function () {
        it('reads specified length', function () {
            $stream = new StringStream('hello world');

            expect($stream->read(5))->toBe('hello');
            expect($stream->read(1))->toBe(' ');
            expect($stream->read(5))->toBe('world');
        });

        it('advances position', function () {
            $stream = new StringStream('hello');
            $stream->read(3);

            expect($stream->tell())->toBe(3);
        });

        it('returns less than requested at end', function () {
            $stream = new StringStream('hi');

            expect($stream->read(10))->toBe('hi');
        });

        it('returns empty string when at end', function () {
            $stream = new StringStream('hi');
            $stream->read(2);

            expect($stream->read(5))->toBe('');
        });
    });

    describe('getContents()', function () {
        it('returns remaining content', function () {
            $stream = new StringStream('hello world');
            $stream->seek(6);

            expect($stream->getContents())->toBe('world');
        });

        it('returns all content from start', function () {
            $stream = new StringStream('hello world');

            expect($stream->getContents())->toBe('hello world');
        });

        it('moves position to end', function () {
            $stream = new StringStream('hello');
            $stream->getContents();

            expect($stream->eof())->toBeTrue();
        });

        it('returns empty string when at end', function () {
            $stream = new StringStream('hello');
            $stream->getContents();

            expect($stream->getContents())->toBe('');
        });
    });

    describe('getMetadata()', function () {
        it('returns empty array with no key', function () {
            $stream = new StringStream();

            expect($stream->getMetadata())->toBe([]);
        });

        it('returns null with key', function () {
            $stream = new StringStream();

            expect($stream->getMetadata('mode'))->toBeNull();
            expect($stream->getMetadata('uri'))->toBeNull();
        });
    });

});
