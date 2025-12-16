---
title: Injecting Dependencies Automatically
description: Type-hint classes and interfaces in your routes and controllers to have them automatically resolved.
---

You shouldn't have to manually instantiate every dependency. Type-hint what you need and the container handles it.

```php
app()->get('/users', function(UserService $users) {
    return $users->all();
});
```

The container sees you need `UserService`, creates it, and passes it in.

## Getting Dependencies in Controllers

Controller constructors work the same way:

```php
class UserController
{
    public function __construct(
        private UserRepository $users,
        private AuthService $auth
    ) {}

    public function index()
    {
        return $this->users->all();
    }
}
```

Both `UserRepository` and `AuthService` resolve when the controller is created.

## Resolving Nested Dependencies

The container looks at constructor parameters and recursively resolves everything:

```php
class UserService
{
    public function __construct(
        private UserRepository $repo,
        private Logger $logger
    ) {}
}

$service = app()->make(UserService::class);
```

This works even without explicit bindings—the container figures out how to build `UserRepository` and `Logger` by examining their constructors too.

## Mixing Auto-Wired and Manual Parameters

Sometimes you need to pass specific values alongside dependencies the container should resolve:

```php
class ReportGenerator
{
    public function __construct(
        private ReportService $service,  // Auto-wired
        private string $format,          // Must be provided
        private int $limit = 100         // Has default
    ) {}
}

$report = app()->make(ReportGenerator::class, ['format' => 'pdf']);
```

The container resolves `ReportService` automatically, you provide `format`, and `limit` uses its default.

## Resolving from Anywhere

The `make()` helper lets you resolve dependencies outside of routes and controllers:

```php
use function Verge\make;

$users = make(UserService::class);
$report = make(ReportGenerator::class, ['format' => 'pdf']);
```

## Checking if a Binding Exists

Before trying to resolve something that might not be bound:

```php
if (app()->has(CacheInterface::class)) {
    $cache = app()->make(CacheInterface::class);
}
```
