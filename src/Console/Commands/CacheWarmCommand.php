<?php

declare(strict_types=1);

namespace Verge\Console\Commands;

use Verge\App;
use Verge\Bootstrap\BootstrapCache;
use Verge\Console\Output;

/**
 * Warm the route and container cache.
 */
class CacheWarmCommand
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

        $output->info('Warming cache...');

        $result = $cache->warm();

        $output->line('');
        $output->line($result->summary());

        if ($result->hasWarnings()) {
            $output->line('');
            $output->error('Some routes could not be cached (closures).');
            $output->line('Convert them to controller classes for caching.');
        } else {
            $output->line('');
            $output->success('Cache warmed successfully.');
        }

        return 0;
    }
}
