---
title: Caching Data
description: Store and retrieve frequently accessed data to improve performance.
---

When you compute or fetch the same data repeatedly, cache it to avoid redundant work. Database queries, API responses, and expensive calculations are all good candidates.

## Avoiding Redundant Computation

The most common caching pattern is "get it from cache, or compute and store it." Type-hint `Cache` in your routes and use `remember()`:

```php
<?php

use Verge\Cache\Cache;

app()->get('/expensive', function(Cache $cache) {
    return $cache->remember('expensive-data', 3600, function() {
        // This only runs if the cache is empty
        return performExpensiveOperation();
    });
});
```

The callback only runs when the key is missing. The TTL is in seconds (3600 = 1 hour).

## Storing and Retrieving Values

When you need manual control over cache storage:

```php
$cache->set('key', 'value');           // Store indefinitely
$cache->set('key', 'value', 3600);     // Store for 1 hour
$cache->forever('key', 'value');       // Explicit indefinite storage
```

Retrieve with optional default values:

```php
$value = $cache->get('key');                // Returns null if missing
$value = $cache->get('key', 'default');     // With default value
```

## Checking What's Cached

Before computing expensive values, check if they're already cached:

```php
if ($cache->has('key')) {
    $value = $cache->get('key');
}

if ($cache->missing('key')) {
    $cache->set('key', computeValue());
}
```

Most of the time you'll use `remember()` instead, which handles this automatically.

## Caching Forever

Some data rarely changes and can be cached indefinitely:

```php
$config = $cache->rememberForever('app-config', fn() => loadConfig());
```

You'll need to manually invalidate these caches when the underlying data changes.

## Invalidating Cached Data

When data changes, remove it from the cache:

```php
$cache->forget('key');          // Remove single key
$cache->flush();                // Clear all cache
```

This is common after create, update, or delete operations:

```php
app()
    ->get('/products', fn(Cache $cache) =>
        $cache->remember('products', 600, fn() => Product::all())
    )
    ->post('/products', function(Request $req, Cache $cache) {
        $product = createProduct($req->json());
        $cache->forget('products');  // Invalidate cache
        return $product;
    });
```

## Using Values Once

Flash messages, one-time tokens, and temporary flags should be removed after reading. Use `pull()` to retrieve and delete in one operation:

```php
$token = $cache->pull('verification-token');            // Returns value and removes it
$message = $cache->pull('flash-message', 'default');    // With default
```

## Tracking Counters

Page views, rate limits, and other counters can be incremented atomically:

```php
$cache->set('page-views', 0);
$cache->increment('page-views');        // Now 1
$cache->increment('page-views', 5);     // Now 6
$cache->decrement('page-views', 2);     // Now 4
```

The cache driver handles this without race conditions.

## Working with Multiple Keys

Batch operations are more efficient when you need multiple cached values:

```php
// Get many
$values = $cache->many(['key1', 'key2', 'key3'], 'default');
// ['key1' => 'value1', 'key2' => 'default', 'key3' => 'value3']

// Set many
$cache->setMany([
    'key1' => 'value1',
    'key2' => 'value2',
], 3600);
```

## Caching Database Queries

Database queries are the most common thing to cache:

```php
app()->get('/users', function(Cache $cache, UserRepository $users) {
    return $cache->remember('all-users', 300, fn() => $users->all());
});
```

Remember to invalidate when data changes:

```php
app()->post('/users', function(Request $req, Cache $cache, UserRepository $users) {
    $user = $users->create($req->json());
    $cache->forget('all-users');
    return $user;
});
```

## Caching External API Responses

Third-party APIs are slow and often rate-limited. Cache their responses:

```php
app()->get('/weather', function(Cache $cache, HttpClient $http) {
    return $cache->remember('weather-data', 1800, function() use ($http) {
        return $http->get('https://api.weather.com/forecast');
    });
});
```

30 minutes (1800 seconds) is a reasonable default for most external APIs.

## Configuring Cache Drivers

Verge uses a driver system to swap cache backends. Set `CACHE_DRIVER` in your environment:

```bash
# .env
CACHE_DRIVER=memory
```

The memory driver (default) stores data in memory for the current request or process. For production, you'll want a persistent driver like Redis.

### Adding Custom Drivers

Register your own cache implementation:

```php
<?php

use App\Cache\RedisCache;
use Verge\App;

app()->driver('cache', 'redis', function(App $app) {
    return new RedisCache($app->env('REDIS_URL'));
});
```

Then set `CACHE_DRIVER=redis` in your environment. See [Configuring Drivers](/guides/configuring-drivers/) for complete documentation on the driver system.
