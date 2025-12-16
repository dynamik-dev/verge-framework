---
title: Binding Services to the Container
description: Register interfaces and services in the container to swap implementations and control instantiation.
---

Most apps need to swap implementations or provide configuration when creating objects. The container lets you register how things get built.

## Binding Interfaces to Implementations

You can tell the container which concrete class to use when code asks for an interface:

```php
app()->bind(UserRepositoryInterface::class, PostgresUserRepository::class);
```

Now anywhere you type-hint `UserRepositoryInterface`, you get a `PostgresUserRepository`. Swap to MySQL later by changing this one line.

## Using Factory Functions

When instantiation needs configuration or dependencies, use a closure:

```php
app()->bind(UserService::class, fn() => new UserService(
    app()->make(UserRepository::class),
    app()->env('USER_SERVICE_TIMEOUT', 30)
));
```

The closure runs each time someone asks for `UserService`.

## Controlling Instance Lifetime

Sometimes you want a new instance every time, sometimes you want to reuse the same one.

**bind()** creates fresh instances:

```php
app()->bind(Logger::class, fn() => new Logger());

$a = app()->make(Logger::class);
$b = app()->make(Logger::class);
// $a !== $b (different instances)
```

**singleton()** creates once and caches:

```php
app()->singleton(DB::class, fn() => new DB(app()->env('DATABASE_URL')));

$a = app()->make(DB::class);
$b = app()->make(DB::class);
// $a === $b (same instance)
```

Use singletons for expensive objects like database connections that you only want to create once.

## Storing Existing Instances

Already have an object you want to store in the container? Use `instance()`:

```php
$config = ['debug' => true, 'env' => 'production'];
app()->instance('config', $config);

$config = app()->make('config');
```

## Binding with String Keys

Class names aren't required. You can bind to any string:

```php
app()->singleton('db', fn() => new Database(app()->env('DATABASE_URL')));

$db = app()->make('db');
```

This is handy for configuration values or when you don't have an interface.

## Contextual Bindings

Sometimes different classes need different implementations of the same interface. Use `for()` to make a binding contextual:

```php
app()
    ->bind(CacheInterface::class, RedisCache::class)
    ->for(UserService::class);

app()
    ->bind(CacheInterface::class, MemoryCache::class)
    ->for(SessionManager::class);
```

Now `UserService` gets `RedisCache` while `SessionManager` gets `MemoryCache`. Classes without a contextual binding will use the default (if one exists) or auto-wiring.

You can also apply the same binding to multiple contexts:

```php
app()
    ->bind(LoggerInterface::class, FileLogger::class)
    ->for([AdminController::class, AuditService::class]);
```

Contextual bindings work with `bind()`, `singleton()`, and `instance()`.

## Chaining Bindings

Container methods return the app instance, so chain away:

```php
app()
    ->singleton(DB::class, fn() => new DB(app()->env('DATABASE_URL')))
    ->bind(UserRepository::class, PostgresUserRepository::class)
    ->bind(PostRepository::class, PostgresPostRepository::class)
    ->get('/users', fn(UserRepository $repo) => $repo->all());
```
