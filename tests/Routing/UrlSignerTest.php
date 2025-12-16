<?php

declare(strict_types=1);

use Verge\App;
use Verge\Http\Request;
use Verge\Routing\UrlSigner;
use Verge\Verge;

use function Verge\signed_route;

describe('UrlSigner', function () {
    describe('sign()', function () {
        it('adds a signature parameter to URL', function () {
            $signer = new UrlSigner('my-secret-key');

            $signed = $signer->sign('https://example.com/unsubscribe');

            expect($signed)->toContain('signature=');
            expect($signed)->toStartWith('https://example.com/unsubscribe?');
        });

        it('preserves existing query parameters', function () {
            $signer = new UrlSigner('my-secret-key');

            $signed = $signer->sign('https://example.com/unsubscribe?user=123');

            expect($signed)->toContain('user=123');
            expect($signed)->toContain('signature=');
        });

        it('adds expiration when provided', function () {
            $signer = new UrlSigner('my-secret-key');
            $expires = time() + 3600;

            $signed = $signer->sign('https://example.com/unsubscribe', $expires);

            expect($signed)->toContain("expires={$expires}");
            expect($signed)->toContain('signature=');
        });

        it('throws for empty key', function () {
            expect(fn () => new UrlSigner(''))
                ->toThrow(RuntimeException::class, 'APP_KEY cannot be empty');
        });
    });

    describe('verify()', function () {
        it('returns true for valid signature', function () {
            $signer = new UrlSigner('my-secret-key');

            $signed = $signer->sign('https://example.com/unsubscribe');

            expect($signer->verify($signed))->toBeTrue();
        });

        it('returns false for tampered URL', function () {
            $signer = new UrlSigner('my-secret-key');

            $signed = $signer->sign('https://example.com/unsubscribe?user=123');
            $tampered = str_replace('user=123', 'user=456', $signed);

            expect($signer->verify($tampered))->toBeFalse();
        });

        it('returns false for missing signature', function () {
            $signer = new UrlSigner('my-secret-key');

            expect($signer->verify('https://example.com/unsubscribe'))->toBeFalse();
        });

        it('returns false for wrong key', function () {
            $signer1 = new UrlSigner('key-one');
            $signer2 = new UrlSigner('key-two');

            $signed = $signer1->sign('https://example.com/unsubscribe');

            expect($signer2->verify($signed))->toBeFalse();
        });

        it('returns false for expired signature', function () {
            $signer = new UrlSigner('my-secret-key');
            $expired = time() - 3600; // 1 hour ago

            $signed = $signer->sign('https://example.com/unsubscribe', $expired);

            expect($signer->verify($signed))->toBeFalse();
        });

        it('returns true for non-expired signature', function () {
            $signer = new UrlSigner('my-secret-key');
            $future = time() + 3600; // 1 hour from now

            $signed = $signer->sign('https://example.com/unsubscribe', $future);

            expect($signer->verify($signed))->toBeTrue();
        });

        it('handles URLs with fragments', function () {
            $signer = new UrlSigner('my-secret-key');

            $signed = $signer->sign('https://example.com/page#section');

            expect($signer->verify($signed))->toBeTrue();
        });

        it('handles URLs with ports', function () {
            $signer = new UrlSigner('my-secret-key');

            $signed = $signer->sign('https://example.com:8080/path');

            expect($signer->verify($signed))->toBeTrue();
        });
    });
});

describe('Request::hasValidSignature()', function () {
    beforeEach(fn () => Verge::reset());

    it('returns true for valid signed request', function () {
        $app = Verge::create();
        $app->config(['app.key' => 'test-secret-key']);

        $signer = new UrlSigner('test-secret-key');
        $signedUrl = $signer->sign('https://example.com/verify?token=abc');

        $request = new Request('GET', $signedUrl);

        expect($request->hasValidSignature($signer))->toBeTrue();
    });

    it('returns false for unsigned request', function () {
        $signer = new UrlSigner('test-secret-key');
        $request = new Request('GET', 'https://example.com/verify?token=abc');

        expect($request->hasValidSignature($signer))->toBeFalse();
    });

    it('returns false for tampered request', function () {
        $signer = new UrlSigner('test-secret-key');
        $signedUrl = $signer->sign('https://example.com/verify?token=abc');
        $tampered = str_replace('token=abc', 'token=xyz', $signedUrl);

        $request = new Request('GET', $tampered);

        expect($request->hasValidSignature($signer))->toBeFalse();
    });
});

describe('signed_route() helper', function () {
    beforeEach(fn () => Verge::reset());

    it('generates signed URL for named route', function () {
        $app = Verge::create();
        $app->config(['app.key' => 'test-app-key']);
        $app->get('/unsubscribe/{id}', fn () => null, [], 'unsubscribe');

        $signed = signed_route('unsubscribe', ['id' => 123]);

        expect($signed)->toContain('/unsubscribe/123');
        expect($signed)->toContain('signature=');

        // Verify it's actually valid
        $signer = new UrlSigner('test-app-key');
        expect($signer->verify($signed))->toBeTrue();
    });

    it('generates signed URL with expiration', function () {
        $app = Verge::create();
        $app->config(['app.key' => 'test-app-key']);
        $app->get('/download/{file}', fn () => null, [], 'download');

        $expires = time() + 3600;
        $signed = signed_route('download', ['file' => 'doc.pdf'], $expires);

        expect($signed)->toContain("expires={$expires}");
        expect($signed)->toContain('signature=');
    });

    it('throws when APP_KEY not configured', function () {
        $app = Verge::create();
        $app->get('/test', fn () => null, [], 'test');

        expect(fn () => signed_route('test'))
            ->toThrow(RuntimeException::class, 'APP_KEY must be set');
    });
});

describe('Request::fullUrl()', function () {
    it('builds full URL with scheme and host', function () {
        $request = new Request('GET', 'https://example.com/path?foo=bar');

        expect($request->fullUrl())->toBe('https://example.com/path?foo=bar');
    });

    it('handles URL with port', function () {
        $request = new Request('GET', 'https://example.com:8080/path');

        expect($request->fullUrl())->toBe('https://example.com:8080/path');
    });

    it('handles URL without query', function () {
        $request = new Request('GET', 'https://example.com/path');

        expect($request->fullUrl())->toBe('https://example.com/path');
    });

    it('defaults to / for empty path', function () {
        $request = new Request('GET', 'https://example.com');

        expect($request->fullUrl())->toContain('/');
    });
});
