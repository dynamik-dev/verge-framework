---
title: Deploying with FrankenPHP
description: Deploy Verge apps in worker mode for maximum performance.
---

FrankenPHP's worker mode boots your app once and handles many requests. Verge is optimized for this.

## Dockerfile

```dockerfile
FROM dunglas/frankenphp

COPY . /app

WORKDIR /app

RUN composer install --no-dev --optimize-autoloader

ENTRYPOINT ["frankenphp", "php-server", "--worker", "index.php"]
```

## How Worker Mode Works

1. Your app boots once (routes register, bindings resolve)
2. FrankenPHP handles incoming requests
3. Each request runs through your registered routes
4. Scoped bindings reset between requests
5. Singletons persist across requests

## Building and Running

```bash
docker build -t my-verge-app .
docker run -p 8080:8080 my-verge-app
```

## Development Mode

For development without worker mode:

```bash
frankenphp php-server index.php
```

Or use PHP's built-in server:

```bash
php -S localhost:8000 index.php
```

## Environment Variables

Pass environment variables to Docker:

```bash
docker run -p 8080:8080 \
  -e DATABASE_URL=postgres://... \
  -e REDIS_URL=redis://... \
  my-verge-app
```

## Pre-Warming Caches

For maximum performance, pre-warm your route and container caches before starting:

```dockerfile
FROM dunglas/frankenphp

COPY . /app

WORKDIR /app

RUN composer install --no-dev --optimize-autoloader
RUN php bin/verge cache:warm

ENTRYPOINT ["frankenphp", "php-server", "--worker", "index.php"]
```

This serializes route definitions and constructor metadata so the first request is instant. See [Bootstrap Caching](/guides/bootstrap-caching/) for details.

## How Worker Mode Works

Verge takes advantage of worker mode automatically:

- Singletons are created once and reused
- Routes load from pre-warmed cache
- Container reflection data is pre-compiled
- Only scoped bindings reset per request

No configuration needed—just use `singleton()` for expensive resources and `scoped()` for request-specific state.

## Health Checks

Always include a health endpoint:

```php
app()->get('/health', fn() => ['status' => 'ok']);
```

This lets orchestrators verify your app is running.
