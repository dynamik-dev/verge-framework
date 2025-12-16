---
title: Configuring Middleware with Parameters
description: Pass custom configuration to middleware for rate limits, required roles, and feature flags.
---

Different routes often need the same middleware but with different settings—stricter rate limits on expensive endpoints, different required roles for admin sections. You can pass parameters to middleware by instantiating it yourself instead of letting the container do it.

## Passing Instances Instead of Class Names

When you pass a class name, the container resolves the middleware with default constructor values:

```php
app()->get('/api/users',
    handler: fn() => User::all(),
    middleware: [AuthMiddleware::class]
);
```

To customize the configuration, pass an instance:

```php
app()->get('/api/users',
    handler: fn() => User::all(),
    middleware: [new ThrottleMiddleware(limit: 100)]
);
```

## Writing Middleware That Accepts Parameters

Accept configuration through constructor parameters with sensible defaults:

```php
<?php

namespace App\Middleware;

use Verge\Http\Request;
use Verge\Http\Response;

class ThrottleMiddleware
{
    public function __construct(
        private int $limit = 60,
        private int $window = 60
    ) {}

    public function __invoke(Request $req, callable $next): Response
    {
        // Rate limiting logic using $this->limit and $this->window
        return $next($req);
    }
}
```

## Applying Different Configurations Per Route

You need strict rate limits on expensive operations but relaxed limits on simple reads:

```php
<?php

use App\Models\User;

app()
    ->get('/api/users',
        handler: fn() => User::all(),
        middleware: [new ThrottleMiddleware(limit: 100)]
    )
    ->get('/api/expensive',
        handler: fn() => expensiveOperation(),
        middleware: [new ThrottleMiddleware(limit: 10, window: 300)]
    );
```

Each route gets its own throttle configuration.

## Using Parameterized Middleware with Groups

Parameterized middleware works on route groups too:

```php
<?php

use App\Middleware\CorsMiddleware;
use App\Middleware\ThrottleMiddleware;
use App\Models\Post;
use App\Models\User;

app()->group('/api', function($app) {
    $app->get('/users', fn() => User::all())
        ->get('/posts', fn() => Post::all());
})->use(CorsMiddleware::class)
  ->use(new ThrottleMiddleware(limit: 100));
```

## Restricting Routes by User Role

Role-based access control needs different roles for different routes. Accept the required role as a parameter:

```php
<?php

namespace App\Middleware;

use Verge\Http\Request;
use Verge\Http\Response;
use function Verge\json;

class RequireRoleMiddleware
{
    public function __construct(private string $role) {}

    public function __invoke(Request $req, callable $next): Response
    {
        $user = $req->getAttribute('user');

        if (!$user || !$user->hasRole($this->role)) {
            return json(['error' => 'Forbidden'], 403);
        }

        return $next($req);
    }
}
```

Now each route specifies its required role:

```php
app()
    ->get('/admin', handler: fn() => 'admin', middleware: [new RequireRoleMiddleware('admin')])
    ->get('/editor', handler: fn() => 'editor', middleware: [new RequireRoleMiddleware('editor')]);
```
