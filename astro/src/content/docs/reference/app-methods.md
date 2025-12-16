---
title: App Methods
description: Complete reference for Verge\App methods.
---

## Creating the App

| Method | Description |
|--------|-------------|
| `app()` | Get global app singleton, creating if needed |
| `new App()` | Create app with defaults (Router, Env bound) |
| `new App($container)` | Create app with custom Verge Container |
| `App::create($callback?)` | Create app with optional setup callback |

```php
// Create with defaults
$app = new App();

// Create with callback
$app = App::create(function (App $app) {
    $app->get('/', fn() => 'Hello');
});

// Get singleton anywhere
$app = app();
```

## Routing Methods

All routing methods return a `Route` object for further configuration (middleware, naming).

| Method | Description |
|--------|-------------|
| `->get($path, $handler, $middleware?, $name?)` | Register GET route |
| `->post($path, $handler, $middleware?, $name?)` | Register POST route |
| `->put($path, $handler, $middleware?, $name?)` | Register PUT route |
| `->patch($path, $handler, $middleware?, $name?)` | Register PATCH route |
| `->delete($path, $handler, $middleware?, $name?)` | Register DELETE route |
| `->any($path, $handler, $middleware?, $name?)` | Register route for all HTTP methods |
| `->group($prefix, $callback)` | Group routes with prefix, returns RouteGroup |
| `->route($prefix, $subApp)` | Mount a sub-app at the given prefix |
| `->url($name, $params?)` | Generate URL from named route |

```php
// Basic routing
$app->get('/users', fn() => User::all());
$app->post('/users', [UserController::class, 'store']);

// With middleware and name
$app->get('/admin', AdminController::class, [AuthMiddleware::class], 'admin.home');

// Route groups
$app->group('/api', function (App $app) {
    $app->get('/users', fn() => User::all());    // /api/users
    $app->get('/posts', fn() => Post::all());    // /api/posts
});

// Mount sub-app
$api = new App();
$api->get('/status', fn() => ['ok' => true]);
$app->route('/api/v1', $api);  // /api/v1/status

// Generate URLs
$url = $app->url('users.show', ['id' => 123]);  // /users/123
```

## Controller Methods

Register controllers that use PHP attributes for route definitions.

| Method | Description |
|--------|-------------|
| `->controller($controller)` | Register a single controller class or instance |
| `->controllers($controllers)` | Register multiple controllers at once |

```php
use Verge\Routing\Attributes\Get;
use Verge\Routing\Attributes\Post;
use Verge\Routing\Attributes\Controller;

#[Controller('/users')]
class UserController
{
    #[Get('/')]
    public function index() { return User::all(); }

    #[Get('/{id}')]
    public function show(int $id) { return User::find($id); }

    #[Post('/')]
    public function store(Request $request) { /* ... */ }
}

// Register single controller
$app->controller(UserController::class);

// Register multiple controllers
$app->controllers([
    UserController::class,
    PostController::class,
    CommentController::class,
]);
```

## Route Introspection

| Method | Description |
|--------|-------------|
| `->routes()` | Get RouteExplorer for introspection |
| `->routes($router)` | Set/merge a RouteMatcherInterface (used by cache) |

```php
// Get all registered routes
$explorer = $app->routes();
$allRoutes = $explorer->all();

// Get routes for specific method
$getRoutes = $explorer->forMethod('GET');

// Find route by name
$route = $explorer->named('users.show');
```

## Module Methods

| Method | Description |
|--------|-------------|
| `->module($module)` | Register a module (callable, class-string, or array) |
| `->ready($callback)` | Register callback for after all modules loaded |

```php
// Register module class
$app->module(CacheModule::class);

// Register inline module
$app->module(function (App $app) {
    $app->singleton(MyService::class, fn() => new MyService());
});

// Register multiple modules
$app->module([
    CacheModule::class,
    LogModule::class,
    QueueModule::class,
]);

// Deferred registration (runs after all modules)
$app->ready(function () use ($app) {
    $app->get('/late-route', LateController::class);
});
```

## Container Methods

| Method | Description |
|--------|-------------|
| `->bind($abstract, $concrete)` | Bind to container (new instance each time) |
| `->singleton($abstract, $concrete)` | Bind as singleton (cached forever) |
| `->scoped($abstract, $concrete)` | Bind as scoped (cached per request) |
| `->for($contexts)` | Make preceding binding contextual |
| `->instance($abstract, $value)` | Store existing value directly |
| `->make($abstract, $params?)` | Resolve from container |
| `->has($abstract)` | Check if bound |
| `->container()` | Get underlying PSR-11 container |

```php
// Bind new instance each resolve
$app->bind(ReportGenerator::class, fn() => new ReportGenerator());

// Singleton - same instance always
$app->singleton(Database::class, fn() => new Database(
    $app->env('DB_HOST')
));

// Scoped - same instance within a request
$app->scoped(RequestContext::class, fn() => new RequestContext());

// Contextual binding
$app->bind(LoggerInterface::class, FileLogger::class)
    ->for(PaymentService::class);
$app->bind(LoggerInterface::class, DatabaseLogger::class)
    ->for(AuditService::class);

// Store existing instance
$app->instance('config.debug', true);

// Resolve
$db = $app->make(Database::class);
$report = $app->make(ReportGenerator::class, ['format' => 'pdf']);

// Check binding
if ($app->has(CacheInterface::class)) {
    // ...
}

// Access underlying container
$psr11 = $app->container();
```

## Driver Methods

| Method | Description |
|--------|-------------|
| `->driver($service)` | Resolve driver based on `{SERVICE}_DRIVER` env var |
| `->driver($service, $name, $factory)` | Register driver factory |
| `->defaultDriver($service, $name)` | Set default driver when env var not set |

```php
// Register drivers
$app->driver('cache', 'memory', fn() => new MemoryCacheDriver());
$app->driver('cache', 'file', fn(App $app) =>
    new FileCacheDriver($app->basePath('storage/cache'))
);
$app->driver('cache', 'redis', fn(App $app) =>
    new RedisCacheDriver($app->env('REDIS_URL'))
);

// Set default
$app->defaultDriver('cache', 'memory');

// Resolve (reads CACHE_DRIVER env var, falls back to default)
$cache = $app->driver('cache');
```

## Middleware Methods

| Method | Description |
|--------|-------------|
| `->use($middleware)` | Add global middleware |
| `->getMiddleware()` | Get all registered global middleware |

```php
// Add middleware (applied to all routes)
$app->use(CorsMiddleware::class);
$app->use(AuthMiddleware::class);

// Middleware runs in order added
$app->use(fn($request, $next) => $next($request)->withHeader('X-Custom', 'value'));

// Get registered middleware
$middleware = $app->getMiddleware();
```

## Event Methods

| Method | Description |
|--------|-------------|
| `->on($event, $listener)` | Register event listener |
| `->emit($event, $payload?)` | Emit event to listeners |
| `->hasListeners($event)` | Check if event has listeners |

```php
// Listen for events
$app->on('user.created', function (User $user) {
    Mail::send($user->email, 'Welcome!');
});

// Wildcard listeners
$app->on('user.*', function ($event, $payload) {
    Log::info("User event: {$event}");
});

// Emit events
$app->emit('user.created', [$user]);

// Check listeners
if ($app->hasListeners('user.created')) {
    // ...
}
```

## Configuration Methods

| Method | Description |
|--------|-------------|
| `->env($key, $default?)` | Get environment variable |
| `->config($key?, $default?)` | Get config value, all config, or set values |
| `->loadConfig($path, $namespace?)` | Load config from PHP file |
| `->setBasePath($path)` | Set application base path |
| `->basePath($subpath?)` | Get base path or append sub-path |

```php
// Environment variables
$debug = $app->env('APP_DEBUG', false);
$dbHost = $app->env('DB_HOST', 'localhost');

// Get config value
$timezone = $app->config('app.timezone', 'UTC');

// Get all config
$all = $app->config();

// Set config values
$app->config(['app.debug' => true, 'app.name' => 'MyApp']);

// Load config from file
$app->loadConfig('/path/to/config.php');           // Merged at root
$app->loadConfig('/path/to/database.php', 'db');   // Under 'db' namespace

// Base path management
$app->setBasePath('/var/www/myapp');
$storagePath = $app->basePath('storage');          // /var/www/myapp/storage
$rootPath = $app->basePath();                      // /var/www/myapp
```

## Console Methods

| Method | Description |
|--------|-------------|
| `->command($name, $handler)` | Register a console command |
| `->getCommands()` | Get all registered commands |

```php
// Register command
$app->command('migrate', MigrateCommand::class);
$app->command('cache:clear', function () {
    // Clear cache logic
    return 0;
});

// Get commands
$commands = $app->getCommands();
```

## Lifecycle Methods

| Method | Description |
|--------|-------------|
| `->run($request?)` | Handle request and send response |
| `->handle($request)` | Handle request and return response |
| `->test()` | Get test client |
| `->isBooted()` | Check if app has booted |

```php
// Run app (captures request, sends response)
$app->run();

// Run with custom request
$request = Request::create('GET', '/users');
$app->run($request);

// Handle without sending (useful for testing)
$response = $app->handle($request);

// Test client for fluent testing
$response = $app->test()->get('/users');
expect($response->status())->toBe(200);

// Check boot status
if ($app->isBooted()) {
    // App has handled at least one request
}
```

## Complete Example

```php
use Verge\App;

$app = App::create(function (App $app) {
    // Set base path
    $app->setBasePath(__DIR__);

    // Register modules
    $app->module([
        DatabaseModule::class,
        CacheModule::class,
    ]);

    // Configure drivers
    $app->driver('cache', 'redis', fn() => new RedisCache());
    $app->defaultDriver('cache', 'file');

    // Global middleware
    $app->use(CorsMiddleware::class);

    // Routes
    $app->get('/', fn() => ['status' => 'ok']);

    $app->group('/api', function (App $app) {
        $app->controller(UserController::class);
        $app->controller(PostController::class);
    });

    // Events
    $app->on('response.sending', function ($response) {
        // Add timing header
    });

    // Deferred setup
    $app->ready(function () use ($app) {
        // Register routes after all modules loaded
    });
});

$app->run();
```
