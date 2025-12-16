---
title: Protecting Routes with Middleware
description: Add authentication, logging, CORS, and other cross-cutting concerns to your routes.
---

Most applications need to run code before or after requests—checking authentication, logging activity, setting CORS headers. Middleware wraps your route handlers to handle these cross-cutting concerns.

## Applying Middleware to Specific Routes

You need some routes to be public while others require authentication. Pass middleware as the third parameter to any route method:

```php
<?php

use App\Middleware\AuthMiddleware;

app()
    ->get('/public', fn() => 'anyone can see this')
    ->get('/admin', handler: fn() => 'admin only', middleware: [AuthMiddleware::class]);
```

Now only `/admin` runs through the authentication check.

## Running Multiple Middleware

Some routes need several checks—CORS headers, authentication, rate limiting. Pass an array of middleware classes:

```php
<?php

use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\RateLimitMiddleware;

app()->get('/api/users',
    handler: [UserController::class, 'index'],
    middleware: [
        CorsMiddleware::class,
        AuthMiddleware::class,
        RateLimitMiddleware::class,
    ]
);
```

Middleware executes in array order—CORS first, then auth, then rate limiting, then your handler.

## Protecting Route Groups

When you have multiple routes that all need the same middleware (like an entire API), use group middleware instead of repeating yourself:

```php
<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Models\Post;
use App\Models\User;

app()->group('/api', function($app) {
    $app->get('/users', fn() => User::all())
        ->get('/posts', fn() => Post::all());
})->use(CorsMiddleware::class)
  ->use(AuthMiddleware::class);
```

Both routes automatically run through CORS and auth checks.

## Applying Middleware Globally

Sometimes you want middleware on every single request—logging, security headers, error handling. Use the `use()` method without a route:

```php
app()->use(fn($req, $next) => $next($req)->header('X-Powered-By', 'Verge'));
```

Every response now includes the `X-Powered-By` header.

## Combining Global and Route Middleware

Global middleware runs first, followed by group middleware, then route-specific middleware:

```php
<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\LoggingMiddleware;

app()
    ->use(LoggingMiddleware::class)  // Runs on every request
    ->get('/admin', handler: fn() => 'admin', middleware: [AuthMiddleware::class]);
```

When someone hits `/admin`, LoggingMiddleware runs, then AuthMiddleware, then your handler.
