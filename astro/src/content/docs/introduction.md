---
title: Introduction
description: Understanding the philosophy and design principles behind Verge
---

Verge is a minimal PHP framework for building web applications and APIs. If you've worked with Hono.js, Express, or similar JavaScript frameworks, you'll feel right at home.

## Philosophy

Verge is built around five core principles that guide every design decision:

### Minimal

Every feature exists to solve a real problem, not hypothetical ones. Before adding anything to the framework, we ask: "Can this be done with what we already have?" If the answer is yes, we don't add it.

This keeps the framework small and the learning curve gentle. You won't wade through dozens of utility classes or configuration options you'll never use.

### Explicit

No magic. No auto-discovery. No surprises.

When you register a route, bind a service, or attach middleware, you do it explicitly. Your code shows exactly what your application does. Six months from now, you'll still understand it.

### Fluent

Where it makes sense, Verge provides chainable APIs that read naturally:

```php
$app->group('/api', function ($api) {
    $api->use(AuthMiddleware::class)
        ->get('/users', UserController::class);
});
```

But fluency never comes at the cost of clarity. If an operation doesn't naturally chain, we don't force it.

### Familiar

If you've built applications with modern JavaScript frameworks, Verge should feel intuitive. Routes, middleware, and responses work the way you'd expect:

```php
$app->get('/users/{id}', function ($id) {
    return ['id' => $id, 'name' => 'Taylor'];
});
```

No ceremony. No boilerplate. Just the code that matters.

### Composable

The framework's features work together seamlessly. The container, router, middleware system, and event dispatcher are designed to complement each other.

Need to add authentication? Attach middleware. Need to log requests? Listen to events. Need to swap cache backends? Register a different driver. Everything composes.

## Core Primitives

Verge provides four core primitives that serve as building blocks for everything else:

| Primitive | Purpose | Extend via |
|-----------|---------|------------|
| Container | Dependency injection and service resolution | `bind()`, `singleton()`, `scoped()` |
| Router | Maps URLs to handlers | `get()`, `post()`, `group()` |
| Middleware | Request/response pipeline | `use()` |
| Events | Lifecycle hooks and decoupling | `on()`, `emit()` |

These aren't just features of the framework—they're your toolkit. Before reaching for a new abstraction, ask yourself if one of these primitives can solve your problem.

## The Lego Blocks Approach

Think of Verge's primitives as Lego blocks. You don't need special pieces to build something new—you compose what's already there.

Need to register routes after all your modules are loaded? Use events:

```php
$app->module(function (App $app) {
    // Bind services immediately
    $app->singleton(MyService::class, fn() => new MyService());

    // Defer route registration until ready
    $app->on('app.ready', function () use ($app) {
        $app->get('/my-route', MyController::class);
    });
});
```

Need request logging? Use middleware. Need to vary behavior based on environment? Use the container. The primitives combine to solve complex problems without adding framework complexity.

## Module-Based Design

Everything in Verge is organized into modules. A module is just a callable that receives the application instance and registers services, routes, middleware, or event listeners:

```php
class MyModule
{
    public function __invoke(App $app): void
    {
        // Register services
        $app->singleton(MyService::class, fn() => new MyService());

        // Register routes
        $app->get('/my-route', MyController::class);

        // Attach middleware
        $app->use(MyMiddleware::class);

        // Listen to events
        $app->on('app.ready', function () {
            // Do something when the app is ready
        });
    }
}
```

Modules are Verge's unit of composition. They encapsulate related functionality and make it easy to add or remove features. Every built-in feature—routing, caching, logging—is implemented as a module.

### Registering Modules

You register modules by passing them to `configure()`:

```php
$app = new App();

$app->configure([
    EnvModule::class,
    ConfigModule::class,
    RoutingModule::class,
    MyModule::class,
]);
```

Or use the `module()` method for inline registration:

```php
$app->module(function (App $app) {
    $app->singleton(EmailService::class, fn() => new EmailService());
});
```

### Module Structure

Most feature domains follow a consistent structure:

```
src/Cache/
├── Cache.php           # Main implementation
├── CacheInterface.php  # Interface (if needed)
├── CacheModule.php     # Registers the service
└── Drivers/            # Swappable backends
```

The module handles all the registration ceremony, so the rest of your application just works with clean interfaces.

### Deferred Registration

Sometimes you need to register something after all modules have loaded. This is common when you're building a package that needs to inspect or modify what other modules have registered.

Use the `app.ready` event for deferred registration:

```php
class SwaggerModule
{
    public function __invoke(App $app): void
    {
        $app->singleton(SwaggerGenerator::class, fn() => new SwaggerGenerator());

        $app->on('app.ready', function () use ($app) {
            // All routes are now registered, safe to generate docs
            $app->get('/swagger.json', function (SwaggerGenerator $gen) {
                return $gen->generate($app);
            });
        });
    }
}
```

The event fires after `configure()` completes but before the first request is handled. It's your hook for any setup that needs the full application context.

## Container-Focused Design

The dependency injection container is at the heart of Verge. It manages how your application's services are created, shared, and injected.

### Predictable Dependency Injection

The container auto-wires dependencies through constructor injection. When you resolve a class, the container inspects its constructor, resolves each dependency, and injects them automatically:

```php
class UserController
{
    public function __construct(
        private UserRepository $users,
        private Logger $logger
    ) {}
}

// Container automatically resolves and injects dependencies
$controller = $app->make(UserController::class);
```

You can also bind services explicitly when you need custom instantiation:

```php
$app->singleton(UserRepository::class, fn() => new UserRepository($config));
```

### Binding Strategies

Verge provides three binding strategies to control service lifetimes:

```php
// Singleton - shared across all requests (in long-running processes)
$app->singleton(Database::class, fn() => new Database());

// Scoped - one instance per request
$app->scoped(Logger::class, fn() => new Logger());

// Transient - new instance every time
$app->bind(EmailMessage::class, fn() => new EmailMessage());
```

Choose the strategy that matches your service's needs. Stateful services like database connections should be singletons or scoped. Stateless value objects can be transient.

### The Driver Pattern

Some services need swappable backends—think cache drivers (memory, file, Redis) or log drivers (stream, syslog). Verge uses a driver pattern for this:

```php
// Register drivers
$app->driver('cache', 'memory', fn() => new MemoryCacheDriver());
$app->driver('cache', 'file', fn() => new FileCacheDriver());

// Set default
$app->defaultDriver('cache', 'memory');

// Resolve the configured driver (reads CACHE_DRIVER env var)
$cache = $app->driver('cache');
```

Users configure which driver to use via environment variables. Your code works with a consistent interface regardless of the backend.

### How the Primitives Work Together

The container doesn't exist in isolation—it powers the entire framework:

- **Routes** resolve handlers through the container, enabling dependency injection in controllers
- **Middleware** is instantiated via the container, allowing middleware to depend on services
- **Events** listeners can be classes resolved from the container
- **Drivers** are registered and resolved through container methods

Everything flows through the container. This gives you a single, consistent way to manage dependencies across your entire application.

## What This Means for You

When you work with Verge, you're working with a small set of primitives that combine in powerful ways. There's no sprawling feature set to learn—just a container, router, middleware system, and event dispatcher.

Need to add a feature? Reach for a primitive. Need to customize behavior? Compose primitives. Need to extend the framework? Write a module.

This keeps your application code focused on solving your actual problems, not learning framework abstractions.
