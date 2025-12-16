---
title: Reading Environment Variables
description: Access configuration and secrets through environment variables.
---

Keep sensitive data and environment-specific settings out of your code by reading them from environment variables at runtime.

## Reading from the App Instance

Pull values directly from the app when bootstrapping your application:

```php
$databaseUrl = app()->env('DATABASE_URL');
$debug = app()->env('DEBUG', false);  // Falls back to false if not set
```

## Reading in Route Handlers

Type-hint `Env` in any route handler to access environment variables:

```php
app()->get('/status', function(Env $env) {
    return [
        'region' => $env->get('FLY_REGION'),
        'debug' => $env->get('DEBUG', false),
    ];
});
```

The container automatically injects the `Env` instance when resolving your handler.

## Reading in Service Providers

Service providers receive `Env` through dependency injection just like any other service:

```php
class DatabaseProvider
{
    public function __construct(private Env $env) {}

    public function __invoke(App $app): void
    {
        $app->singleton(DB::class, fn() => new DB(
            $this->env->get('DATABASE_URL')
        ));
    }
}
```

## Reading in Container Bindings

Access env vars through `app()->env()` when registering bindings:

```php
app()->singleton(DB::class, fn() => new DB(
    app()->env('DATABASE_URL')
));

app()->singleton(Cache::class, fn() => new RedisCache(
    app()->env('REDIS_URL'),
    app()->env('CACHE_PREFIX', 'app')
));
```

## Local Development Setup

Create a `.env` file in your project root for local development:

```
DATABASE_URL=postgres://localhost/myapp
REDIS_URL=redis://localhost:6379
DEBUG=true
```

Also create `.env.example` to document what variables your app needs:

```
DATABASE_URL=
REDIS_URL=
DEBUG=false
```

Add `.env` to your `.gitignore` so secrets don't get committed. Commit `.env.example` so other developers know what to configure.

## Setting Variables in Production

Your hosting platform should provide a way to set environment variables securely:

```bash
# Fly.io
fly secrets set DATABASE_URL=postgres://...

# Docker
docker run -e DATABASE_URL=postgres://... myapp

# Traditional hosting
export DATABASE_URL=postgres://...
```

Never hardcode production credentials or put them in `.env` files that might get committed.
