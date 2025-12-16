<?php

declare(strict_types=1);

use Verge\App;
use Verge\Console\ConsoleModule;
use Verge\Console\Commands\CacheClearCommand;
use Verge\Console\Commands\CacheWarmCommand;
use Verge\Console\Commands\RoutesListCommand;

describe('ConsoleModule', function () {
    it('registers all built-in commands', function () {
        $app = new App();

        $module = new ConsoleModule();
        $module($app);

        $commands = $app->getCommands();

        expect($commands)->toHaveKey('routes:list');
        expect($commands)->toHaveKey('cache:warm');
        expect($commands)->toHaveKey('cache:clear');
    });

    it('registers RoutesListCommand for routes:list', function () {
        $app = new App();

        $module = new ConsoleModule();
        $module($app);

        expect($app->getCommands()['routes:list'])->toBe(RoutesListCommand::class);
    });

    it('registers CacheWarmCommand for cache:warm', function () {
        $app = new App();

        $module = new ConsoleModule();
        $module($app);

        expect($app->getCommands()['cache:warm'])->toBe(CacheWarmCommand::class);
    });

    it('registers CacheClearCommand for cache:clear', function () {
        $app = new App();

        $module = new ConsoleModule();
        $module($app);

        expect($app->getCommands()['cache:clear'])->toBe(CacheClearCommand::class);
    });

    it('is registered via AppBuilder', function () {
        $app = new App();

        // AppBuilder is called in App constructor
        $commands = $app->getCommands();

        expect($commands)->toHaveKey('routes:list');
        expect($commands)->toHaveKey('cache:warm');
        expect($commands)->toHaveKey('cache:clear');
    });
});
