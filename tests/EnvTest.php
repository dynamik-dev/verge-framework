<?php

declare(strict_types=1);

use Verge\Env\Env;

describe('Env', function () {

    beforeEach(function () {
        // Clean up test env vars
        unset($_ENV['TEST_VAR']);
        putenv('TEST_VAR');
        unset($_ENV['TEST_BOOL']);
        putenv('TEST_BOOL');
    });

    describe('get()', function () {
        it('gets value from $_ENV', function () {
            $_ENV['TEST_VAR'] = 'from_env';
            $env = new Env();

            expect($env->get('TEST_VAR'))->toBe('from_env');
        });

        it('gets value from getenv', function () {
            putenv('TEST_GETENV_VAR=from_putenv');
            $env = new Env();

            expect($env->get('TEST_GETENV_VAR'))->toBe('from_putenv');

            putenv('TEST_GETENV_VAR');
        });

        it('returns default for missing key', function () {
            $env = new Env();

            expect($env->get('MISSING_VAR', 'default'))->toBe('default');
        });

        it('returns null by default for missing key', function () {
            $env = new Env();

            expect($env->get('MISSING_VAR'))->toBeNull();
        });

        it('returns default for empty string', function () {
            $_ENV['TEST_VAR'] = '';
            $env = new Env();

            expect($env->get('TEST_VAR', 'default'))->toBe('default');
        });

        it('converts true string to boolean', function () {
            $env = new Env();

            $_ENV['TEST_BOOL'] = 'true';
            expect($env->get('TEST_BOOL'))->toBeTrue();

            $_ENV['TEST_BOOL'] = 'TRUE';
            expect($env->get('TEST_BOOL'))->toBeTrue();

            $_ENV['TEST_BOOL'] = '(true)';
            expect($env->get('TEST_BOOL'))->toBeTrue();
        });

        it('converts false string to boolean', function () {
            $env = new Env();

            $_ENV['TEST_BOOL'] = 'false';
            expect($env->get('TEST_BOOL'))->toBeFalse();

            $_ENV['TEST_BOOL'] = 'FALSE';
            expect($env->get('TEST_BOOL'))->toBeFalse();

            $_ENV['TEST_BOOL'] = '(false)';
            expect($env->get('TEST_BOOL'))->toBeFalse();
        });

        it('converts null string to null', function () {
            $env = new Env();

            $_ENV['TEST_VAR'] = 'null';
            expect($env->get('TEST_VAR'))->toBeNull();

            $_ENV['TEST_VAR'] = 'NULL';
            expect($env->get('TEST_VAR'))->toBeNull();

            $_ENV['TEST_VAR'] = '(null)';
            expect($env->get('TEST_VAR'))->toBeNull();
        });

        it('converts empty string token to empty string', function () {
            $env = new Env();

            $_ENV['TEST_VAR'] = 'empty';
            expect($env->get('TEST_VAR'))->toBe('');

            $_ENV['TEST_VAR'] = '(empty)';
            expect($env->get('TEST_VAR'))->toBe('');
        });

        it('returns regular strings as-is', function () {
            $_ENV['TEST_VAR'] = 'hello world';
            $env = new Env();

            expect($env->get('TEST_VAR'))->toBe('hello world');
        });

        it('returns numeric strings as-is', function () {
            $_ENV['TEST_VAR'] = '12345';
            $env = new Env();

            expect($env->get('TEST_VAR'))->toBe('12345');
        });
    });

    describe('has()', function () {
        it('returns true for $_ENV key', function () {
            $_ENV['TEST_VAR'] = 'value';
            $env = new Env();

            expect($env->has('TEST_VAR'))->toBeTrue();
        });

        it('returns true for getenv key', function () {
            putenv('TEST_VAR=value');
            $env = new Env();

            expect($env->has('TEST_VAR'))->toBeTrue();
        });

        it('returns false for missing key', function () {
            $env = new Env();

            expect($env->has('MISSING_VAR'))->toBeFalse();
        });
    });

    describe('set()', function () {
        it('sets value in $_ENV', function () {
            $env = new Env();

            $env->set('TEST_VAR', 'new_value');

            expect($_ENV['TEST_VAR'])->toBe('new_value');
        });

        it('sets value via putenv', function () {
            $env = new Env();

            $env->set('TEST_VAR', 'new_value');

            expect(getenv('TEST_VAR'))->toBe('new_value');
        });

        it('makes value available via get', function () {
            $env = new Env();

            $env->set('TEST_VAR', 'test_value');

            expect($env->get('TEST_VAR'))->toBe('test_value');
        });

        it('overwrites existing value', function () {
            $_ENV['TEST_VAR'] = 'old';
            $env = new Env();

            $env->set('TEST_VAR', 'new');

            expect($env->get('TEST_VAR'))->toBe('new');
        });
    });

});
