<?php

declare(strict_types=1);

use Verge\App;

describe('App Lifecycle', function () {

    describe('app.ready event', function () {
        it('emits app.ready before handling first request', function () {
            $app = new App();
            $readyFired = false;

            $app->on('app.ready', function () use (&$readyFired) {
                $readyFired = true;
            });

            $app->get('/', fn () => 'ok');

            expect($readyFired)->toBeFalse();

            $app->test()->get('/');

            expect($readyFired)->toBeTrue();
        });

        it('only emits app.ready once', function () {
            $app = new App();
            $count = 0;

            $app->on('app.ready', function () use (&$count) {
                $count++;
            });

            $app->get('/', fn () => 'ok');
            $app->test()->get('/');
            $app->test()->get('/');
            $app->test()->get('/');

            expect($count)->toBe(1);
        });

        it('allows modules to register routes on app.ready', function () {
            $app = new App();

            $app->module(function ($app) {
                $app->on('app.ready', function () use ($app) {
                    $app->get('/deferred', fn () => 'deferred route');
                });
            });

            expect($app->test()->get('/deferred')->body())->toBe('deferred route');
        });

        it('allows modules to use services registered by other modules', function () {
            $app = new App();

            // First module registers a service
            $app->module(function ($app) {
                $app->singleton('greeting', fn () => 'Hello from service');
            });

            // Second module uses that service on app.ready
            $app->module(function ($app) {
                $app->on('app.ready', function () use ($app) {
                    $greeting = $app->make('greeting');
                    $app->get('/greeting', fn () => $greeting);
                });
            });

            expect($app->test()->get('/greeting')->body())->toBe('Hello from service');
        });
    });

    describe('isBooted()', function () {
        it('returns false before any request', function () {
            $app = new App();

            expect($app->isBooted())->toBeFalse();
        });

        it('returns true after first request', function () {
            $app = new App();
            $app->get('/', fn () => 'ok');

            $app->test()->get('/');

            expect($app->isBooted())->toBeTrue();
        });
    });

    describe('module() with app.ready pattern', function () {
        it('supports class-based modules', function () {
            $app = new App();
            $app->module(TestLifecycleModule::class);

            expect($app->test()->get('/module-route')->body())->toBe('from module');
        });

        it('supports closure modules', function () {
            $app = new App();

            $app->module(function ($app) {
                $app->singleton('config.value', fn () => 42);
                $app->on('app.ready', function () use ($app) {
                    $app->get('/config', fn () => (string) $app->make('config.value'));
                });
            });

            expect($app->test()->get('/config')->body())->toBe('42');
        });

        it('maintains order of immediate bindings', function () {
            $app = new App();
            $order = [];

            $app->module(function ($app) use (&$order) {
                $order[] = 'module1:bind';
                $app->on('app.ready', function () use (&$order) {
                    $order[] = 'module1:ready';
                });
            });

            $app->module(function ($app) use (&$order) {
                $order[] = 'module2:bind';
                $app->on('app.ready', function () use (&$order) {
                    $order[] = 'module2:ready';
                });
            });

            $app->get('/', fn () => 'ok');
            $app->test()->get('/');

            expect($order)->toBe([
                'module1:bind',
                'module2:bind',
                'module1:ready',
                'module2:ready',
            ]);
        });
    });

});

// Test module class
class TestLifecycleModule
{
    public function __invoke(App $app): void
    {
        $app->on('app.ready', function () use ($app) {
            $app->get('/module-route', fn () => 'from module');
        });
    }
}
