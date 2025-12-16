<?php

declare(strict_types=1);

use Verge\App;
use Verge\Verge;
use Verge\Routing\RouterInterface;
use Verge\Env\Env;

beforeEach(function () {
    Verge::reset();
});

describe('Verge', function () {

    describe('create()', function () {
        it('returns an App instance', function () {
            $app = Verge::create();

            expect($app)->toBeInstanceOf(App::class);
        });

        it('stores the app instance', function () {
            $app = Verge::create();

            expect(Verge::app())->toBe($app);
        });

        it('accepts a callback for app configuration', function () {
            $app = Verge::create(fn ($app) => $app->bind('custom', fn () => 'value'));

            expect($app->make('custom'))->toBe('value');
        });

        it('has core services bound', function () {
            $app = Verge::create();

            expect($app->has(RouterInterface::class))->toBeTrue();
            expect($app->has(Env::class))->toBeTrue();
        });
    });

    describe('make()', function () {
        it('resolves from the app container', function () {
            Verge::create()->bind('service', fn () => 'resolved');

            expect(Verge::make('service'))->toBe('resolved');
        });

        it('throws when no app exists', function () {
            expect(fn () => Verge::make('anything'))
                ->toThrow(RuntimeException::class, 'No application instance');
        });
    });

    describe('has()', function () {
        it('checks if binding exists', function () {
            Verge::create();

            expect(Verge::has(RouterInterface::class))->toBeTrue();
            expect(Verge::has('nonexistent'))->toBeFalse();
        });

        it('returns false when no app exists', function () {
            expect(Verge::has('anything'))->toBeFalse();
        });
    });

    describe('env()', function () {
        it('gets environment variable', function () {
            $_ENV['TEST_VAR'] = 'test_value';
            Verge::create();

            expect(Verge::env('TEST_VAR'))->toBe('test_value');

            unset($_ENV['TEST_VAR']);
        });

        it('throws when no app exists', function () {
            expect(fn () => Verge::env('anything'))
                ->toThrow(RuntimeException::class, 'No application instance');
        });
    });

    describe('app()', function () {
        it('returns null when no app created', function () {
            expect(Verge::app())->toBeNull();
        });

        it('returns the app instance after create', function () {
            $app = Verge::create();

            expect(Verge::app())->toBe($app);
        });
    });

    describe('reset()', function () {
        it('clears the app instance', function () {
            Verge::create();
            expect(Verge::app())->not->toBeNull();

            Verge::reset();
            expect(Verge::app())->toBeNull();
        });
    });

});
