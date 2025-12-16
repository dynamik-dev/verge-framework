---
title: Swapping Service Implementations
description: Switch between different implementations for cache, logging, and other services using drivers.
---

When you need to change from in-memory cache to Redis, or from file logging to database logging, use the driver system to swap implementations without changing your code.

## Setting Which Driver to Use

Configure which implementation to use through environment variables:

```bash
# .env
CACHE_DRIVER=memory
LOG_DRIVER=stream
```

The service resolves the configured driver automatically:

```php
app()->get('/test', function(Cache $cache, Logger $logger) {
    $cache->set('key', 'value');  // Uses CACHE_DRIVER
    $logger->info('Logged');      // Uses LOG_DRIVER

    return 'ok';
});
```

## Registering Your Own Drivers

Add custom driver implementations for any service:

```php
use App\Cache\RedisCache;

app()->driver('cache', 'redis', function(App $app) {
    return new RedisCache($app->env('REDIS_URL'));
});
```

Now you can set `CACHE_DRIVER=redis` to use it.

## Getting the Configured Driver

Resolve the active driver instance directly:

```php
// Reads CACHE_DRIVER env var and returns that driver
$cache = app()->driver('cache');
```

The driver system reads the `{SERVICE}_DRIVER` environment variable (like `CACHE_DRIVER`), falls back to the default if not set, and returns the driver instance.

## Built-in Cache Drivers

Verge includes a memory driver that stores cache data for the current request:

```bash
CACHE_DRIVER=memory
```

This is registered by default:

```php
use Verge\Cache\Drivers\MemoryCacheDriver;

app()->driver('cache', 'memory', fn() => new MemoryCacheDriver());
```

## Adding a Redis Cache Driver

Register a Redis implementation by implementing the `CacheInterface`:

```php
use Verge\Cache\CacheInterface;

app()->driver('cache', 'redis', function(App $app) {
    return new class($app->env('REDIS_URL')) implements CacheInterface {
        public function __construct(private string $url) {}

        public function get(string $key, mixed $default = null): mixed {
            // Redis get implementation
        }

        public function set(string $key, mixed $value, ?int $ttl = null): static {
            // Redis set implementation
            return $this;
        }

        // Implement remaining CacheInterface methods...
    };
});
```

## Built-in Log Drivers

The stream driver logs to a file or stream like `php://stderr`:

```bash
LOG_DRIVER=stream
LOG_PATH=php://stderr
LOG_LEVEL=debug
```

```php
use Verge\Log\Drivers\StreamLogDriver;
use Verge\Log\LogLevel;

app()->driver('log', 'stream', fn(App $app) => new StreamLogDriver(
    $app->env('LOG_PATH', 'php://stderr'),
    LogLevel::from($app->env('LOG_LEVEL', 'debug'))
));
```

The array driver stores logs in memory, which is perfect for testing:

```bash
LOG_DRIVER=array
```

```php
use Verge\Log\Drivers\ArrayLogDriver;

app()->driver('log', 'array', fn() => new ArrayLogDriver());
```

## Changing the Default Driver

Override which driver gets used when no environment variable is set:

```php
app()->defaultDriver('cache', 'redis');  // Use redis if CACHE_DRIVER not set
app()->defaultDriver('log', 'array');    // Use array if LOG_DRIVER not set
```

## Organizing Drivers in Providers

Group driver configuration in service providers:

```php
namespace App\Providers;

use App\Cache\RedisCache;
use App\Log\DatabaseLogger;

class InfrastructureProvider
{
    public function __invoke(App $app): void
    {
        $app->driver('cache', 'redis', fn(App $app) => new RedisCache(
            $app->env('REDIS_URL')
        ));

        $app->driver('log', 'database', fn(App $app) => new DatabaseLogger(
            $app->make(Database::class)
        ));

        $app->defaultDriver('cache', 'redis');
        $app->defaultDriver('log', 'database');
    }
}
```

Load the provider in your bootstrap:

```php
use App\Providers\InfrastructureProvider;

app()->configure(InfrastructureProvider::class);
```

## Switching Drivers by Environment

Use different `.env` files for each environment:

```bash
# .env.development
CACHE_DRIVER=memory
LOG_DRIVER=stream
LOG_PATH=storage/logs/app.log

# .env.production
CACHE_DRIVER=redis
LOG_DRIVER=database
```

## Creating Drivers for Your Services

The driver system works for any service, not just cache and logging:

```php
use App\Queue\SyncQueue;
use App\Queue\RedisQueue;

app()
    ->driver('queue', 'sync', fn() => new SyncQueue())
    ->driver('queue', 'redis', fn(App $app) => new RedisQueue(
        $app->env('REDIS_URL')
    ))
    ->defaultDriver('queue', 'sync');

app()->post('/jobs', function() {
    $queue = app()->driver('queue');
    $queue->push('SendEmail', ['to' => 'user@example.com']);

    return ['queued' => true];
});
```

## Testing with Drivers

Override which driver gets used in your tests:

```php
use Verge\Log\Drivers\ArrayLogDriver;

it('logs messages', function() {
    putenv('LOG_DRIVER=array');
    $app = new App();

    $app->get('/action', function(Logger $logger) {
        $logger->info('Action performed');
        return 'ok';
    });

    $response = $app->test()->get('/action');

    expect($response->body())->toBe('ok');
    // Now you can inspect the ArrayLogDriver to verify logs
});
```
