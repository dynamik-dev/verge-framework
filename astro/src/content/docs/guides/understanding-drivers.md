---
title: The Driver Pattern
description: How Verge uses the driver pattern as a first-class architectural feature for building swappable services.
---

The driver pattern is baked into Verge as a first-class citizen. It lets you build services with multiple implementations and switch between them using environment variables, without changing your code.

## What Are Drivers?

Drivers are interchangeable implementations of a service. A single service like "cache" can have multiple drivers like "memory", "redis", or "file". Your code works with the service interface, and the framework resolves the configured driver at runtime.

```php
// Your code never knows which driver is active
app()->get('/data', function(Cache $cache) {
    $cache->set('key', 'value');  // Uses configured driver
    return $cache->get('key');
});
```

The `CACHE_DRIVER` environment variable controls which implementation runs. Change it from `memory` to `redis` without touching your code.

## Why Drivers Matter

Drivers solve real problems you face every day:

**Development vs Production** — Use fast in-memory cache during development, switch to Redis in production. Same code, different behavior.

**Testing** — Swap real services for test doubles that let you verify behavior without side effects.

**Flexibility** — Add new implementations without rewriting existing code. Your app depends on interfaces, not concrete classes.

**Configuration** — Control infrastructure through environment variables, not code changes.

## How the Driver System Works

The framework maintains a registry of driver factories for each service. When you request a driver, it:

1. Reads the `{SERVICE}_DRIVER` environment variable
2. Looks up the factory function for that driver
3. Calls the factory to create the driver instance
4. Returns the instance to your code

```php
// Register a driver factory
app()->driver('cache', 'redis', function(App $app) {
    return new RedisCache($app->env('REDIS_URL'));
});

// Get the configured driver (reads CACHE_DRIVER env var)
$cache = app()->driver('cache');
```

## Registering Drivers

Use the `driver()` method to register a new driver implementation:

```php
app()->driver(
    service: 'cache',
    name: 'redis',
    factory: fn(App $app) => new RedisCache($app->env('REDIS_URL'))
);
```

The factory receives the app instance, giving you access to the container, environment variables, and other services.

## Creating Your Own Services with Drivers

The driver pattern isn't limited to cache and logging. Build your own services with driver support:

```php
// Define your service interface
interface QueueInterface
{
    public function push(string $job, array $data): void;
    public function pop(): ?array;
}

// Register drivers for your service
app()
    ->driver('queue', 'sync', fn() => new SyncQueue())
    ->driver('queue', 'redis', fn(App $app) => new RedisQueue(
        $app->env('REDIS_URL')
    ))
    ->driver('queue', 'sqs', fn(App $app) => new SqsQueue(
        $app->env('AWS_KEY'),
        $app->env('AWS_SECRET')
    ))
    ->defaultDriver('queue', 'sync');

// Use your service
app()->post('/jobs', function() {
    $queue = app()->driver('queue');  // Reads QUEUE_DRIVER
    $queue->push('SendEmail', ['to' => 'user@example.com']);

    return ['queued' => true];
});
```

Set `QUEUE_DRIVER=redis` in production, `QUEUE_DRIVER=sync` in development.

## Binding Drivers to the Container

Connect your driver to dependency injection by binding the service interface:

```php
app()->bind(QueueInterface::class, function(App $app) {
    return $app->driver('queue');
});
```

Now you can inject the interface directly:

```php
app()->post('/jobs', function(QueueInterface $queue) {
    $queue->push('SendEmail', ['to' => 'user@example.com']);
    return ['queued' => true];
});
```

The container resolves the interface by reading `QUEUE_DRIVER` and returning that driver's instance.

## Setting Default Drivers

Specify which driver to use when no environment variable is set:

```php
app()->defaultDriver('queue', 'sync');
```

Without this, requesting a driver when `{SERVICE}_DRIVER` isn't set throws an exception.

## Driver Factories and Dependencies

Driver factories can resolve dependencies from the container:

```php
app()->driver('queue', 'database', function(App $app) {
    return new DatabaseQueue(
        $app->make(Database::class),
        $app->env('QUEUE_TABLE', 'jobs')
    );
});
```

The factory runs when the driver is first requested, not at registration time. This lets you use services that haven't been bound yet.

## Organizing Drivers in Providers

Group related driver registrations in service providers:

```php
namespace App\Providers;

use App\Queue\SyncQueue;
use App\Queue\RedisQueue;
use App\Queue\SqsQueue;

class QueueServiceProvider
{
    public function __invoke(App $app): void
    {
        $app->driver('queue', 'sync', fn() => new SyncQueue());

        $app->driver('queue', 'redis', fn(App $app) => new RedisQueue(
            $app->env('REDIS_URL')
        ));

        $app->driver('queue', 'sqs', fn(App $app) => new SqsQueue(
            $app->env('AWS_KEY'),
            $app->env('AWS_SECRET'),
            $app->env('AWS_REGION')
        ));

        $app->defaultDriver('queue', 'sync');

        $app->bind(QueueInterface::class, fn(App $app) => $app->driver('queue'));
    }
}
```

Load the provider in your bootstrap:

```php
app()->configure(QueueServiceProvider::class);
```

## Built-in Services Using Drivers

Verge uses drivers for its own services:

**Cache** — Memory driver included, add Redis, Memcached, or file-based drivers as needed.

**Logging** — Stream driver (files/stderr) and array driver (testing) included.

Your custom services work the same way these built-in services do. The driver system is a general-purpose pattern, not special infrastructure for framework internals.

## Testing with Drivers

Override drivers in tests without changing your code:

```php
it('queues emails', function() {
    $queue = new FakeQueue();
    app()->driver('queue', 'fake', fn() => $queue);
    putenv('QUEUE_DRIVER=fake');

    $response = app()->test()->post('/send-email', [
        'to' => 'user@example.com'
    ]);

    expect($queue->pushed())->toHaveCount(1);
});
```

## When to Use Drivers

Use the driver pattern when:

- You need to swap implementations based on environment
- Different environments require different infrastructure
- You want to provide multiple implementations of a service
- You're building a package that others will extend

Don't use drivers when:

- You have only one implementation and won't add more
- The service doesn't vary by environment
- Simpler patterns like direct instantiation work fine

Drivers add flexibility at the cost of indirection. Use them when the flexibility matters.
