<?php

declare(strict_types=1);

use Verge\Routing\Route;
use Verge\Routing\RouteMatch;

describe('RouteMatch', function () {

    describe('constructor', function () {
        it('creates a matched result', function () {
            $route = new Route('GET', '/', fn () => 'home', '#^/$#');
            $match = new RouteMatch(true, $route, ['id' => '123']);

            expect($match->matched)->toBeTrue();
            expect($match->route)->toBe($route);
            expect($match->params)->toBe(['id' => '123']);
        });

        it('creates an unmatched result', function () {
            $match = new RouteMatch(false);

            expect($match->matched)->toBeFalse();
            expect($match->route)->toBeNull();
            expect($match->params)->toBe([]);
        });

        it('defaults route to null', function () {
            $match = new RouteMatch(false);

            expect($match->route)->toBeNull();
        });

        it('defaults params to empty array', function () {
            $route = new Route('GET', '/', fn () => 'home', '#^/$#');
            $match = new RouteMatch(true, $route);

            expect($match->params)->toBe([]);
        });
    });

    describe('notFound()', function () {
        it('creates unmatched result', function () {
            $match = RouteMatch::notFound();

            expect($match->matched)->toBeFalse();
            expect($match->route)->toBeNull();
            expect($match->params)->toBe([]);
        });

        it('returns new instance each time', function () {
            $match1 = RouteMatch::notFound();
            $match2 = RouteMatch::notFound();

            expect($match1)->not->toBe($match2);
        });
    });

    describe('found()', function () {
        it('creates matched result with route', function () {
            $route = new Route('GET', '/users', fn () => 'users', '#^/users$#');
            $match = RouteMatch::found($route);

            expect($match->matched)->toBeTrue();
            expect($match->route)->toBe($route);
            expect($match->params)->toBe([]);
        });

        it('creates matched result with params', function () {
            $route = new Route('GET', '/users/{id}', fn () => 'user', '#^/users/([^/]+)$#', ['id']);
            $match = RouteMatch::found($route, ['id' => '42']);

            expect($match->matched)->toBeTrue();
            expect($match->route)->toBe($route);
            expect($match->params)->toBe(['id' => '42']);
        });

        it('creates matched result with multiple params', function () {
            $route = new Route(
                'GET',
                '/posts/{postId}/comments/{commentId}',
                fn () => 'comment',
                '#^/posts/([^/]+)/comments/([^/]+)$#',
                ['postId', 'commentId']
            );
            $match = RouteMatch::found($route, ['postId' => '1', 'commentId' => '99']);

            expect($match->params)->toBe(['postId' => '1', 'commentId' => '99']);
        });
    });

});
