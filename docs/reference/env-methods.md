---
title: Env Methods
description: Complete reference for environment variable access.
---

## Core Methods

| Method | Description |
|--------|-------------|
| `$env->get($key, $default)` | Get environment variable with type coercion |
| `$env->has($key)` | Check if variable exists |
| `$env->set($key, $value)` | Set environment variable |

## Type Coercion

The `get()` method automatically converts string values:

| String Value | Converted To |
|-------------|--------------|
| `'true'` | `true` (boolean) |
| `'false'` | `false` (boolean) |
| `'null'` | `null` |
| `'empty'` | `''` (empty string) |
| Other values | Returned as-is (string) |

## Access via App

The `app()` singleton provides direct access to environment variables:

```php
$debug = app()->env('DEBUG', false);
$databaseUrl = app()->env('DATABASE_URL');
```

## Examples

### Basic Usage

```php
app()->get('/config', function(Env $env) {
    return [
        'debug' => $env->get('DEBUG', false),
        'region' => $env->get('FLY_REGION', 'unknown'),
    ];
});
```

### In Providers

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

### In Bindings

```php
app()->singleton(DB::class, fn() => new DB(
    app()->env('DATABASE_URL')
));

app()->singleton(Cache::class, fn() => new RedisCache(
    app()->env('REDIS_URL'),
    app()->env('CACHE_PREFIX', 'app')
));
```

### Checking Existence

```php
if ($env->has('STRIPE_KEY')) {
    $stripe = new StripeClient($env->get('STRIPE_KEY'));
}
```

### Setting Values (for testing)

```php
$env->set('APP_ENV', 'testing');
$env->set('DEBUG', 'true');
```

## Type Coercion Examples

```php
// .env file:
// DEBUG=true
// WORKERS=5
// API_KEY=secret

$debug = $env->get('DEBUG');    // true (boolean, not string)
$workers = $env->get('WORKERS'); // '5' (string)
$apiKey = $env->get('API_KEY');  // 'secret' (string)
```
