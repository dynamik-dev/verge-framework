---
title: Grouping Routes by Prefix
description: Organize related routes under a common path prefix and apply shared middleware.
---

When you have multiple routes that start with the same path segments—like `/api/v1/...` or `/admin/...`—use a group instead of repeating the prefix on every route.

```php
app()->group('/api', function($app) {
    $app->get('/users', fn() => User::all());
    $app->get('/posts', fn() => Post::all());
});
```

Both routes register with the `/api` prefix, so you get `/api/users` and `/api/posts`.

## Protecting Groups with Middleware

The real power of groups is applying middleware to multiple routes at once. Instead of adding authentication to each admin route individually, add it to the group:

```php
app()->group('/admin', function($app) {
    $app->get('/dashboard', fn() => 'admin dashboard');
    $app->get('/users', fn() => User::all());
})->use(AuthMiddleware::class);
```

Now both `/admin/dashboard` and `/admin/users` require authentication.

## Nesting Groups

You can nest groups to build up complex URL structures. This is useful for API versioning or organizing features by module:

```php
app()->group('/api', function($app) {
    $app->group('/v1', function($app) {
        $app->get('/users', fn() => 'v1 users');
    });

    $app->group('/v2', function($app) {
        $app->get('/users', fn() => 'v2 users');
    });
});
```

This registers `/api/v1/users` and `/api/v2/users` without repeating `/api` for each version.

## Applying Multiple Middleware

When a group needs several middleware—like CORS, rate limiting, and authentication—chain multiple `use()` calls:

```php
app()->group('/api', function($app) {
    $app->get('/users', fn() => User::all());
})->use(CorsMiddleware::class)
  ->use(RateLimitMiddleware::class)
  ->use(AuthMiddleware::class);
```

Middleware executes in the order you add it—CORS first, then rate limiting, then authentication.

## Groups vs. Sub-Apps

Groups are great for shared prefixes and middleware, but if you need more isolation—like separate error handlers or different middleware stacks—check out the guide on creating sub-applications. Sub-apps give you completely independent request processing with their own configuration.
