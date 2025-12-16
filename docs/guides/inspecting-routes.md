---
title: Inspecting Routes
description: Access route metadata for debugging, documentation generation, and building admin tools.
---

Sometimes you need to programmatically examine your application's routes—maybe you're building a debug panel, generating API documentation, or verifying test coverage. The `$app->routes()` method returns a RouteExplorer that gives you access to all route metadata.

## Getting the RouteExplorer

Call `$app->routes()` to get a RouteExplorer instance:

```php
$app->get('/users', fn() => User::all())->name('users.index');
$app->get('/users/{id}', fn($id) => findUser($id))->name('users.show');
$app->post('/users', fn(Request $req) => createUser($req));

$explorer = $app->routes();
```

## Listing All Routes

The `all()` method returns an array of `RouteInfo` objects:

```php
$routes = $app->routes()->all();

foreach ($routes as $route) {
    echo "{$route->method} {$route->path}\n";
}
```

Each `RouteInfo` object contains everything about a route—method, path, middleware, parameters, and more.

## Filtering by HTTP Method

Get only routes for a specific HTTP method:

```php
$getRoutes = $app->routes()->method('GET');
$postRoutes = $app->routes()->method('POST');

foreach ($getRoutes as $route) {
    echo "GET {$route->path}\n";
}
```

## Finding Named Routes

Get only routes that have names:

```php
$namedRoutes = $app->routes()->named();

foreach ($namedRoutes as $route) {
    echo "{$route->name} -> {$route->method} {$route->path}\n";
}
```

## Filtering by Path Prefix

Examine all routes under a specific path:

```php
$apiRoutes = $app->routes()->prefix('/api');
$adminRoutes = $app->routes()->prefix('/admin');

foreach ($apiRoutes as $route) {
    echo "{$route->method} {$route->path}\n";
}
```

## Getting Route Count

```php
$total = $app->routes()->count();
echo "Total routes: $total\n";
```

## Understanding RouteInfo Properties

Each `RouteInfo` object has these readonly properties:

| Property | Type | Description |
|----------|------|-------------|
| `method` | string | HTTP method (GET, POST, etc.) |
| `path` | string | Route path pattern |
| `name` | ?string | Route name if set, null otherwise |
| `params` | ParamInfo[] | Array of parameter information objects |
| `middleware` | string[] | Middleware class names |
| `handler` | array | Handler metadata (type, class, method) |

```php
$routes = $app->routes()->all();

foreach ($routes as $route) {
    echo "Method: {$route->method}\n";
    echo "Path: {$route->path}\n";
    echo "Name: " . ($route->name ?? 'unnamed') . "\n";
    echo "Middleware: " . count($route->middleware) . "\n";
}
```

## Examining Route Parameters

Route parameters are `ParamInfo` objects with these properties:

| Property | Type | Description |
|----------|------|-------------|
| `name` | string | Parameter name |
| `required` | bool | Whether parameter is required |
| `constraint` | ?string | Regex constraint if specified |

```php
$app->get('/posts/{id:\d+}/comments/{commentId?}', ...)->name('post.comments');

$routes = $app->routes()->named();
foreach ($routes as $route) {
    foreach ($route->params as $param) {
        echo "Parameter: {$param->name}\n";
        echo "Required: " . ($param->required ? 'yes' : 'no') . "\n";
        echo "Constraint: " . ($param->constraint ?? 'none') . "\n";
    }
}
```

## Understanding Handler Metadata

The `handler` property is an array with different structures based on handler type:

```php
// Closure handler
['type' => 'closure']

// Controller method
['type' => 'controller', 'class' => 'UserController', 'method' => 'show']

// Invokable class
['type' => 'invokable', 'class' => 'ShowUserAction']

// Function name
['type' => 'function', 'name' => 'handleRequest']

// Unknown
['type' => 'unknown']
```

Example usage:

```php
foreach ($app->routes()->all() as $route) {
    if ($route->handler['type'] === 'controller') {
        echo "{$route->handler['class']}@{$route->handler['method']}\n";
    } elseif ($route->handler['type'] === 'closure') {
        echo "Closure\n";
    }
}
```

## Converting to Arrays

Export all route data as arrays for JSON serialization or storage:

```php
$routesArray = $app->routes()->toArray();

file_put_contents('routes.json', json_encode($routesArray, JSON_PRETTY_PRINT));
```

## Creating a Debug Endpoint

A simple endpoint showing all routes is helpful during development:

```php
$app->get('/debug/routes', function() {
    $routes = app()->routes()->all();

    return array_map(fn($r) => [
        'method' => $r->method,
        'path' => $r->path,
        'name' => $r->name,
        'params' => array_map(fn($p) => [
            'name' => $p->name,
            'required' => $p->required
        ], $r->params),
    ], $routes);
});
```

## Generating API Documentation

Create comprehensive API documentation from your routes:

```php
$app->get('/api/docs', function() {
    $apiRoutes = app()->routes()->prefix('/api');

    $docs = [];
    foreach ($apiRoutes as $route) {
        $params = [];
        foreach ($route->params as $param) {
            $params[] = [
                'name' => $param->name,
                'required' => $param->required,
                'type' => $param->constraint ? 'number' : 'string',
            ];
        }

        $docs[] = [
            'endpoint' => "{$route->method} {$route->path}",
            'name' => $route->name,
            'parameters' => $params,
            'protected' => in_array('AuthMiddleware', $route->middleware),
        ];
    }

    return $docs;
});
```

## Identifying Protected Routes

Check which routes have authentication or other middleware:

```php
$routes = $app->routes()->all();

$protected = array_filter($routes, function($route) {
    return in_array('AuthMiddleware', $route->middleware);
});

foreach ($protected as $route) {
    echo "Protected: {$route->method} {$route->path}\n";
}
```

## Building Route Statistics

Gather metrics about your application's routes:

```php
$explorer = $app->routes();

$stats = [
    'total' => $explorer->count(),
    'by_method' => [
        'GET' => count($explorer->method('GET')),
        'POST' => count($explorer->method('POST')),
        'PUT' => count($explorer->method('PUT')),
        'PATCH' => count($explorer->method('PATCH')),
        'DELETE' => count($explorer->method('DELETE')),
    ],
    'named' => count($explorer->named()),
    'api_routes' => count($explorer->prefix('/api')),
];

print_r($stats);
```

## Building a CLI Route List Command

Create a command-line tool to view all routes:

```php
// bin/routes.php
require 'vendor/autoload.php';
require 'bootstrap.php';

foreach (app()->routes()->all() as $route) {
    $name = str_pad($route->name ?? '', 25);
    $method = str_pad($route->method, 7);
    $path = $route->path;

    echo "{$method} {$path} {$name}\n";
}
```

Run it with `php bin/routes.php`.

## Verifying Test Coverage

Ensure every route has corresponding tests:

```php
it('has tests for all routes', function() {
    $routes = app()->routes()->all();
    $testedRoutes = getTestedRoutes(); // Your test tracking logic

    foreach ($routes as $route) {
        $key = "{$route->method} {$route->path}";
        expect($testedRoutes)->toContain($key);
    }
});
```

## Finding Routes by Name

While there's no direct `find()` method, you can search named routes:

```php
function findRoute(string $name): ?RouteInfo {
    $routes = app()->routes()->named();

    foreach ($routes as $route) {
        if ($route->name === $name) {
            return $route;
        }
    }

    return null;
}

$route = findRoute('users.show');
if ($route) {
    echo "Found: {$route->method} {$route->path}\n";
}
```

## Analyzing Middleware Usage

See which middleware are most commonly used:

```php
$routes = $app->routes()->all();
$middlewareCount = [];

foreach ($routes as $route) {
    foreach ($route->middleware as $mw) {
        $middlewareCount[$mw] = ($middlewareCount[$mw] ?? 0) + 1;
    }
}

arsort($middlewareCount);

foreach ($middlewareCount as $middleware => $count) {
    echo "{$middleware}: {$count} routes\n";
}
```

## Building an Admin Dashboard

Display route information in an admin panel:

```php
$app->get('/admin/routes', function() {
    $explorer = app()->routes();

    return [
        'total' => $explorer->count(),
        'by_method' => [
            'GET' => count($explorer->method('GET')),
            'POST' => count($explorer->method('POST')),
        ],
        'named' => count($explorer->named()),
        'routes' => $explorer->toArray(),
    ];
});
```

## Complete Example

```php
use Verge\App;

$app = new App();

// Define routes
$app->get('/', fn() => 'Home');
$app->get('/users', fn() => User::all())->name('users.index');
$app->get('/users/{id:\d+}', fn($id) => findUser($id))->name('users.show');
$app->post('/users', fn() => createUser())->name('users.store');

$app->group('/api', function(App $app) {
    $app->use([AuthMiddleware::class]);
    $app->get('/posts', fn() => Post::all())->name('api.posts.index');
    $app->get('/posts/{id}', fn($id) => findPost($id))->name('api.posts.show');
});

// Inspect routes
$explorer = $app->routes();

echo "Total routes: " . $explorer->count() . "\n\n";

echo "API routes:\n";
foreach ($explorer->prefix('/api') as $route) {
    echo "  {$route->method} {$route->path}";
    if ($route->name) {
        echo " ({$route->name})";
    }
    echo "\n";
}

echo "\nProtected routes:\n";
foreach ($explorer->all() as $route) {
    if (in_array('AuthMiddleware', $route->middleware)) {
        echo "  {$route->method} {$route->path}\n";
    }
}

echo "\nRoutes with parameters:\n";
foreach ($explorer->all() as $route) {
    if (count($route->params) > 0) {
        echo "  {$route->path}: ";
        echo implode(', ', array_map(fn($p) => $p->name, $route->params));
        echo "\n";
    }
}
```
