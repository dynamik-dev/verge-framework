<?php

declare(strict_types=1);

use Verge\App;
use Verge\Bundler\Bundler;
use Verge\Bundler\BuildResult;

beforeEach(function () {
    $this->outputPath = sys_get_temp_dir() . '/verge-bundler-test-' . uniqid();
});

// Helper functions to create unbound closures (outside class context)
function createIndexClosure(): \Closure
{
    return fn () => 'index';
}

function createUsersClosure(): \Closure
{
    return fn () => ['users' => []];
}

function createUserByIdClosure(): \Closure
{
    return fn ($id) => ['id' => $id];
}

function createPostClosure(): \Closure
{
    return fn () => 'create';
}

function createDoubleClosure(int $multiplier): \Closure
{
    return fn ($n) => $n * $multiplier;
}

afterEach(function () {
    // Clean up output directory
    if (is_dir($this->outputPath)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->outputPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $path = $file->getPathname();
            if (is_link($path)) {
                unlink($path);
            } elseif ($file->isDir()) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($this->outputPath);
    }
});

describe('Bundler', function () {
    describe('__invoke()', function () {
        it('registers itself in the container', function () {
            $app = new App();
            $bundler = new Bundler(outputPath: $this->outputPath);

            $app->module($bundler);

            expect($app->make(Bundler::class))->toBe($bundler);
        });

        it('can be configured with class string', function () {
            $app = new App();
            $app->module(Bundler::class);

            expect($app->make(Bundler::class))->toBeInstanceOf(Bundler::class);
        });
    });

    describe('build()', function () {
        it('throws when called before module', function () {
            $bundler = new Bundler(outputPath: $this->outputPath);

            expect(fn () => $bundler->build())
                ->toThrow(\RuntimeException::class, 'must be registered');
        });

        it('returns BuildResult', function () {
            $app = new App();
            $app->get('/', createIndexClosure());

            $bundler = new Bundler(outputPath: $this->outputPath);
            $app->module($bundler);

            $result = $bundler->build();

            expect($result)->toBeInstanceOf(BuildResult::class);
        });

        it('converts closure routes to handlers', function () {
            $app = new App();
            $app->get('/', createIndexClosure());
            $app->get('/users', createUsersClosure());
            $app->get('/users/{id}', createUserByIdClosure());

            $bundler = new Bundler(outputPath: $this->outputPath, symlinkVendor: false);
            $app->module($bundler);

            $result = $bundler->build();

            expect($result->closuresConverted)->toBe(3);
            // 3 handlers + routes.php + bootstrap.php = 5
            expect($result->filesGenerated)->toBe(5);
            expect($result->handlers)->toHaveCount(3);
        });

        it('generates correct handler class names', function () {
            $app = new App();
            $app->get('/', createIndexClosure());
            $app->post('/users', createPostClosure());
            $app->get('/users/{id}', createUserByIdClosure());

            $bundler = new Bundler(
                outputPath: $this->outputPath,
                namespace: 'App\\Handlers',
            );
            $app->module($bundler);

            $result = $bundler->build();

            expect($result->handlers)->toHaveKey('GET /');
            expect($result->handlers['GET /'])->toBe('App\\Handlers\\GetIndexHandler');
            expect($result->handlers)->toHaveKey('POST /users');
            expect($result->handlers['POST /users'])->toBe('App\\Handlers\\PostUsersHandler');
            expect($result->handlers)->toHaveKey('GET /users/{id}');
            expect($result->handlers['GET /users/{id}'])->toBe('App\\Handlers\\GetUsersIdHandler');
        });

        it('creates handler files', function () {
            $app = new App();
            $app->get('/', createIndexClosure());

            $bundler = new Bundler(outputPath: $this->outputPath);
            $app->module($bundler);

            $bundler->build();

            $handlerFile = $this->outputPath . '/Handlers/GetIndexHandler.php';
            expect(file_exists($handlerFile))->toBeTrue();

            $content = file_get_contents($handlerFile);
            expect($content)->toContain('class GetIndexHandler');
            expect($content)->toContain('public function __invoke()');
        });

        it('skips non-closure routes', function () {
            $app = new App();
            $app->get('/', createIndexClosure());
            $app->get('/users', ['TestController', 'index']);

            $bundler = new Bundler(outputPath: $this->outputPath);
            $app->module($bundler);

            $result = $bundler->build();

            expect($result->closuresConverted)->toBe(1);
            expect($result->handlers)->toHaveCount(1);
        });

        it('skips bound closures', function () {
            $obj = new class () {
                public function getApp(): App
                {
                    $app = new App();
                    $app->get('/', fn () => $this); // Bound closure
                    return $app;
                }
            };

            $app = $obj->getApp();
            $bundler = new Bundler(outputPath: $this->outputPath);
            $app->module($bundler);

            $result = $bundler->build();

            expect($result->closuresConverted)->toBe(0);
            expect($result->skipped)->toHaveCount(1);
            expect($result->skipped[0]['reason'])->toContain('$this');
        });

        it('handles closures with use clause', function () {
            $app = new App();
            $app->get('/double/{n}', createDoubleClosure(2));

            $bundler = new Bundler(outputPath: $this->outputPath);
            $app->module($bundler);

            $result = $bundler->build();

            expect($result->closuresConverted)->toBe(1);

            $handlerFile = $this->outputPath . '/Handlers/GetDoubleNHandler.php';
            $content = file_get_contents($handlerFile);
            expect($content)->toContain('private int $multiplier');
            expect($content)->toContain('__construct');
        });
    });

    describe('analyze()', function () {
        it('throws when called before module', function () {
            $bundler = new Bundler(outputPath: $this->outputPath);

            expect(fn () => $bundler->analyze())
                ->toThrow(\RuntimeException::class, 'must be registered');
        });

        it('returns route analysis', function () {
            $app = new App();
            $app->get('/', createIndexClosure());
            $app->get('/users', ['TestController', 'index']);

            $bundler = new Bundler(outputPath: $this->outputPath);
            $app->module($bundler);

            $analysis = $bundler->analyze();

            expect($analysis['total_routes'])->toBe(2);
            expect($analysis['closure_routes'])->toBe(1);
            expect($analysis['convertible'])->toBe(1);
        });

        it('identifies convertible closures', function () {
            $app = new App();
            $app->get('/users/{id}', createUserByIdClosure());

            $bundler = new Bundler(outputPath: $this->outputPath);
            $app->module($bundler);

            $analysis = $bundler->analyze();

            expect($analysis['routes']['GET /users/{id}']['convertible'])->toBeTrue();
            expect($analysis['routes']['GET /users/{id}']['target_class'])->toBe('GetUsersIdHandler');
        });
    });

    describe('BuildResult', function () {
        it('generates summary', function () {
            $app = new App();
            $app->get('/', createIndexClosure());
            $app->get('/users', createUsersClosure());

            $bundler = new Bundler(outputPath: $this->outputPath, symlinkVendor: false);
            $app->module($bundler);

            $result = $bundler->build();
            $summary = $result->summary();

            expect($summary)->toContain('Closures converted: 2');
            // 2 handlers + routes.php + bootstrap.php = 4
            expect($summary)->toContain('Handler files generated: 4');
        });

        it('reports skipped routes in summary', function () {
            $obj = new class () {
                public function getApp(): App
                {
                    $app = new App();
                    $app->get('/', fn () => $this);
                    return $app;
                }
            };

            $app = $obj->getApp();
            $bundler = new Bundler(outputPath: $this->outputPath);
            $app->module($bundler);

            $result = $bundler->build();
            $summary = $result->summary();

            expect($summary)->toContain('Skipped');
            expect($summary)->toContain('GET /');
        });
    });
});
