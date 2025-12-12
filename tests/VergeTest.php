<?php

declare(strict_types=1);

use Verge\App;
use Verge\Verge;
use Verge\Routing\RouterInterface;
use Verge\Env;

beforeEach(function () {
    Verge::reset();
});

describe('Verge', function () {

    describe('build()', function () {
        it('returns an App instance', function () {
            $app = Verge::build();

            expect($app)->toBeInstanceOf(App::class);
        });

        it('stores the app instance', function () {
            $app = Verge::build();

            expect(Verge::app())->toBe($app);
        });

        it('accepts a callback for container configuration', function () {
            $app = Verge::build(fn($c) => $c
                ->defaults()
                ->bind('custom', fn() => 'value')
            );

            expect($app->make('custom'))->toBe('value');
        });
    });

    describe('buildDefaults()', function () {
        it('returns an App with defaults bound', function () {
            $app = Verge::buildDefaults();

            expect($app->has(RouterInterface::class))->toBeTrue();
            expect($app->has(Env::class))->toBeTrue();
        });

        it('stores the app instance', function () {
            $app = Verge::buildDefaults();

            expect(Verge::app())->toBe($app);
        });
    });

    describe('make()', function () {
        it('resolves from the app container', function () {
            Verge::buildDefaults()->bind('service', fn() => 'resolved');

            expect(Verge::make('service'))->toBe('resolved');
        });

        it('throws when no app exists', function () {
            expect(fn() => Verge::make('anything'))
                ->toThrow(RuntimeException::class, 'No application instance');
        });
    });

    describe('has()', function () {
        it('checks if binding exists', function () {
            Verge::buildDefaults();

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
            Verge::buildDefaults();

            expect(Verge::env('TEST_VAR'))->toBe('test_value');

            unset($_ENV['TEST_VAR']);
        });

        it('throws when no app exists', function () {
            expect(fn() => Verge::env('anything'))
                ->toThrow(RuntimeException::class, 'No application instance');
        });
    });

    describe('app()', function () {
        it('returns null when no app built', function () {
            expect(Verge::app())->toBeNull();
        });

        it('returns the app instance after build', function () {
            $app = Verge::buildDefaults();

            expect(Verge::app())->toBe($app);
        });
    });

    describe('reset()', function () {
        it('clears the app instance', function () {
            Verge::buildDefaults();
            expect(Verge::app())->not->toBeNull();

            Verge::reset();
            expect(Verge::app())->toBeNull();
        });
    });

    describe('routes()', function () {
        it('returns a RoutesBuilder', function () {
            Verge::buildDefaults();

            $builder = Verge::routes(function ($r) {
                $r->get('/test', fn() => 'test');
            });

            expect($builder)->toBeInstanceOf(\Verge\Routing\RoutesBuilder::class);
        });

        it('adds routes to the app router', function () {
            Verge::buildDefaults();

            Verge::routes(function ($r) {
                $r->get('/hello', fn() => 'world');
            });

            $response = Verge::app()->test()->get('/hello');
            expect($response->body())->toBe('world');
        });

        it('allows chaining middleware on route group', function () {
            Verge::buildDefaults();

            Verge::routes(function ($r) {
                $r->get('/with-header', fn() => 'ok');
            })->use(fn($req, $next) => $next($req)->header('X-Group', 'applied'));

            $response = Verge::app()->test()->get('/with-header');
            expect($response->getHeaderLine('X-Group'))->toBe('applied');
        });

        it('allows multiple route blocks', function () {
            Verge::buildDefaults();

            Verge::routes(function ($r) {
                $r->get('/one', fn() => 'one');
            });

            Verge::routes(function ($r) {
                $r->get('/two', fn() => 'two');
            });

            expect(Verge::app()->test()->get('/one')->body())->toBe('one');
            expect(Verge::app()->test()->get('/two')->body())->toBe('two');
        });

        it('throws when no app exists', function () {
            expect(fn() => Verge::routes(fn($r) => $r->get('/', fn() => 'test')))
                ->toThrow(RuntimeException::class, 'No application instance');
        });
    });

});
