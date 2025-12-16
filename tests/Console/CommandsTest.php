<?php

declare(strict_types=1);

use Minicli\Output\OutputHandler;
use Verge\App;
use Verge\Bootstrap\BootstrapCache;
use Verge\Bootstrap\WarmResult;
use Verge\Bootstrap\RouteCacheResult;
use Verge\Bootstrap\ContainerCacheResult;
use Verge\Console\Commands\CacheClearCommand;
use Verge\Console\Commands\CacheWarmCommand;
use Verge\Console\Commands\RoutesListCommand;
use Verge\Console\Output;

function createTestOutput(): Output
{
    $handler = Mockery::mock(OutputHandler::class);
    $handler->shouldReceive('info', 'error', 'success', 'display')->byDefault();
    return new Output($handler);
}

describe('RoutesListCommand', function () {
    it('shows message when no routes', function () {
        $app = new App();
        $output = createTestOutput();

        $command = new RoutesListCommand();
        $result = $command($app, $output);

        expect($result)->toBe(0);
    });

    it('displays routes in table format', function () {
        $app = new App();
        $app->get('/users', fn () => [])->name('users.index');
        $app->post('/users', fn () => [])->name('users.store');

        $output = createTestOutput();

        $command = new RoutesListCommand();
        $result = $command($app, $output);

        expect($result)->toBe(0);
    });

    it('formats closure handler', function () {
        $app = new App();
        $app->get('/closure', fn () => 'ok');

        $output = createTestOutput();

        $command = new RoutesListCommand();
        $result = $command($app, $output);

        expect($result)->toBe(0);
    });

    it('formats array handler', function () {
        $app = new App();
        $app->get('/array', [CommandTestController::class, 'index']);

        $output = createTestOutput();

        $command = new RoutesListCommand();
        $result = $command($app, $output);

        expect($result)->toBe(0);
    });

    it('formats invokable handler', function () {
        $app = new App();
        $app->get('/invokable', CommandTestInvokableController::class);

        $output = createTestOutput();

        $command = new RoutesListCommand();
        $result = $command($app, $output);

        expect($result)->toBe(0);
    });

    it('shows middleware on routes', function () {
        $app = new App();
        $app->get('/protected', fn () => 'ok')
            ->use(fn ($req, $next) => $next($req));

        $output = createTestOutput();

        $command = new RoutesListCommand();
        $result = $command($app, $output);

        expect($result)->toBe(0);
    });
});

describe('CacheClearCommand', function () {
    it('clears cache when BootstrapCache is registered', function () {
        $app = new App();

        // Mock BootstrapCache
        $cache = Mockery::mock(BootstrapCache::class);
        $cache->shouldReceive('clear')->once();

        $app->instance(BootstrapCache::class, $cache);

        $output = createTestOutput();

        $command = new CacheClearCommand();
        $result = $command($app, $output);

        expect($result)->toBe(0);
    });
});

describe('CacheWarmCommand', function () {
    it('warms cache and shows result', function () {
        $app = new App();

        // Create mock result with correct signatures
        $routeResult = new RouteCacheResult(10, [], ['Handler1', 'Handler2']);
        $containerResult = new ContainerCacheResult(5, []);
        $warmResult = new WarmResult($routeResult, $containerResult);

        // Mock BootstrapCache
        $cache = Mockery::mock(BootstrapCache::class);
        $cache->shouldReceive('warm')->once()->andReturn($warmResult);

        $app->instance(BootstrapCache::class, $cache);

        $output = createTestOutput();

        $command = new CacheWarmCommand();
        $result = $command($app, $output);

        expect($result)->toBe(0);
    });

    it('shows warning when routes are skipped', function () {
        $app = new App();

        // Create result with skipped routes
        $routeResult = new RouteCacheResult(
            10,
            [
                ['method' => 'GET', 'path' => '/closure1', 'reason' => 'Closure handler'],
                ['method' => 'POST', 'path' => '/closure2', 'reason' => 'Closure handler'],
            ],
            ['Handler1']
        );
        $containerResult = new ContainerCacheResult(5, []);
        $warmResult = new WarmResult($routeResult, $containerResult);

        // Mock BootstrapCache
        $cache = Mockery::mock(BootstrapCache::class);
        $cache->shouldReceive('warm')->once()->andReturn($warmResult);

        $app->instance(BootstrapCache::class, $cache);

        $output = createTestOutput();

        $command = new CacheWarmCommand();
        $result = $command($app, $output);

        expect($result)->toBe(0);
    });
});

// Test fixtures
class CommandTestController
{
    public function index(): array
    {
        return ['message' => 'hello'];
    }
}

class CommandTestInvokableController
{
    public function __invoke(): string
    {
        return 'invoked';
    }
}
