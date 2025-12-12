<?php

declare(strict_types=1);

use Verge\App;
use Verge\Env;
use Verge\Cache\Cache;
use Verge\Cache\CacheInterface;
use Verge\Cache\Drivers\MemoryCacheDriver;
use Verge\Log\Logger;
use Verge\Log\LoggerInterface;
use Verge\Log\Drivers\ArrayLogDriver;
use Verge\Log\Drivers\StreamLogDriver;

function clearDriverEnv(): void {
    $vars = ['CACHE_DRIVER', 'LOG_DRIVER', 'QUEUE_DRIVER', 'CUSTOM_DRIVER', 'CUSTOM_CACHE_URL', 'LOG_PATH', 'LOG_LEVEL'];
    foreach ($vars as $var) {
        putenv($var);
        unset($_ENV[$var]);
    }
}

describe('Driver System', function () {

    beforeEach(function () {
        // Clear env between tests
        clearDriverEnv();
    });

    afterEach(function () {
        // Clean up env after tests
        clearDriverEnv();
    });

    describe('driver registration', function () {

        it('registers a driver with three arguments', function () {
            $app = new App();

            $result = $app->driver('cache', 'custom', fn() => new MemoryCacheDriver());

            expect($result)->toBe($app); // fluent
        });

        it('allows registering multiple drivers for same service', function () {
            $app = new App();

            $app->driver('queue', 'sync', fn() => 'sync-driver');
            $app->driver('queue', 'redis', fn() => 'redis-driver');

            putenv('QUEUE_DRIVER=sync');
            expect($app->driver('queue'))->toBe('sync-driver');

            putenv('QUEUE_DRIVER=redis');
            expect($app->driver('queue'))->toBe('redis-driver');
        });

    });

    describe('driver resolution', function () {

        it('resolves driver based on ENV variable', function () {
            putenv('CACHE_DRIVER=memory');
            $app = new App();

            $driver = $app->driver('cache');

            expect($driver)->toBeInstanceOf(MemoryCacheDriver::class);
        });

        it('throws when ENV variable not set', function () {
            $app = new App();

            // Use a service with no default drivers registered
            expect(fn() => $app->driver('mail'))
                ->toThrow(RuntimeException::class, "No driver configured for 'mail'. Set the MAIL_DRIVER environment variable.");
        });

        it('throws when driver not registered', function () {
            putenv('CACHE_DRIVER=redis');
            $app = new App();

            expect(fn() => $app->driver('cache'))
                ->toThrow(RuntimeException::class, "Unknown cache driver 'redis'");
        });

        it('lists available drivers in error message', function () {
            putenv('CACHE_DRIVER=invalid');
            $app = new App();
            $app->driver('cache', 'memory', fn() => new MemoryCacheDriver());
            $app->driver('cache', 'file', fn() => 'file-driver');

            expect(fn() => $app->driver('cache'))
                ->toThrow(RuntimeException::class, 'Available drivers: memory, file');
        });

        it('passes App instance to factory', function () {
            putenv('CUSTOM_DRIVER=test');
            $app = new App();
            $receivedApp = null;

            $app->driver('custom', 'test', function (App $a) use (&$receivedApp) {
                $receivedApp = $a;
                return 'test-driver';
            });

            $app->driver('custom');

            expect($receivedApp)->toBe($app);
        });

    });

    describe('invalid arguments', function () {

        it('throws with two arguments', function () {
            $app = new App();

            expect(fn() => $app->driver('cache', 'memory'))
                ->toThrow(InvalidArgumentException::class);
        });

    });

    describe('default cache drivers', function () {

        it('ships with memory driver', function () {
            putenv('CACHE_DRIVER=memory');
            $app = new App();

            $driver = $app->driver('cache');

            expect($driver)->toBeInstanceOf(MemoryCacheDriver::class);
        });

        it('wires CacheInterface to driver system', function () {
            putenv('CACHE_DRIVER=memory');
            $app = new App();

            $cache = $app->make(CacheInterface::class);

            expect($cache)->toBeInstanceOf(MemoryCacheDriver::class);
        });

        it('Cache wrapper uses configured driver', function () {
            putenv('CACHE_DRIVER=memory');
            $app = new App();

            $cache = $app->make(Cache::class);

            expect($cache->driver())->toBeInstanceOf(MemoryCacheDriver::class);
        });

    });

    describe('default log drivers', function () {

        it('ships with stream driver', function () {
            putenv('LOG_DRIVER=stream');
            $app = new App();

            $driver = $app->driver('log');

            expect($driver)->toBeInstanceOf(StreamLogDriver::class);
        });

        it('ships with array driver', function () {
            putenv('LOG_DRIVER=array');
            $app = new App();

            $driver = $app->driver('log');

            expect($driver)->toBeInstanceOf(ArrayLogDriver::class);
        });

        it('wires LoggerInterface to driver system', function () {
            putenv('LOG_DRIVER=array');
            $app = new App();

            $logger = $app->make(LoggerInterface::class);

            expect($logger)->toBeInstanceOf(ArrayLogDriver::class);
        });

    });

    describe('custom drivers', function () {

        it('user can register custom cache driver', function () {
            putenv('CACHE_DRIVER=turso');
            $app = new App();

            $customDriver = new MemoryCacheDriver();
            $app->driver('cache', 'turso', fn() => $customDriver);

            expect($app->driver('cache'))->toBe($customDriver);
        });

        it('user can override default driver', function () {
            putenv('CACHE_DRIVER=memory');
            $app = new App();

            $customDriver = new MemoryCacheDriver();
            $app->driver('cache', 'memory', fn() => $customDriver);

            expect($app->driver('cache'))->toBe($customDriver);
        });

        it('custom driver can read ENV variables', function () {
            putenv('CACHE_DRIVER=custom');
            putenv('CUSTOM_CACHE_URL=redis://localhost');
            $app = new App();

            $receivedUrl = null;
            $app->driver('cache', 'custom', function (App $app) use (&$receivedUrl) {
                $receivedUrl = $app->env('CUSTOM_CACHE_URL');
                return new MemoryCacheDriver();
            });

            $app->driver('cache');

            expect($receivedUrl)->toBe('redis://localhost');
        });

    });

    describe('phpunit.xml integration', function () {

        it('resolves driver from $_ENV', function () {
            $_ENV['CACHE_DRIVER'] = 'memory';
            $app = new App();

            $driver = $app->driver('cache');

            expect($driver)->toBeInstanceOf(MemoryCacheDriver::class);
        });

    });

    describe('handler injection', function () {

        it('injects Cache with configured driver into handlers', function () {
            putenv('CACHE_DRIVER=memory');
            $app = new App();

            $app->get('/test', function (Cache $cache) {
                $cache->set('key', 'value');
                return $cache->get('key');
            });

            $response = $app->test()->get('/test');

            expect($response->body())->toBe('value');
        });

        it('injects Logger with configured driver into handlers', function () {
            putenv('LOG_DRIVER=array');
            $app = new App();

            $app->get('/test', function (Logger $logger) {
                $logger->info('test message');
                return 'logged';
            });

            $response = $app->test()->get('/test');

            expect($response->body())->toBe('logged');

            // Clean up immediately
            putenv('LOG_DRIVER');
        });

    });

});
