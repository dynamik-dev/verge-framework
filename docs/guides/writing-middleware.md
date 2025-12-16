---
title: Writing Custom Middleware
description: Create reusable middleware classes for authentication, logging, and other cross-cutting concerns.
---

You need to check authentication tokens, log requests, or add security headers across multiple routes. Custom middleware lets you write this logic once and reuse it anywhere.

## Creating a Middleware Class

Middleware is just an invokable class that receives a request, optionally modifies it, calls the next handler, and returns a response:

```php
<?php

namespace App\Middleware;

use App\Services\AuthService;
use Verge\Http\Request;
use Verge\Http\Response;
use function Verge\json;

class AuthMiddleware
{
    public function __construct(private AuthService $auth) {}

    public function __invoke(Request $req, callable $next): Response
    {
        if (!$this->auth->check($req->header('Authorization'))) {
            return json(['error' => 'Unauthorized'], 401);
        }

        return $next($req);
    }
}
```

The container automatically injects dependencies through the constructor—here, the `AuthService`.

## Understanding the Middleware Signature

Every middleware follows the same pattern:

```php
function(Request $req, callable $next): Response
```

- `$req` — the incoming HTTP request
- `$next` — a callable that invokes the next middleware or your route handler
- Return value must be a `Response` object

## Running Code Before and After Handlers

You can execute logic both before and after the handler runs. This is useful for timing, logging, or modifying responses:

```php
<?php

namespace App\Middleware;

use Verge\Http\Request;
use Verge\Http\Response;

class TimingMiddleware
{
    public function __invoke(Request $req, callable $next): Response
    {
        $start = microtime(true);

        $response = $next($req);  // Handler runs here

        $duration = microtime(true) - $start;

        return $response->header('X-Response-Time', $duration);
    }
}
```

Everything before `$next($req)` runs before the handler, everything after runs once the handler completes.

## Stopping Requests Early

Sometimes you need to reject a request without running the handler at all—maintenance mode, invalid API keys, IP blocking. Just return a response instead of calling `$next`:

```php
<?php

namespace App\Middleware;

use Verge\Http\Request;
use Verge\Http\Response;
use function Verge\json;

class MaintenanceMiddleware
{
    public function __invoke(Request $req, callable $next): Response
    {
        if (file_exists('/tmp/maintenance')) {
            return json(['error' => 'Down for maintenance'], 503);
        }

        return $next($req);
    }
}
```

## Using Closure Middleware for Quick Tasks

For simple, one-off middleware that doesn't need dependencies or reuse, a closure works fine:

```php
app()->use(function($req, $next) {
    if ($req->header('X-Api-Key') !== 'secret') {
        return json(['error' => 'Invalid API key'], 401);
    }

    return $next($req);
});
```

Prefer classes for middleware you'll reuse or that needs dependencies injected.
