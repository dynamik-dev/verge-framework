<?php

declare(strict_types=1);

use Psr\Container\ContainerExceptionInterface;
use Verge\ContainerException;

describe('ContainerException', function () {

    it('extends Exception', function () {
        $exception = new ContainerException('test');

        expect($exception)->toBeInstanceOf(Exception::class);
    });

    it('implements PSR-11 ContainerExceptionInterface', function () {
        $exception = new ContainerException('test');

        expect($exception)->toBeInstanceOf(ContainerExceptionInterface::class);
    });

    it('stores message', function () {
        $exception = new ContainerException('Something went wrong');

        expect($exception->getMessage())->toBe('Something went wrong');
    });

    it('can be thrown and caught', function () {
        $caught = false;

        try {
            throw new ContainerException('Test error');
        } catch (ContainerException $e) {
            $caught = true;
            expect($e->getMessage())->toBe('Test error');
        }

        expect($caught)->toBeTrue();
    });

    it('can be caught as ContainerExceptionInterface', function () {
        $caught = false;

        try {
            throw new ContainerException('PSR-11 error');
        } catch (ContainerExceptionInterface $e) {
            $caught = true;
        }

        expect($caught)->toBeTrue();
    });

});
