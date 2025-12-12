<?php

declare(strict_types=1);

use Verge\Concerns\HasHeaders;

function createHeadersInstance(array $headers = []): object
{
    return new class($headers) {
        use HasHeaders;

        protected array $headers = [];

        public function __construct(array $headers = [])
        {
            $this->headers = $this->normalizeHeaders($headers);
        }
    };
}

describe('HasHeaders', function () {

    describe('normalizeHeaders()', function () {
        it('normalizes header names to lowercase', function () {
            $instance = createHeadersInstance(['Content-Type' => 'application/json']);

            expect($instance->getHeaders())->toBe(['content-type' => ['application/json']]);
        });

        it('wraps single values in arrays', function () {
            $instance = createHeadersInstance(['Accept' => 'text/html']);

            expect($instance->getHeaders())->toBe(['accept' => ['text/html']]);
        });

        it('preserves array values', function () {
            $instance = createHeadersInstance(['Accept' => ['text/html', 'application/json']]);

            expect($instance->getHeaders())->toBe(['accept' => ['text/html', 'application/json']]);
        });

        it('handles empty headers', function () {
            $instance = createHeadersInstance([]);

            expect($instance->getHeaders())->toBe([]);
        });
    });

    describe('hasHeader()', function () {
        it('returns true for existing header', function () {
            $instance = createHeadersInstance(['Content-Type' => 'application/json']);

            expect($instance->hasHeader('Content-Type'))->toBeTrue();
            expect($instance->hasHeader('content-type'))->toBeTrue();
        });

        it('returns false for non-existing header', function () {
            $instance = createHeadersInstance(['Content-Type' => 'application/json']);

            expect($instance->hasHeader('Accept'))->toBeFalse();
        });
    });

    describe('getHeader()', function () {
        it('returns header values as array', function () {
            $instance = createHeadersInstance(['Accept' => ['text/html', 'application/json']]);

            expect($instance->getHeader('Accept'))->toBe(['text/html', 'application/json']);
        });

        it('returns empty array for non-existing header', function () {
            $instance = createHeadersInstance([]);

            expect($instance->getHeader('Content-Type'))->toBe([]);
        });

        it('is case-insensitive', function () {
            $instance = createHeadersInstance(['Content-Type' => 'application/json']);

            expect($instance->getHeader('CONTENT-TYPE'))->toBe(['application/json']);
            expect($instance->getHeader('content-type'))->toBe(['application/json']);
        });
    });

    describe('getHeaderLine()', function () {
        it('returns comma-separated values', function () {
            $instance = createHeadersInstance(['Accept' => ['text/html', 'application/json']]);

            expect($instance->getHeaderLine('Accept'))->toBe('text/html, application/json');
        });

        it('returns single value without comma', function () {
            $instance = createHeadersInstance(['Content-Type' => 'application/json']);

            expect($instance->getHeaderLine('Content-Type'))->toBe('application/json');
        });

        it('returns empty string for non-existing header', function () {
            $instance = createHeadersInstance([]);

            expect($instance->getHeaderLine('Accept'))->toBe('');
        });
    });

    describe('withHeader()', function () {
        it('returns new instance with header set', function () {
            $instance = createHeadersInstance([]);
            $new = $instance->withHeader('Content-Type', 'application/json');

            expect($new)->not->toBe($instance);
            expect($new->getHeader('Content-Type'))->toBe(['application/json']);
            expect($instance->getHeader('Content-Type'))->toBe([]);
        });

        it('replaces existing header', function () {
            $instance = createHeadersInstance(['Content-Type' => 'text/html']);
            $new = $instance->withHeader('Content-Type', 'application/json');

            expect($new->getHeader('Content-Type'))->toBe(['application/json']);
        });

        it('accepts array values', function () {
            $instance = createHeadersInstance([]);
            $new = $instance->withHeader('Accept', ['text/html', 'application/json']);

            expect($new->getHeader('Accept'))->toBe(['text/html', 'application/json']);
        });
    });

    describe('withAddedHeader()', function () {
        it('adds to existing header values', function () {
            $instance = createHeadersInstance(['Accept' => 'text/html']);
            $new = $instance->withAddedHeader('Accept', 'application/json');

            expect($new->getHeader('Accept'))->toBe(['text/html', 'application/json']);
        });

        it('creates new header if not existing', function () {
            $instance = createHeadersInstance([]);
            $new = $instance->withAddedHeader('Accept', 'text/html');

            expect($new->getHeader('Accept'))->toBe(['text/html']);
        });

        it('returns new instance', function () {
            $instance = createHeadersInstance([]);
            $new = $instance->withAddedHeader('Accept', 'text/html');

            expect($new)->not->toBe($instance);
        });
    });

    describe('withoutHeader()', function () {
        it('removes existing header', function () {
            $instance = createHeadersInstance(['Content-Type' => 'application/json', 'Accept' => 'text/html']);
            $new = $instance->withoutHeader('Content-Type');

            expect($new->hasHeader('Content-Type'))->toBeFalse();
            expect($new->hasHeader('Accept'))->toBeTrue();
        });

        it('returns new instance', function () {
            $instance = createHeadersInstance(['Content-Type' => 'application/json']);
            $new = $instance->withoutHeader('Content-Type');

            expect($new)->not->toBe($instance);
            expect($instance->hasHeader('Content-Type'))->toBeTrue();
        });

        it('handles non-existing header gracefully', function () {
            $instance = createHeadersInstance([]);
            $new = $instance->withoutHeader('Content-Type');

            expect($new->hasHeader('Content-Type'))->toBeFalse();
        });
    });

});
