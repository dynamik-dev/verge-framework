<?php

declare(strict_types=1);

namespace Verge\Log;

use Verge\App;

class LogModule
{
    public function __invoke(App $app): void
    {
        $logPath = $app->env('LOG_PATH', 'php://stderr');
        $logLevel = $app->env('LOG_LEVEL', 'debug');

        $app->driver('log', 'stream', fn () => new Drivers\StreamLogDriver(
            is_scalar($logPath) ? (string) $logPath : 'php://stderr',
            LogLevel::from(is_scalar($logLevel) ? (string) $logLevel : 'debug')
        ));
        $app->driver('log', 'array', fn () => new Drivers\ArrayLogDriver());
        $app->defaultDriver('log', 'stream');

        $app->singleton(LoggerInterface::class, fn () => $app->driver('log'));
    }
}
