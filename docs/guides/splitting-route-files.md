---
title: Organizing Routes into Files
description: Split route definitions across multiple files as your application grows.
---

As your application grows, keeping all routes in a single file becomes unwieldy. Split them into logical groups—one file for your API, another for web routes, maybe one for admin endpoints.

```php
// routes/api.php
app()
    ->get('/api/health', [HealthController::class, 'index'])
    ->get('/api/users', handler: [UserController::class, 'index'], middleware: [AuthMiddleware::class]);
```

```php
// routes/web.php
app()
    ->get('/', fn() => ['message' => 'Welcome'])
    ->get('/about', fn() => ['page' => 'about']);
```

```php
// index.php
require 'vendor/autoload.php';

require 'routes/api.php';
require 'routes/web.php';

app()->run();
```

The `app()` helper returns a global singleton, so routes defined in any file register to the same application instance.

## Using Modules Instead of Files

If you prefer organizing features as classes rather than loose files, use modules. Modules are just callables that receive the app instance:

```php
// src/Modules/UserModule.php
class UserModule
{
    public function __invoke(App $app): void
    {
        // Register routes
        $app->get('/users', [UserController::class, 'index'])
            ->get('/users/{id}', [UserController::class, 'show']);

        // Register services too
        $app->singleton(UserRepository::class, fn() => new UserRepository());
    }
}
```

```php
// index.php
require 'vendor/autoload.php';

app()->module(UserModule::class);
app()->run();
```

Modules can register both routes and services, making them useful for packaging complete features. You can pass multiple modules at once using `configure()`:

```php
app()->configure([
    UserModule::class,
    AdminModule::class,
    ApiModule::class,
]);
```

Choose whichever style fits your project. Simple route files work well for small apps. Modules shine when you want to bundle routes with their related services and keep everything testable.

## Organizing Your Project

```
project/
├── index.php
├── routes/
│   ├── api.php
│   └── web.php
├── src/
│   ├── Controllers/
│   ├── Middleware/
│   └── Modules/       # Optional: for module-based organization
│       ├── UserModule.php
│       └── AdminModule.php
└── composer.json
```

This keeps route definitions separate from business logic and makes it easy to find where a URL is handled.

## Using Groups in Route Files

Each route file can define its own groups. This is particularly useful for admin sections that need shared middleware:

```php
// routes/admin.php
app()->group('/admin', function($app) {
    $app->get('/dashboard', [AdminController::class, 'dashboard'])
        ->get('/users', [AdminController::class, 'users']);
})->use(AdminAuthMiddleware::class);
```

```php
// index.php
require 'vendor/autoload.php';

require 'routes/api.php';
require 'routes/admin.php';
require 'routes/web.php';

app()->run();
```
