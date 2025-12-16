---
title: Bootstrap Caching
description: Warm caches for production to eliminate cold start overhead.
---

When you deploy to production, Verge can pre-warm its route and container caches. This eliminates startup time—your first request is as fast as your hundredth.

## Why Cache Matters

Every time your app boots, it needs to:

1. Register all routes
2. Analyze constructor parameters for dependency injection
3. Build route matching patterns

In development this overhead is fine. In production, especially on serverless or edge platforms, you want instant cold starts. Bootstrap caching solves this by serializing routes and reflection metadata to PHP files.

## Enabling the Cache Module

Add the `BootstrapCache` module to your app:

```php
<?php

use Verge\Bootstrap\BootstrapCache;

app()->module(BootstrapCache::class);
```

By default, caching only activates when `APP_ENV=production` or `APP_ENV=prod`. In development mode, the module does nothing—routes and container work normally.

## Warming the Cache

Before your production app handles its first request, warm the cache:

```bash
./bin/verge cache:warm
```

This creates two files in `bootstrap/cache/`:

- `routes.php` - Compiled route definitions
- `container.php` - Constructor parameter metadata

Once warmed, Verge loads routes from the cached PHP file instead of re-registering them on every boot.

## Deployment Workflow

1. Deploy your code with `APP_ENV=production`
2. Run `./bin/verge cache:warm` after deployment
3. Restart your app server or workers

Subsequent requests skip route registration entirely and load from cache.

## When Routes Change

Anytime you modify routes, re-warm the cache:

```bash
./bin/verge cache:clear
./bin/verge cache:warm
```

Or in one command:

```bash
./bin/verge cache:clear && ./bin/verge cache:warm
```

## What Gets Cached

Bootstrap caching serializes two things:

**Route definitions** - Every route's HTTP method, pattern, handler, middleware, and name. When the cache is warm, the `CachedRouter` loads these instantly.

**Container reflection** - Constructor parameter types and names. This lets the container resolve dependencies without reflection on every request.

**What doesn't get cached** - Middleware logic, handlers, and application code still run normally. Only the metadata about routes and constructors is pre-computed.

## Custom Cache Directory

Change where cache files are stored:

```php
<?php

use Verge\Bootstrap\BootstrapCache;

app()->module(new BootstrapCache(
    path: __DIR__ . '/storage/framework/cache',
));
```

Or set the `VERGE_CACHE_PATH` environment variable:

```bash
# .env
VERGE_CACHE_PATH=/var/cache/verge
```

## Force-Enabling in Other Environments

The cache module respects `APP_ENV` by default, but you can override:

```php
<?php

use Verge\Bootstrap\BootstrapCache;

app()->module(new BootstrapCache(
    enabled: true,  // Always cache, regardless of APP_ENV
));
```

This is useful for staging environments where you want production-like performance.

## Checking Cache Status

Inspect the current cache state:

```php
<?php

use Verge\Bootstrap\BootstrapCache;

$cache = app()->make(BootstrapCache::class);
$status = $cache->status();

// Returns:
// [
//     'enabled' => true,
//     'path' => '/app/bootstrap/cache',
//     'routes' => ['exists' => true, 'size' => 4096],
//     'container' => ['exists' => true, 'size' => 2048],
// ]
```

This is helpful for health checks or debugging deployment issues.

## How CachedRouter Works

When the cache is warm, Verge swaps the normal `Router` with a `CachedRouter`. This router:

- Loads route definitions from the PHP file
- Implements `match()`, `url()`, and `getRoutes()`
- **Cannot register new routes** after cache is loaded

This means you can't conditionally register routes at runtime when caching is enabled. All routes must be registered during the normal app boot process, before the cache is warmed.

## Edge and Serverless Deployments

Bootstrap caching is especially valuable on platforms with aggressive cold starts:

- AWS Lambda
- Cloudflare Workers (with PHP)
- Vercel Serverless Functions
- Google Cloud Run

Combine this with [FrankenPHP worker mode](/guides/deploying-frankenphp/) for maximum performance: boot once, warm the cache, and handle thousands of requests without re-initializing.

## Development vs Production

In development, you want fast iteration. Verge doesn't cache by default—register routes freely, and see changes immediately.

In production, you want fast requests. Set `APP_ENV=production`, warm the cache, and every request benefits from pre-compiled metadata.

The best part? You don't change your application code at all. The same route definitions work in both modes.
