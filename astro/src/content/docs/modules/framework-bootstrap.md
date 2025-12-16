---
title: Framework Bootstrap
description: How Verge boots itself using modules
---

Verge uses its own module system to bootstrap the framework. When you create a new `App` instance, you're already running modules—this is how the framework eats its own dog food.

## The Bootstrap Process

When you instantiate `App`, this happens:

```php
$app = new App();
```

1. A new container is created
2. The `App` instance is registered in the container
3. `AppBuilder` is invoked to load framework modules

That's it. The framework is just a collection of modules registered on the container.

## AppBuilder

The `AppBuilder` class is Verge's internal bootstrap module. Here's what it looks like:

```php
class AppBuilder
{
    public function __invoke(App $app): void
    {
        $app->module([
            EnvModule::class,
            ConfigModule::class,
            RoutingModule::class,
            HttpModule::class,
            EventsModule::class,
            CacheModule::class,
            LogModule::class,
            ClockModule::class,
            ConsoleModule::class,
        ]);
    }
}
```

Each module registers one piece of framework functionality. They run in order, with each building on what came before.

## Framework Modules

Here's what each framework module does:

### EnvModule

Loads environment variables and registers the `Env` service:

```php
$app->singleton(Env::class, fn() => new Env());
$app->bind(EnvInterface::class, fn($app) => $app->make(Env::class));
```

This runs first because other modules need access to environment variables.

### ConfigModule

Builds application configuration from environment and config files:

```php
$app->singleton(Config::class, fn() => new Config());
```

Depends on `EnvModule` for reading configuration from environment variables.

### RoutingModule

Registers the router for URL-to-handler mapping:

```php
$app->singleton(Router::class, fn() => new Router());
$app->bind(RouterInterface::class, fn($app) => $app->make(Router::class));
```

### HttpModule

Provides HTTP factories and the request handler:

```php
$app->singleton(HttpFactory::class, fn() => new HttpFactory());
$app->bind(RequestHandlerInterface::class, fn($app) => new RequestHandler($app));
```

### EventsModule

Registers the event dispatcher:

```php
$app->singleton(EventDispatcher::class, fn() => new EventDispatcher());
$app->bind(EventDispatcherInterface::class, fn($app) => $app->make(EventDispatcher::class));
```

### CacheModule

Sets up cache drivers:

```php
$app->driver('cache', 'memory', fn() => new MemoryCacheDriver());
$app->defaultDriver('cache', 'memory');
$app->singleton(CacheInterface::class, fn() => $app->driver('cache'));
```

### LogModule

Configures logging with drivers:

```php
$app->driver('log', 'stream', fn() => new StreamLogDriver());
$app->defaultDriver('log', 'stream');
$app->singleton(LoggerInterface::class, fn() => new Logger($app->driver('log')));
```

### ClockModule

Provides the PSR-20 clock:

```php
$app->singleton(Clock::class, fn() => new Clock());
$app->bind(ClockInterface::class, fn($app) => $app->make(Clock::class));
```

### ConsoleModule

Registers CLI commands:

```php
$app->command('routes:list', RoutesListCommand::class);
$app->command('cache:warm', CacheWarmCommand::class);
$app->command('cache:clear', CacheClearCommand::class);
```

## The app.ready Event

After all modules are loaded and the first request arrives, Verge fires the `app.ready` event:

```php
// Inside App::boot()
protected function boot(): void
{
    if ($this->booted) {
        return;
    }

    $this->booted = true;
    $this->emit('app.ready');
}
```

This event fires exactly once. It's your hook for any setup that needs the complete application context—all services registered, all routes defined.

Common uses:

```php
$app->ready(function () use ($app) {
    // Generate API documentation from registered routes
    $app->get('/api/docs', fn() => generateDocs($app->routes()));
});

$app->ready(function () use ($app) {
    // Register admin routes that inspect the application
    $app->get('/admin/health', fn() => checkHealth($app));
});
```

## Why This Design?

The framework bootstraps itself with modules for several reasons:

### Consistency

Your code uses the same patterns as framework code. There's no special bootstrap process for the framework versus your application—it's all modules.

### Transparency

You can read `AppBuilder` to see exactly what the framework provides. No hidden initialization, no magic loading.

### Extensibility

You can replace or extend framework modules. Want a different cache driver by default? Register your own cache module after the framework's.

### Predictability

Modules run in order. If you need to override something, register your module after the framework's and rebind the service.

## Customizing the Bootstrap

You can add modules after the framework bootstraps:

```php
$app = new App();

// Add your modules
$app->module([
    DatabaseModule::class,
    AuthModule::class,
    MyAppModule::class,
]);

$app->run();
```

Or extend the framework by rebinding services:

```php
$app = new App();

// Replace the default cache with Redis
$app->driver('cache', 'redis', fn() => new RedisCacheDriver());
$app->defaultDriver('cache', 'redis');

$app->run();
```

The framework modules have already run. Your modules build on top of them or override specific bindings.

## Understanding Boot Order

Here's the complete lifecycle:

1. **App construction**: Container created, `AppBuilder` runs
2. **Your modules**: Added via `$app->module()`
3. **First request**: `boot()` called, `app.ready` fires
4. **Request handling**: Router matches, middleware runs, handler executes

Everything before step 3 is registration. Everything after is execution. Keep this mental model and the bootstrap process becomes intuitive.
