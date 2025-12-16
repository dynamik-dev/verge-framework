---
title: Organizing Bindings with Providers
description: Group related container bindings into provider classes to keep your bootstrap code clean.
---

Once you have more than a handful of bindings, your bootstrap file gets messy. Service providers let you group related bindings into focused classes.

## Writing a Provider Class

A provider is just an invokable class that receives the app instance:

```php
class DatabaseProvider
{
    public function __invoke(App $app): void
    {
        $app->singleton(DB::class, fn() => new DB($app->env('DATABASE_URL')));
        $app->bind(UserRepository::class, PostgresUserRepository::class);
        $app->bind(PostRepository::class, PostgresPostRepository::class);
    }
}
```

## Registering Providers

Pass provider class names to `configure()`:

```php
app()
    ->configure(DatabaseProvider::class)
    ->configure(AuthProvider::class)
    ->configure(PaymentProvider::class);
```

Providers run in the order you register them.

## Using Dependencies in Providers

Providers resolve through the container, so type-hint whatever you need:

```php
class PaymentProvider
{
    public function __construct(private Env $env) {}

    public function __invoke(App $app): void
    {
        $app->singleton(PaymentGateway::class, fn() => new Stripe(
            $this->env->get('STRIPE_SECRET')
        ));
    }
}
```

## Using Closures for Simple Configuration

For one-off configuration that doesn't need a full class, pass a closure:

```php
app()->configure(function($app) {
    $app->singleton(DB::class, fn() => new DB($app->env('DATABASE_URL')));
});
```

Or require a file that returns a closure:

```php
// config/services.php
return function(App $app) {
    $app->singleton(DB::class, fn() => new DB($app->env('DATABASE_URL')));
    $app->bind(UserRepository::class, PostgresUserRepository::class);
};
```

```php
// index.php
app()->configure(require 'config/services.php');
```

## Structuring a Larger App

Here's how you might organize providers in a real application:

```
project/
├── index.php
├── src/
│   └── Providers/
│       ├── DatabaseProvider.php
│       ├── AuthProvider.php
│       └── PaymentProvider.php
└── routes/
    └── api.php
```

```php
// index.php
require 'vendor/autoload.php';

app()
    ->configure(DatabaseProvider::class)
    ->configure(AuthProvider::class)
    ->configure(PaymentProvider::class);

require 'routes/api.php';

app()->run();
```
