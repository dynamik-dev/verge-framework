<?php

declare(strict_types=1);

namespace Verge\Console;

use Verge\App;
use Verge\Console\Commands\CacheClearCommand;
use Verge\Console\Commands\CacheWarmCommand;
use Verge\Console\Commands\RoutesListCommand;

/**
 * Registers built-in console commands.
 */
class ConsoleModule
{
    public function __invoke(App $app): void
    {
        $app->command('routes:list', RoutesListCommand::class);
        $app->command('cache:warm', CacheWarmCommand::class);
        $app->command('cache:clear', CacheClearCommand::class);
    }
}
