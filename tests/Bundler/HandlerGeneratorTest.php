<?php

declare(strict_types=1);

use Verge\Bundler\ClosureExtractor;
use Verge\Bundler\HandlerGenerator;

beforeEach(function () {
    $this->generator = new HandlerGenerator('App\\Handlers');
    $this->extractor = new ClosureExtractor();
    $this->outputPath = sys_get_temp_dir() . '/verge-bundler-test-' . uniqid();
    mkdir($this->outputPath, 0755, true);
    $this->generator->setOutputPath($this->outputPath);
});

afterEach(function () {
    // Clean up generated files
    if (is_dir($this->outputPath)) {
        array_map('unlink', glob($this->outputPath . '/*') ?: []);
        rmdir($this->outputPath);
    }
});

describe('HandlerGenerator', function () {
    describe('generateClassName()', function () {
        it('generates GetIndexHandler for root path', function () {
            expect($this->generator->generateClassName('GET', '/'))
                ->toBe('GetIndexHandler');
        });

        it('generates GetUsersHandler for /users', function () {
            expect($this->generator->generateClassName('GET', '/users'))
                ->toBe('GetUsersHandler');
        });

        it('generates GetUsersIdHandler for /users/{id}', function () {
            expect($this->generator->generateClassName('GET', '/users/{id}'))
                ->toBe('GetUsersIdHandler');
        });

        it('generates PostUsersHandler for POST /users', function () {
            expect($this->generator->generateClassName('POST', '/users'))
                ->toBe('PostUsersHandler');
        });

        it('generates DeleteUsersIdHandler for DELETE /users/{id}', function () {
            expect($this->generator->generateClassName('DELETE', '/users/{id}'))
                ->toBe('DeleteUsersIdHandler');
        });

        it('handles nested paths', function () {
            expect($this->generator->generateClassName('GET', '/users/{id}/posts'))
                ->toBe('GetUsersIdPostsHandler');
        });

        it('handles deeply nested paths', function () {
            expect($this->generator->generateClassName('GET', '/api/v1/users/{userId}/posts/{postId}'))
                ->toBe('GetApiV1UsersUserIdPostsPostIdHandler');
        });

        it('handles kebab-case segments', function () {
            expect($this->generator->generateClassName('GET', '/user-profiles'))
                ->toBe('GetUserProfilesHandler');
        });

        it('handles snake_case segments', function () {
            expect($this->generator->generateClassName('GET', '/user_profiles'))
                ->toBe('GetUserProfilesHandler');
        });

        it('handles parameters with constraints', function () {
            expect($this->generator->generateClassName('GET', '/users/{id:\\d+}'))
                ->toBe('GetUsersIdHandler');
        });
    });

    describe('generate()', function () {
        it('generates valid PHP class for simple closure', function () {
            $closure = fn() => 'hello';
            $info = $this->extractor->extract($closure);

            $content = $this->generator->generate('GetIndexHandler', $info);

            expect($content)->toContain('namespace App\\Handlers;');
            expect($content)->toContain('class GetIndexHandler');
            expect($content)->toContain('public function __invoke()');
            expect($content)->toContain("return 'hello';");
        });

        it('generates class with parameters', function () {
            $closure = fn($id) => ['id' => $id];
            $info = $this->extractor->extract($closure);

            $content = $this->generator->generate('GetUsersIdHandler', $info);

            expect($content)->toContain('public function __invoke($id)');
        });

        it('generates class with typed parameters', function () {
            $closure = fn(string $name) => $name;
            $info = $this->extractor->extract($closure);

            $content = $this->generator->generate('GetUsersHandler', $info);

            expect($content)->toContain('public function __invoke(string $name)');
        });

        it('generates class with return type', function () {
            $closure = fn(): array => [];
            $info = $this->extractor->extract($closure);

            $content = $this->generator->generate('GetUsersHandler', $info);

            expect($content)->toContain('public function __invoke(): array');
        });

        it('generates use statements for class types', function () {
            $closure = fn(\stdClass $obj) => $obj;
            $info = $this->extractor->extract($closure);

            $content = $this->generator->generate('GetHandler', $info);

            expect($content)->toContain('use stdClass;');
        });

        it('generates constructor for closures with use clause', function () {
            $value = 42;
            $closure = fn() => $value;
            $info = $this->extractor->extract($closure);

            $content = $this->generator->generate('GetHandler', $info);

            expect($content)->toContain('private int $value;');
            expect($content)->toContain('public function __construct(int $value)');
            expect($content)->toContain('$this->value = $value;');
        });
    });

    describe('write()', function () {
        it('creates handler file', function () {
            $closure = fn() => 'test';
            $info = $this->extractor->extract($closure);

            $content = $this->generator->generate('TestHandler', $info);
            $filePath = $this->generator->write('TestHandler', $content);

            expect(file_exists($filePath))->toBeTrue();
            expect(file_get_contents($filePath))->toBe($content);
        });

        it('creates directory if not exists', function () {
            $newPath = $this->outputPath . '/nested/handlers';
            $this->generator->setOutputPath($newPath);

            $closure = fn() => 'test';
            $info = $this->extractor->extract($closure);

            $content = $this->generator->generate('TestHandler', $info);
            $filePath = $this->generator->write('TestHandler', $content);

            expect(file_exists($filePath))->toBeTrue();

            // Cleanup nested dirs
            unlink($filePath);
            rmdir($newPath);
            rmdir(dirname($newPath));
        });
    });

    describe('getFullyQualifiedClassName()', function () {
        it('returns namespaced class name', function () {
            expect($this->generator->getFullyQualifiedClassName('GetUsersHandler'))
                ->toBe('App\\Handlers\\GetUsersHandler');
        });
    });
});
