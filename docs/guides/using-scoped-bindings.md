---
title: Preventing Memory Leaks with Scoped Bindings
description: Cache instances per-request in long-running workers to prevent data leaking between requests.
---

When you deploy to FrankenPHP or other long-running workers, your app boots once and handles thousands of requests. You need some state to persist across requests and other state to reset.

## Avoiding Data Leakage

If you use `singleton()` for request-specific state, the same instance lives across all requests:

```php
app()->singleton(RequestContext::class, fn() => new RequestContext());
```

This causes data from request A to appear in request B—a serious bug.

## Caching Per-Request with scoped()

Scoped bindings cache for the lifetime of one request, then reset:

```php
app()->scoped(RequestContext::class, fn() => new RequestContext());
```

Within a single request, you get the same instance every time. The next request gets a fresh instance.

## Choosing the Right Lifetime

| Method | Creates new instance? | Persists across requests? |
|--------|----------------------|---------------------------|
| `bind()` | Every time | No (because it's always new) |
| `singleton()` | Once, forever | Yes |
| `scoped()` | Once per request | No |

## Services That Should Be Scoped

Anything specific to the current request needs scoping:

```php
app()
    ->scoped(RequestContext::class, fn() => new RequestContext())
    ->scoped(UserSession::class, fn() => new UserSession())
    ->scoped(RequestLogger::class, fn() => new RequestLogger());
```

## Services That Should Be Singletons

Expensive resources that can safely be shared need singletons:

```php
app()
    ->singleton(DB::class, fn() => new DB(app()->env('DATABASE_URL')))
    ->singleton(CacheInterface::class, fn() => new RedisCache(app()->env('REDIS_URL')))
    ->singleton(Config::class, fn() => new Config());
```

## Traditional PHP Mode

In traditional PHP deployment (Apache, PHP-FPM), the process dies after each request. There's no practical difference between `singleton()` and `scoped()` because everything resets anyway.

Still, use `scoped()` for request-specific state. It documents your intent and makes the app safe for worker deployment later.
