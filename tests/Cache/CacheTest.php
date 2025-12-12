<?php

declare(strict_types=1);

use Verge\App;
use Verge\Cache\Cache;
use Verge\Cache\CacheInterface;
use Verge\Cache\Drivers\MemoryCacheDriver;

describe('MemoryCacheDriver', function () {

    describe('get/set', function () {
        it('stores and retrieves values', function () {
            $cache = new MemoryCacheDriver();

            $cache->set('key', 'value');

            expect($cache->get('key'))->toBe('value');
        });

        it('returns default when key not found', function () {
            $cache = new MemoryCacheDriver();

            expect($cache->get('missing'))->toBeNull();
            expect($cache->get('missing', 'default'))->toBe('default');
        });

        it('stores complex values', function () {
            $cache = new MemoryCacheDriver();
            $data = ['users' => [1, 2, 3], 'meta' => ['total' => 3]];

            $cache->set('data', $data);

            expect($cache->get('data'))->toBe($data);
        });

        it('overwrites existing keys', function () {
            $cache = new MemoryCacheDriver();

            $cache->set('key', 'first');
            $cache->set('key', 'second');

            expect($cache->get('key'))->toBe('second');
        });
    });

    describe('TTL expiration', function () {
        it('expires items after TTL', function () {
            $cache = new MemoryCacheDriver();

            $cache->set('key', 'value', 1);
            expect($cache->get('key'))->toBe('value');

            sleep(2);

            expect($cache->get('key'))->toBeNull();
            expect($cache->has('key'))->toBeFalse();
        });

        it('does not expire items with null TTL', function () {
            $cache = new MemoryCacheDriver();

            $cache->set('key', 'value', null);

            expect($cache->get('key'))->toBe('value');
            expect($cache->has('key'))->toBeTrue();
        });
    });

    describe('has()', function () {
        it('returns true when key exists', function () {
            $cache = new MemoryCacheDriver();

            $cache->set('key', 'value');

            expect($cache->has('key'))->toBeTrue();
        });

        it('returns false when key missing', function () {
            $cache = new MemoryCacheDriver();

            expect($cache->has('missing'))->toBeFalse();
        });

        it('returns false when key expired', function () {
            $cache = new MemoryCacheDriver();

            $cache->set('key', 'value', 1);
            sleep(2);

            expect($cache->has('key'))->toBeFalse();
        });
    });

    describe('delete()', function () {
        it('removes item from cache', function () {
            $cache = new MemoryCacheDriver();

            $cache->set('key', 'value');
            $cache->delete('key');

            expect($cache->has('key'))->toBeFalse();
        });

        it('returns true even when key does not exist', function () {
            $cache = new MemoryCacheDriver();

            expect($cache->delete('missing'))->toBeTrue();
        });
    });

    describe('clear()', function () {
        it('removes all items', function () {
            $cache = new MemoryCacheDriver();

            $cache->set('a', 1);
            $cache->set('b', 2);
            $cache->set('c', 3);
            $cache->clear();

            expect($cache->has('a'))->toBeFalse();
            expect($cache->has('b'))->toBeFalse();
            expect($cache->has('c'))->toBeFalse();
        });
    });

    describe('getMultiple/setMultiple', function () {
        it('retrieves multiple keys', function () {
            $cache = new MemoryCacheDriver();

            $cache->set('a', 1);
            $cache->set('b', 2);

            $result = $cache->getMultiple(['a', 'b', 'c']);

            expect($result)->toBe(['a' => 1, 'b' => 2, 'c' => null]);
        });

        it('sets multiple keys', function () {
            $cache = new MemoryCacheDriver();

            $cache->setMultiple(['a' => 1, 'b' => 2, 'c' => 3]);

            expect($cache->get('a'))->toBe(1);
            expect($cache->get('b'))->toBe(2);
            expect($cache->get('c'))->toBe(3);
        });

        it('sets multiple keys with TTL', function () {
            $cache = new MemoryCacheDriver();

            $cache->setMultiple(['a' => 1, 'b' => 2], 1);
            expect($cache->get('a'))->toBe(1);

            sleep(2);

            expect($cache->get('a'))->toBeNull();
            expect($cache->get('b'))->toBeNull();
        });
    });

    describe('deleteMultiple()', function () {
        it('deletes multiple keys', function () {
            $cache = new MemoryCacheDriver();

            $cache->set('a', 1);
            $cache->set('b', 2);
            $cache->set('c', 3);

            $cache->deleteMultiple(['a', 'b']);

            expect($cache->has('a'))->toBeFalse();
            expect($cache->has('b'))->toBeFalse();
            expect($cache->has('c'))->toBeTrue();
        });
    });

});

describe('Cache Wrapper', function () {

    describe('fluent interface', function () {
        it('chains set calls', function () {
            $cache = new Cache(new MemoryCacheDriver());

            $result = $cache->set('a', 1)->set('b', 2)->set('c', 3);

            expect($result)->toBeInstanceOf(Cache::class);
            expect($cache->get('a'))->toBe(1);
            expect($cache->get('b'))->toBe(2);
            expect($cache->get('c'))->toBe(3);
        });

        it('chains forget calls', function () {
            $cache = new Cache(new MemoryCacheDriver());

            $cache->set('a', 1)->set('b', 2)->forget('a')->forget('b');

            expect($cache->has('a'))->toBeFalse();
            expect($cache->has('b'))->toBeFalse();
        });

        it('chains flush', function () {
            $cache = new Cache(new MemoryCacheDriver());

            $result = $cache->set('a', 1)->flush()->set('b', 2);

            expect($result)->toBeInstanceOf(Cache::class);
            expect($cache->has('a'))->toBeFalse();
            expect($cache->get('b'))->toBe(2);
        });
    });

    describe('remember()', function () {
        it('returns cached value when exists', function () {
            $cache = new Cache(new MemoryCacheDriver());
            $cache->set('key', 'cached');
            $called = false;

            $result = $cache->remember('key', 60, function () use (&$called) {
                $called = true;
                return 'computed';
            });

            expect($result)->toBe('cached');
            expect($called)->toBeFalse();
        });

        it('computes and stores when missing', function () {
            $cache = new Cache(new MemoryCacheDriver());

            $result = $cache->remember('key', 60, fn() => 'computed');

            expect($result)->toBe('computed');
            expect($cache->get('key'))->toBe('computed');
        });
    });

    describe('rememberForever()', function () {
        it('stores without TTL', function () {
            $cache = new Cache(new MemoryCacheDriver());

            $result = $cache->rememberForever('key', fn() => 'value');

            expect($result)->toBe('value');
            expect($cache->get('key'))->toBe('value');
        });
    });

    describe('forever()', function () {
        it('stores value indefinitely', function () {
            $cache = new Cache(new MemoryCacheDriver());

            $cache->forever('key', 'eternal');

            expect($cache->get('key'))->toBe('eternal');
        });
    });

    describe('put()', function () {
        it('is alias for set', function () {
            $cache = new Cache(new MemoryCacheDriver());

            $cache->put('key', 'value');

            expect($cache->get('key'))->toBe('value');
        });
    });

    describe('pull()', function () {
        it('retrieves and deletes value', function () {
            $cache = new Cache(new MemoryCacheDriver());
            $cache->set('key', 'value');

            $result = $cache->pull('key');

            expect($result)->toBe('value');
            expect($cache->has('key'))->toBeFalse();
        });

        it('returns default when missing', function () {
            $cache = new Cache(new MemoryCacheDriver());

            expect($cache->pull('missing', 'default'))->toBe('default');
        });
    });

    describe('missing()', function () {
        it('returns true when key does not exist', function () {
            $cache = new Cache(new MemoryCacheDriver());

            expect($cache->missing('key'))->toBeTrue();
        });

        it('returns false when key exists', function () {
            $cache = new Cache(new MemoryCacheDriver());
            $cache->set('key', 'value');

            expect($cache->missing('key'))->toBeFalse();
        });
    });

    describe('increment/decrement', function () {
        it('increments value', function () {
            $cache = new Cache(new MemoryCacheDriver());
            $cache->set('count', 5);

            expect($cache->increment('count'))->toBe(6);
            expect($cache->increment('count', 4))->toBe(10);
        });

        it('starts from zero if missing', function () {
            $cache = new Cache(new MemoryCacheDriver());

            expect($cache->increment('count'))->toBe(1);
        });

        it('decrements value', function () {
            $cache = new Cache(new MemoryCacheDriver());
            $cache->set('count', 10);

            expect($cache->decrement('count'))->toBe(9);
            expect($cache->decrement('count', 4))->toBe(5);
        });
    });

    describe('many/setMany', function () {
        it('gets multiple values', function () {
            $cache = new Cache(new MemoryCacheDriver());
            $cache->set('a', 1)->set('b', 2);

            $result = $cache->many(['a', 'b', 'c']);

            expect($result)->toBe(['a' => 1, 'b' => 2, 'c' => null]);
        });

        it('sets multiple values fluently', function () {
            $cache = new Cache(new MemoryCacheDriver());

            $result = $cache->setMany(['a' => 1, 'b' => 2]);

            expect($result)->toBeInstanceOf(Cache::class);
            expect($cache->get('a'))->toBe(1);
            expect($cache->get('b'))->toBe(2);
        });
    });

    describe('driver()', function () {
        it('returns underlying driver', function () {
            $driver = new MemoryCacheDriver();
            $cache = new Cache($driver);

            expect($cache->driver())->toBe($driver);
        });
    });

});

describe('App Cache Integration', function () {

    it('auto-wires Cache with default driver', function () {
        $app = new App();

        $cache = $app->container()->resolve(Cache::class);

        expect($cache)->toBeInstanceOf(Cache::class);
        expect($cache->driver())->toBeInstanceOf(MemoryCacheDriver::class);
    });

    it('allows swapping cache driver', function () {
        $app = new App();
        $customDriver = new MemoryCacheDriver();
        $app->container()->singleton(CacheInterface::class, fn() => $customDriver);

        $cache = $app->container()->resolve(Cache::class);

        expect($cache->driver())->toBe($customDriver);
    });

    it('injects cache into handlers', function () {
        $app = new App();

        $app->get('/cached', function (Cache $cache) {
            return $cache->remember('greeting', 60, fn() => 'Hello');
        });

        expect($app->test()->get('/cached')->body())->toBe('Hello');
    });

    it('shares cache singleton across requests', function () {
        $app = new App();

        $app->get('/set', function (Cache $cache) {
            $cache->set('counter', ($cache->get('counter', 0) + 1));
            return (string) $cache->get('counter');
        });

        $app->test()->get('/set');
        $app->test()->get('/set');
        $response = $app->test()->get('/set');

        expect($response->body())->toBe('3');
    });

});
