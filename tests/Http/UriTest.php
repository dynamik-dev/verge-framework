<?php

declare(strict_types=1);

use Verge\Http\Uri;

describe('Uri', function () {

    describe('constructor', function () {
        it('creates an empty URI', function () {
            $uri = new Uri();

            expect($uri->getScheme())->toBe('');
            expect($uri->getHost())->toBe('');
            expect($uri->getPath())->toBe('');
            expect((string) $uri)->toBe('');
        });

        it('parses a full URI', function () {
            $uri = new Uri('https://user:pass@example.com:8080/path?query=value#fragment');

            expect($uri->getScheme())->toBe('https');
            expect($uri->getUserInfo())->toBe('user:pass');
            expect($uri->getHost())->toBe('example.com');
            expect($uri->getPort())->toBe(8080);
            expect($uri->getPath())->toBe('/path');
            expect($uri->getQuery())->toBe('query=value');
            expect($uri->getFragment())->toBe('fragment');
        });

        it('parses a simple path', function () {
            $uri = new Uri('/api/users');

            expect($uri->getPath())->toBe('/api/users');
            expect($uri->getScheme())->toBe('');
            expect($uri->getHost())->toBe('');
        });

        it('parses a path with query string', function () {
            $uri = new Uri('/search?q=test&page=1');

            expect($uri->getPath())->toBe('/search');
            expect($uri->getQuery())->toBe('q=test&page=1');
        });

        it('lowercases scheme', function () {
            $uri = new Uri('HTTPS://example.com');

            expect($uri->getScheme())->toBe('https');
        });

        it('lowercases host', function () {
            $uri = new Uri('https://EXAMPLE.COM');

            expect($uri->getHost())->toBe('example.com');
        });

        it('throws on invalid URI', function () {
            expect(fn() => new Uri('http:///invalid'))
                ->toThrow(InvalidArgumentException::class);
        });
    });

    describe('getScheme()', function () {
        it('returns the scheme', function () {
            expect((new Uri('http://example.com'))->getScheme())->toBe('http');
            expect((new Uri('https://example.com'))->getScheme())->toBe('https');
            expect((new Uri('ftp://example.com'))->getScheme())->toBe('ftp');
        });

        it('returns empty string when no scheme', function () {
            expect((new Uri('/path'))->getScheme())->toBe('');
        });
    });

    describe('getAuthority()', function () {
        it('returns host only', function () {
            $uri = new Uri('https://example.com/path');

            expect($uri->getAuthority())->toBe('example.com');
        });

        it('returns host with port', function () {
            $uri = new Uri('https://example.com:8080/path');

            expect($uri->getAuthority())->toBe('example.com:8080');
        });

        it('returns user info with host', function () {
            $uri = new Uri('https://user@example.com/path');

            expect($uri->getAuthority())->toBe('user@example.com');
        });

        it('returns full authority', function () {
            $uri = new Uri('https://user:pass@example.com:8080/path');

            expect($uri->getAuthority())->toBe('user:pass@example.com:8080');
        });

        it('returns empty string for path-only URI', function () {
            $uri = new Uri('/path');

            expect($uri->getAuthority())->toBe('');
        });
    });

    describe('getUserInfo()', function () {
        it('returns user only', function () {
            $uri = new Uri('https://user@example.com');

            expect($uri->getUserInfo())->toBe('user');
        });

        it('returns user and password', function () {
            $uri = new Uri('https://user:pass@example.com');

            expect($uri->getUserInfo())->toBe('user:pass');
        });

        it('returns empty string when no user', function () {
            $uri = new Uri('https://example.com');

            expect($uri->getUserInfo())->toBe('');
        });
    });

    describe('getHost()', function () {
        it('returns the host', function () {
            $uri = new Uri('https://example.com/path');

            expect($uri->getHost())->toBe('example.com');
        });

        it('returns empty string for path-only URI', function () {
            $uri = new Uri('/path');

            expect($uri->getHost())->toBe('');
        });
    });

    describe('getPort()', function () {
        it('returns the port', function () {
            $uri = new Uri('https://example.com:8080');

            expect($uri->getPort())->toBe(8080);
        });

        it('returns null when no port', function () {
            $uri = new Uri('https://example.com');

            expect($uri->getPort())->toBeNull();
        });
    });

    describe('getPath()', function () {
        it('returns the path', function () {
            $uri = new Uri('https://example.com/api/users');

            expect($uri->getPath())->toBe('/api/users');
        });

        it('returns empty string when no path', function () {
            $uri = new Uri('https://example.com');

            expect($uri->getPath())->toBe('');
        });
    });

    describe('getQuery()', function () {
        it('returns the query string', function () {
            $uri = new Uri('https://example.com?foo=bar&baz=qux');

            expect($uri->getQuery())->toBe('foo=bar&baz=qux');
        });

        it('returns empty string when no query', function () {
            $uri = new Uri('https://example.com/path');

            expect($uri->getQuery())->toBe('');
        });
    });

    describe('getFragment()', function () {
        it('returns the fragment', function () {
            $uri = new Uri('https://example.com#section');

            expect($uri->getFragment())->toBe('section');
        });

        it('returns empty string when no fragment', function () {
            $uri = new Uri('https://example.com');

            expect($uri->getFragment())->toBe('');
        });
    });

    describe('withScheme()', function () {
        it('returns new instance with scheme', function () {
            $uri = new Uri('http://example.com');
            $new = $uri->withScheme('https');

            expect($uri->getScheme())->toBe('http');
            expect($new->getScheme())->toBe('https');
        });

        it('lowercases the scheme', function () {
            $uri = new Uri();
            $new = $uri->withScheme('HTTPS');

            expect($new->getScheme())->toBe('https');
        });
    });

    describe('withUserInfo()', function () {
        it('returns new instance with user', function () {
            $uri = new Uri('https://example.com');
            $new = $uri->withUserInfo('admin');

            expect($uri->getUserInfo())->toBe('');
            expect($new->getUserInfo())->toBe('admin');
        });

        it('returns new instance with user and password', function () {
            $uri = new Uri('https://example.com');
            $new = $uri->withUserInfo('admin', 'secret');

            expect($new->getUserInfo())->toBe('admin:secret');
        });
    });

    describe('withHost()', function () {
        it('returns new instance with host', function () {
            $uri = new Uri('https://old.com');
            $new = $uri->withHost('new.com');

            expect($uri->getHost())->toBe('old.com');
            expect($new->getHost())->toBe('new.com');
        });

        it('lowercases the host', function () {
            $uri = new Uri();
            $new = $uri->withHost('EXAMPLE.COM');

            expect($new->getHost())->toBe('example.com');
        });
    });

    describe('withPort()', function () {
        it('returns new instance with port', function () {
            $uri = new Uri('https://example.com');
            $new = $uri->withPort(8080);

            expect($uri->getPort())->toBeNull();
            expect($new->getPort())->toBe(8080);
        });

        it('removes port with null', function () {
            $uri = new Uri('https://example.com:8080');
            $new = $uri->withPort(null);

            expect($uri->getPort())->toBe(8080);
            expect($new->getPort())->toBeNull();
        });
    });

    describe('withPath()', function () {
        it('returns new instance with path', function () {
            $uri = new Uri('https://example.com/old');
            $new = $uri->withPath('/new');

            expect($uri->getPath())->toBe('/old');
            expect($new->getPath())->toBe('/new');
        });
    });

    describe('withQuery()', function () {
        it('returns new instance with query', function () {
            $uri = new Uri('https://example.com');
            $new = $uri->withQuery('foo=bar');

            expect($uri->getQuery())->toBe('');
            expect($new->getQuery())->toBe('foo=bar');
        });

        it('strips leading question mark', function () {
            $uri = new Uri();
            $new = $uri->withQuery('?foo=bar');

            expect($new->getQuery())->toBe('foo=bar');
        });
    });

    describe('withFragment()', function () {
        it('returns new instance with fragment', function () {
            $uri = new Uri('https://example.com');
            $new = $uri->withFragment('section');

            expect($uri->getFragment())->toBe('');
            expect($new->getFragment())->toBe('section');
        });

        it('strips leading hash', function () {
            $uri = new Uri();
            $new = $uri->withFragment('#section');

            expect($new->getFragment())->toBe('section');
        });
    });

    describe('__toString()', function () {
        it('converts to full URI string', function () {
            $uri = new Uri('https://user:pass@example.com:8080/path?query=value#fragment');

            expect((string) $uri)->toBe('https://user:pass@example.com:8080/path?query=value#fragment');
        });

        it('handles simple path', function () {
            $uri = new Uri('/api/users');

            expect((string) $uri)->toBe('/api/users');
        });

        it('handles path with query', function () {
            $uri = new Uri('/search?q=test');

            expect((string) $uri)->toBe('/search?q=test');
        });

        it('handles scheme without authority', function () {
            $uri = (new Uri())->withScheme('file')->withPath('/etc/hosts');

            expect((string) $uri)->toBe('file:///etc/hosts');
        });

        it('handles empty URI', function () {
            $uri = new Uri();

            expect((string) $uri)->toBe('');
        });

        it('builds URI from parts', function () {
            $uri = (new Uri())
                ->withScheme('https')
                ->withHost('example.com')
                ->withPort(8080)
                ->withPath('/api')
                ->withQuery('foo=bar')
                ->withFragment('section');

            expect((string) $uri)->toBe('https://example.com:8080/api?foo=bar#section');
        });
    });

});
