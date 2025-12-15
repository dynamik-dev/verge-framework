<?php

declare(strict_types=1);

use Verge\Concerns\HasMiddleware;

function createMiddlewareInstance(): object
{
    return new class () {
        use HasMiddleware;
    };
}

describe('HasMiddleware', function () {

    describe('use()', function () {
        it('adds callable middleware', function () {
            $instance = createMiddlewareInstance();
            $middleware = fn ($req, $next) => $next($req);

            $result = $instance->use($middleware);

            expect($result)->toBe($instance);
            expect($instance->getMiddleware())->toBe([$middleware]);
        });

        it('adds string middleware', function () {
            $instance = createMiddlewareInstance();
            $instance->use('AuthMiddleware');

            expect($instance->getMiddleware())->toBe(['AuthMiddleware']);
        });

        it('adds multiple middleware in order', function () {
            $instance = createMiddlewareInstance();
            $first = fn ($req, $next) => $next($req);
            $second = fn ($req, $next) => $next($req);
            $third = 'ThirdMiddleware';

            $instance->use($first)->use($second)->use($third);

            expect($instance->getMiddleware())->toBe([$first, $second, $third]);
        });

        it('returns static for fluent chaining', function () {
            $instance = createMiddlewareInstance();
            $result = $instance->use('Middleware');

            expect($result)->toBe($instance);
        });
    });

    describe('getMiddleware()', function () {
        it('returns empty array by default', function () {
            $instance = createMiddlewareInstance();
            expect($instance->getMiddleware())->toBe([]);
        });

        it('returns all added middleware', function () {
            $instance = createMiddlewareInstance();
            $instance->use('First');
            $instance->use('Second');
            $instance->use('Third');

            expect($instance->getMiddleware())->toBe(['First', 'Second', 'Third']);
        });
    });

    describe('integration with multiple classes', function () {
        it('maintains separate middleware state per instance', function () {
            $instance1 = createMiddlewareInstance();
            $instance2 = createMiddlewareInstance();

            $instance1->use('Middleware1');
            $instance2->use('Middleware2');
            $instance2->use('Middleware3');

            expect($instance1->getMiddleware())->toBe(['Middleware1']);
            expect($instance2->getMiddleware())->toBe(['Middleware2', 'Middleware3']);
        });
    });

});
