---
title: Mounting Sub-Applications
description: Create isolated applications with their own middleware stacks for better separation of concerns.
---

Sometimes you need a section of your application to have completely different middleware than the rest. An API might need CORS and rate limiting but not session handling, while your web routes need the opposite. Mount a sub-application to keep these concerns separate.

```php
// routes/api.php
use Verge\App;

return App::create()
    ->use(CorsMiddleware::class)
    ->use(RateLimitMiddleware::class)
    ->get('/users', [UserController::class, 'index'])
    ->get('/users/{id}', [UserController::class, 'show']);
```

```php
// index.php
app()
    ->route('/api', require 'routes/api.php')
    ->run();
```

The routes register at `/api/users` and `/api/users/{id}`, and only these routes get CORS and rate limiting middleware.

## Sub-Apps vs Groups

This is the key difference: **groups inherit the parent app's middleware**, while **sub-apps don't**.

With groups, middleware stacks up:

```php
app()->use(LoggingMiddleware::class);

app()->group('/api', function($app) {
    // LoggingMiddleware runs here too
    $app->get('/users', fn() => User::all());
})->use(AuthMiddleware::class);
```

The group's routes run through both `LoggingMiddleware` and `AuthMiddleware`.

With sub-apps, middleware is isolated:

```php
app()->use(LoggingMiddleware::class);

$api = App::create()
    ->use(CorsMiddleware::class)  // Only CORS, no logging
    ->get('/users', fn() => User::all());

app()->route('/api', $api);
```

The sub-app's routes only run `CorsMiddleware`, not `LoggingMiddleware`.

## When to Use Sub-Apps

Choose sub-apps when you need:
- APIs that require CORS but would break with session middleware
- Admin panels with completely different authentication strategies
- Microservice-style sections that could be extracted later
- Third-party packages that provide their own route definitions

For most situations—especially just grouping routes by prefix—use groups. They're simpler and usually what you want.

## Container Sharing

Even though sub-apps have isolated middleware, they share the main app's dependency injection container:

```php
app()->singleton(UserRepository::class, PostgresUserRepository::class);

$api = App::create()
    ->get('/users', fn(UserRepository $repo) => $repo->all());

app()->route('/api', $api);
```

The sub-app resolves `UserRepository` from the main app's container, so you don't need to duplicate bindings.

## Defining Sub-Apps Inline

While extracting sub-apps to separate files helps with organization, you can also define them inline when it makes sense:

```php
app()->route('/admin', App::create()
    ->use(RequireAdminMiddleware::class)
    ->get('/dashboard', fn() => view('admin/dashboard'))
    ->get('/users', fn() => User::all())
);
```

This works well for smaller sub-apps or when you're prototyping. As your sub-app grows, moving it to a separate file keeps your main application definition clean.
