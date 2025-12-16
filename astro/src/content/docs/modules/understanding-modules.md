---
title: Understanding Modules
description: Modules are the foundational building block of Verge applications
---

Modules are how you organize and register functionality in Verge. Every feature in the framework—routing, caching, logging, events—is implemented as a module. Your application code uses the same pattern.

## What is a Module?

A module is simply a callable that receives the `App` instance. That's it. No interfaces to implement, no base classes to extend:

```php
// A module as a closure
$app->module(function (App $app) {
    $app->singleton(UserRepository::class, fn() => new UserRepository());
    $app->get('/users', UserController::class);
});

// A module as a class
class UserModule
{
    public function __invoke(App $app): void
    {
        $app->singleton(UserRepository::class, fn() => new UserRepository());
        $app->get('/users', UserController::class);
    }
}

$app->module(UserModule::class);
```

Both approaches are equivalent. Use closures for quick inline registration. Use classes when you want reusable, testable modules.

## Why Modules?

Modules solve several problems at once:

### Organization

Instead of scattering service bindings, routes, and middleware across your codebase, modules group related functionality together. A `PaymentModule` contains everything about payments—services, routes, event listeners.

### Composability

Modules compose naturally. You can build an application by combining modules:

```php
$app->module([
    AuthModule::class,
    UserModule::class,
    PaymentModule::class,
    NotificationModule::class,
]);
```

Each module is self-contained. Add or remove features by adding or removing modules.

### Explicit Registration

There's no auto-discovery. Your application only includes what you explicitly register. This makes the bootstrap process predictable—you can read the module list and know exactly what's in your application.

## What Modules Can Do

Modules have access to the full `App` API. They can:

**Register services:**
```php
$app->singleton(PaymentGateway::class, fn() => new StripeGateway());
$app->bind(PaymentProcessor::class, fn() => new PaymentProcessor());
```

**Define routes:**
```php
$app->get('/checkout', CheckoutController::class);
$app->post('/webhook/stripe', StripeWebhookController::class);
```

**Attach middleware:**
```php
$app->use(CorsMiddleware::class);
```

**Register event listeners:**
```php
$app->on('order.placed', SendConfirmationEmail::class);
$app->on('payment.failed', NotifyAdminOnFailure::class);
```

**Register drivers:**
```php
$app->driver('payment', 'stripe', fn() => new StripeGateway());
$app->driver('payment', 'paypal', fn() => new PayPalGateway());
$app->defaultDriver('payment', 'stripe');
```

**Configure other modules:**
```php
$app->ready(function() use ($app) {
    // Runs after all modules are loaded
    $app->get('/admin/routes', fn() => $app->routes());
});
```

## Module Loading Order

Modules execute in the order you register them. This matters when modules depend on each other:

```php
$app->module([
    EnvModule::class,      // First: loads environment variables
    ConfigModule::class,   // Second: uses env to build config
    DatabaseModule::class, // Third: uses config for connection
]);
```

If `DatabaseModule` runs before `ConfigModule`, it won't have access to configuration values. Order your modules so dependencies are registered before dependents.

## The Lego Blocks Philosophy

Verge provides four core primitives: Container, Router, Middleware, and Events. Modules use these primitives to build features.

Before creating a new abstraction, ask yourself:

- Can I do this with a container binding?
- Can I do this with middleware?
- Can I do this with an event listener?
- Can I compose existing primitives?

Most problems can be solved by combining what's already there. A module is just the place where that composition happens.

```php
class RateLimitModule
{
    public function __invoke(App $app): void
    {
        // Use container for the limiter service
        $app->singleton(RateLimiter::class, fn() => new RateLimiter());

        // Use middleware to enforce limits
        $app->use(RateLimitMiddleware::class);

        // Use events for monitoring
        $app->on('rate.exceeded', LogRateLimitExceeded::class);
    }
}
```

Three primitives, one cohesive feature. That's the Verge way.
