<?php

declare(strict_types=1);

namespace Verge\Console\Commands;

use Verge\App;
use Verge\Bootstrap\BootstrapCache;
use Verge\Console\Output;

/**
 * Clear the route and container cache.
 */
class CacheClearCommand
{
    public function __invoke(App $app, Output $output): int
    {
        if (!$app->has(BootstrapCache::class)) {
            $output->error('BootstrapCache is not registered.');
            $output->line('Add it to your app configuration:');
            $output->line('');
            $output->line('  $app->module(BootstrapCache::class);');
            return 1;
        }

        /** @var BootstrapCache $cache */
        $cache = $app->make(BootstrapCache::class);

        $cache->clear();

        $output->success('Cache cleared successfully.');

        return 0;
    }
}
