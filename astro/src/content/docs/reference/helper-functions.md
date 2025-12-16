---
title: Helper Functions
description: Complete reference for global helper functions.
---

## Global Namespace

| Function | Signature | Description |
|----------|-----------|-------------|
| `app()` | `app(): App` | Get global app singleton, creating if needed |

```php
// Get the app anywhere
$app = app();

// Chain calls
app()->get('/users', fn() => User::all());

// Access container
$db = app()->make(DB::class);
```

## Verge Namespace

Import these functions from the `Verge` namespace:

```php
use function Verge\make;
use function Verge\response;
use function Verge\json;
use function Verge\html;
use function Verge\redirect;
use function Verge\download;
use function Verge\file;
use function Verge\route;
use function Verge\signed_route;
use function Verge\http;
use function Verge\config;
use function Verge\base_path;
```

### Container

#### make()

Resolve a class from the container.

```php
make(string $abstract, array $parameters = []): mixed
```

```php
$users = make(UserService::class);
$report = make(ReportGenerator::class, ['format' => 'pdf']);
```

### Response Helpers

#### response()

Create a basic Response.

```php
response(string $body = '', int $status = 200, array $headers = []): Response
```

```php
response('Hello');                           // 200, text/plain
response('Created', 201);                    // Custom status
response('', 200, ['X-Custom' => 'value']);  // Custom headers
```

#### json()

Create a JSON Response.

```php
json(mixed $data, int $status = 200, array $headers = []): JsonResponse
```

```php
json(['ok' => true]);                  // 200, application/json
json(['error' => 'fail'], 400);        // 400 Bad Request
json(['users' => $users], 200, [
    'X-Total-Count' => count($users)
]);
```

#### html()

Create an HTML Response.

```php
html(string $content, int $status = 200, array $headers = []): HtmlResponse
```

```php
html('<h1>Hello World</h1>');          // 200, text/html
html('<h1>Not Found</h1>', 404);       // 404 page
html($template, 200, [
    'Cache-Control' => 'public, max-age=3600'
]);
```

#### redirect()

Create a redirect Response.

```php
redirect(string $url, int $status = 302, array $headers = []): RedirectResponse
```

```php
redirect('/dashboard');                // 302 temporary redirect
redirect('/new-home', 301);            // 301 permanent redirect
redirect('/login', 302, [
    'X-Redirect-Reason' => 'session-expired'
]);
```

#### download()

Create a file download Response (Content-Disposition: attachment).

```php
download(string $path, ?string $filename = null, ?string $contentType = null): DownloadResponse
```

```php
// Download with original filename
download('/path/to/report.pdf');

// Download with custom filename
download('/path/to/file.pdf', 'monthly-report.pdf');

// Download with explicit content type
download('/path/to/data.bin', 'export.csv', 'text/csv');
```

#### file()

Create a file Response for inline display (Content-Disposition: inline).

```php
file(string $path, ?string $contentType = null): FileResponse
```

```php
// Display PDF in browser
file('/path/to/document.pdf');

// Display image
file('/path/to/image.png');

// Explicit content type
file('/path/to/data', 'image/svg+xml');
```

### Routing Helpers

#### route()

Generate URL for a named route.

```php
route(string $name, array $params = []): string
```

```php
// Given: $app->get('/users/{id}', ...)->name('users.show');
route('users.show', ['id' => 123]);     // /users/123

// Given: $app->get('/posts', ...)->name('posts.index');
route('posts.index');                    // /posts

// With query parameters (extra params become query string)
route('users.show', ['id' => 123, 'tab' => 'posts']);  // /users/123?tab=posts
```

#### signed_route()

Generate a signed URL for a named route with optional expiration.

```php
signed_route(string $name, array $params = [], ?int $expiration = null): string
```

```php
// Signed URL (no expiration)
signed_route('unsubscribe', ['user' => 123]);
// /unsubscribe/123?signature=abc123...

// Signed URL with expiration (1 hour from now)
signed_route('download', ['file' => 'report.pdf'], time() + 3600);
// /download/report.pdf?expires=1699999999&signature=xyz789...

// Use for password reset, email verification, etc.
$resetUrl = signed_route('password.reset', [
    'token' => $token
], time() + 3600);
```

### HTTP Client

#### http()

Get the HTTP client instance for making external requests.

```php
http(): Client
```

```php
// GET request
$response = http()->get('https://api.example.com/users');

// POST with JSON body
$response = http()->post('https://api.example.com/users', [
    'name' => 'John',
    'email' => 'john@example.com'
]);

// With headers
$response = http()
    ->withHeader('Authorization', 'Bearer ' . $token)
    ->get('https://api.example.com/me');

// Access response
$data = json_decode($response->getBody()->getContents(), true);
$status = $response->getStatusCode();
```

### Configuration Helpers

#### config()

Get or set configuration values.

```php
config(string|array|null $key = null, mixed $default = null): mixed
```

```php
// Get single value
$debug = config('app.debug', false);
$dbHost = config('database.host', 'localhost');

// Get all config
$all = config();

// Set values (pass array)
config(['app.debug' => true]);
```

#### base_path()

Get the application base path, optionally appending a sub-path.

```php
base_path(string $path = ''): string
```

```php
// Get root path
$root = base_path();                    // /var/www/myapp

// Get sub-path
$storage = base_path('storage');        // /var/www/myapp/storage
$views = base_path('resources/views');  // /var/www/myapp/resources/views
```

## Verge Facade

For static access to the app instance:

```php
use Verge\Verge;

// Create app with optional setup
Verge::create(function (App $app) {
    $app->get('/', fn() => 'Hello');
});

// Get current app instance
$app = Verge::app();

// Resolve from container
$service = Verge::make(UserService::class);
$report = Verge::make(ReportGenerator::class, ['format' => 'pdf']);

// Check if bound
if (Verge::has(CacheInterface::class)) {
    // ...
}

// Get environment variable
$debug = Verge::env('APP_DEBUG', false);

// Generate route URL
$url = Verge::route('users.show', ['id' => 123]);

// HTTP client
$response = Verge::http()->get('https://api.example.com');

// Cache instance
$cache = Verge::cache();
$value = $cache->get('key');

// Reset app instance (for testing)
Verge::reset();
```

## Quick Reference

| Function | Purpose | Example |
|----------|---------|---------|
| `app()` | Get app singleton | `app()->make(Service::class)` |
| `make()` | Resolve from container | `make(UserService::class)` |
| `response()` | Basic response | `response('OK', 200)` |
| `json()` | JSON response | `json(['ok' => true])` |
| `html()` | HTML response | `html('<h1>Hi</h1>')` |
| `redirect()` | Redirect response | `redirect('/login')` |
| `download()` | File download | `download('/path/to/file.pdf')` |
| `file()` | Inline file display | `file('/path/to/image.png')` |
| `route()` | Generate route URL | `route('users.show', ['id' => 1])` |
| `signed_route()` | Signed URL | `signed_route('reset', [], time() + 3600)` |
| `http()` | HTTP client | `http()->get('https://api.example.com')` |
| `config()` | Get/set config | `config('app.debug', false)` |
| `base_path()` | Get base path | `base_path('storage')` |
