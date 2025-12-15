<?php

declare(strict_types=1);

use Verge\Routing\ParamInfo;
use Verge\Routing\PathParser;

describe('PathParser', function () {

    describe('compile()', function () {
        it('compiles path without parameters', function () {
            [$pattern, $params] = PathParser::compile('/users');

            expect($pattern)->toBe('#^/users$#');
            expect($params)->toBe([]);
        });

        it('compiles path with single required parameter', function () {
            [$pattern, $params] = PathParser::compile('/users/{id}');

            expect($pattern)->toBe('#^/users/([^/]+)$#');
            expect($params)->toBe(['id']);
        });

        it('compiles path with optional parameter', function () {
            [$pattern, $params] = PathParser::compile('/archive/{year?}');

            expect($pattern)->toBe('#^/archive(?:/([^/]+))?$#');
            expect($params)->toBe(['year']);
        });

        it('compiles path with constraint', function () {
            [$pattern, $params] = PathParser::compile('/users/{id:\d+}');

            expect($pattern)->toBe('#^/users/(\d+)$#');
            expect($params)->toBe(['id']);
        });

        it('compiles path with nested brace constraint', function () {
            [$pattern, $params] = PathParser::compile('/archive/{year:\d{4}}');

            expect($pattern)->toBe('#^/archive/(\d{4})$#');
            expect($params)->toBe(['year']);
        });

        it('compiles path with optional parameter and constraint', function () {
            [$pattern, $params] = PathParser::compile('/archive/{year?:\d{4}}');

            expect($pattern)->toBe('#^/archive(?:/(\d{4}))?$#');
            expect($params)->toBe(['year']);
        });

        it('compiles path with multiple parameters', function () {
            [$pattern, $params] = PathParser::compile('/posts/{postId}/comments/{commentId}');

            expect($pattern)->toBe('#^/posts/([^/]+)/comments/([^/]+)$#');
            expect($params)->toBe(['postId', 'commentId']);
        });

        it('matches compiled pattern against paths', function () {
            [$pattern, $params] = PathParser::compile('/users/{id:\d+}');

            expect(preg_match($pattern, '/users/123'))->toBe(1);
            expect(preg_match($pattern, '/users/abc'))->toBe(0);
        });
    });

    describe('extractParams()', function () {
        it('returns empty array for path without parameters', function () {
            $params = PathParser::extractParams('/users');

            expect($params)->toBe([]);
        });

        it('extracts required parameter', function () {
            $params = PathParser::extractParams('/users/{id}');

            expect($params)->toHaveCount(1);
            expect($params[0])->toBeInstanceOf(ParamInfo::class);
            expect($params[0]->name)->toBe('id');
            expect($params[0]->required)->toBeTrue();
            expect($params[0]->constraint)->toBeNull();
        });

        it('extracts optional parameter', function () {
            $params = PathParser::extractParams('/archive/{year?}');

            expect($params)->toHaveCount(1);
            expect($params[0]->name)->toBe('year');
            expect($params[0]->required)->toBeFalse();
            expect($params[0]->constraint)->toBeNull();
        });

        it('extracts parameter with constraint', function () {
            $params = PathParser::extractParams('/users/{id:\d+}');

            expect($params)->toHaveCount(1);
            expect($params[0]->name)->toBe('id');
            expect($params[0]->required)->toBeTrue();
            expect($params[0]->constraint)->toBe('\d+');
        });

        it('extracts parameter with nested brace constraint', function () {
            $params = PathParser::extractParams('/archive/{year:\d{4}}');

            expect($params)->toHaveCount(1);
            expect($params[0]->name)->toBe('year');
            expect($params[0]->required)->toBeTrue();
            expect($params[0]->constraint)->toBe('\d{4}');
        });

        it('extracts optional parameter with constraint', function () {
            $params = PathParser::extractParams('/archive/{year?:\d{4}}');

            expect($params)->toHaveCount(1);
            expect($params[0]->name)->toBe('year');
            expect($params[0]->required)->toBeFalse();
            expect($params[0]->constraint)->toBe('\d{4}');
        });

        it('extracts multiple parameters', function () {
            $params = PathParser::extractParams('/posts/{postId}/comments/{commentId}');

            expect($params)->toHaveCount(2);
            expect($params[0]->name)->toBe('postId');
            expect($params[1]->name)->toBe('commentId');
        });
    });

    describe('ParamInfo::toArray()', function () {
        it('serializes parameter info to array', function () {
            $params = PathParser::extractParams('/users/{id:\d+}');

            expect($params[0]->toArray())->toBe([
                'name' => 'id',
                'required' => true,
                'constraint' => '\d+',
            ]);
        });
    });

});
