<?php

declare(strict_types=1);

use Verge\Bundler\ClosureExtractor;
use Verge\Bundler\ClosureInfo;

beforeEach(function () {
    $this->extractor = new ClosureExtractor();
});

describe('ClosureExtractor', function () {
    describe('extract()', function () {
        it('extracts simple arrow function', function () {
            $closure = fn () => 'hello';

            $info = $this->extractor->extract($closure);

            expect($info)->toBeInstanceOf(ClosureInfo::class);
            expect($info->isArrowFunction)->toBeTrue();
            expect($info->parameters)->toBeEmpty();
            expect($info->body)->toContain('hello');
        });

        it('extracts arrow function with parameters', function () {
            $closure = fn ($id) => ['id' => $id];

            $info = $this->extractor->extract($closure);

            expect($info->parameters)->toHaveCount(1);
            expect($info->parameters[0]->name)->toBe('id');
            expect($info->body)->toContain('id');
        });

        it('extracts typed parameters', function () {
            $closure = fn (string $name, int $age) => "{$name} is {$age}";

            $info = $this->extractor->extract($closure);

            expect($info->parameters)->toHaveCount(2);
            expect($info->parameters[0]->name)->toBe('name');
            expect($info->parameters[0]->type)->toBe('string');
            expect($info->parameters[0]->isBuiltin)->toBeTrue();
            expect($info->parameters[1]->name)->toBe('age');
            expect($info->parameters[1]->type)->toBe('int');
        });

        it('extracts class type-hinted parameters', function () {
            $closure = fn (\stdClass $obj) => $obj;

            $info = $this->extractor->extract($closure);

            expect($info->parameters[0]->type)->toBe('stdClass');
            expect($info->parameters[0]->isBuiltin)->toBeFalse();
        });

        it('extracts return type', function () {
            $closure = fn (): string => 'hello';

            $info = $this->extractor->extract($closure);

            expect($info->returnType)->toBe('string');
        });

        it('extracts traditional closure', function () {
            $closure = function ($x) {
                return $x * 2;
            };

            $info = $this->extractor->extract($closure);

            expect($info->isArrowFunction)->toBeFalse();
            expect($info->parameters)->toHaveCount(1);
            expect($info->body)->toContain('return');
        });

        it('extracts closure with use clause', function () {
            $multiplier = 5;
            $closure = fn ($x) => $x * $multiplier;

            $info = $this->extractor->extract($closure);

            expect($info->hasUses())->toBeTrue();
            expect($info->uses)->toHaveKey('multiplier');
            expect($info->uses['multiplier'])->toBe(5);
        });

        it('detects bound closures', function () {
            $obj = new class () {
                public function getClosure(): \Closure
                {
                    return fn () => $this;
                }
            };

            $closure = $obj->getClosure();
            $info = $this->extractor->extract($closure);

            expect($info->bindThis)->toBeTrue();
            expect($info->isConvertible())->toBeFalse();
            expect($info->getSkipReason())->toBe('Closure binds $this');
        });

        it('extracts source location', function () {
            $closure = fn () => 'test';

            $info = $this->extractor->extract($closure);

            expect($info->sourceFile)->toContain('ClosureExtractorTest.php');
            expect($info->startLine)->toBeGreaterThan(0);
            expect($info->endLine)->toBeGreaterThanOrEqual($info->startLine);
        });

        it('extracts optional parameters with defaults', function () {
            $closure = fn ($required, $optional = 'default') => $required . $optional;

            $info = $this->extractor->extract($closure);

            expect($info->parameters)->toHaveCount(2);
            expect($info->parameters[0]->isOptional)->toBeFalse();
            expect($info->parameters[1]->isOptional)->toBeTrue();
            expect($info->parameters[1]->hasDefaultValue)->toBeTrue();
            expect($info->parameters[1]->defaultValue)->toBe('default');
        });

        it('extracts nullable parameters', function () {
            $closure = fn (?string $name) => $name ?? 'anonymous';

            $info = $this->extractor->extract($closure);

            expect($info->parameters[0]->isNullable)->toBeTrue();
        });
    });

    describe('isConvertible()', function () {
        it('returns true for unbound closures', function () {
            // Create closure in static context to avoid $this binding
            $closure = getUnboundClosure();

            $info = $this->extractor->extract($closure);

            expect($info->bindThis)->toBeFalse();
            expect($info->isConvertible())->toBeTrue();
        });

        it('returns false for bound closures', function () {
            $obj = new class () {
                public function getClosure(): \Closure
                {
                    return fn () => $this;
                }
            };

            $closure = $obj->getClosure();
            $info = $this->extractor->extract($closure);

            expect($info->bindThis)->toBeTrue();
            expect($info->isConvertible())->toBeFalse();
        });
    });
});

// Helper function to create unbound closure (outside class context)
function getUnboundClosure(): \Closure
{
    return fn ($id) => ['id' => $id];
}
