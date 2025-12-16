---
title: Container Methods
description: Complete reference for dependency injection container methods.
---

## Binding Methods

| Method | New instance each call? | Cleared between requests? |
|--------|------------------------|---------------------------|
| `bind()` | Yes | N/A |
| `singleton()` | No | No |
| `scoped()` | No | Yes |
| `instance()` | No | No |
| `for()` | (modifier) | (modifier) |

The `for()` method is a modifier—call it after `bind()`, `singleton()`, or `instance()` to make that binding contextual.

## bind()

Create a new instance each time:

```php
app()->bind(Logger::class, fn() => new Logger());
app()->bind(UserRepository::class, PostgresUserRepository::class);
```

## singleton()

Cache instance forever (across all requests in worker mode):

```php
app()->singleton(DB::class, fn() => new DB(app()->env('DATABASE_URL')));
```

## scoped()

Cache per-request, reset between requests:

```php
app()->scoped(RequestContext::class, fn() => new RequestContext());
```

## instance()

Store an existing value directly:

```php
$config = ['debug' => true];
app()->instance('config', $config);
```

## for()

Make the preceding binding contextual for specific classes:

```php
app()
    ->bind(CacheInterface::class, RedisCache::class)
    ->for(UserService::class);

app()
    ->bind(CacheInterface::class, MemoryCache::class)
    ->for([SessionManager::class, TokenService::class]);
```

When `UserService` is resolved, it receives `RedisCache`. When `SessionManager` or `TokenService` are resolved, they receive `MemoryCache`.

## Resolution Methods

| Method | Description |
|--------|-------------|
| `make($abstract, $params?)` | Resolve from container |
| `get($abstract)` | PSR-11 alias for `make()` |
| `has($abstract)` | Check if bound |

## make()

Resolve with optional parameters:

```php
$users = app()->make(UserService::class);
$report = app()->make(ReportGenerator::class, ['format' => 'pdf']);
```

## has()

Check binding exists:

```php
if (app()->has(CacheInterface::class)) {
    $cache = app()->make(CacheInterface::class);
}
```

## Global Helper

```php
use function Verge\make;

$users = make(UserService::class);
$report = make(ReportGenerator::class, ['format' => 'pdf']);
```
