---
title: Testing Your Routes
description: Write tests for your routes without spinning up a server.
---

Most apps need tests to ensure routes work correctly. Verge includes a test client that lets you make requests against your app without HTTP overhead.

## Making Your First Request

Create a new app instance in your test, define routes, and use the `test()` method to send requests:

```php
use Verge\App;

it('returns hello', function() {
    $app = new App();
    $app->get('/', fn() => 'Hello Verge');

    $response = $app->test()->get('/');

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('Hello Verge');
});
```

The test client returns a `Response` object with methods like `status()`, `body()`, and `json()`.

## Testing JSON APIs

API routes typically return JSON. Use the `json()` method to get the decoded response:

```php
it('returns users as JSON', function() {
    $app = new App();
    $app->get('/users', fn() => ['data' => [['id' => 1], ['id' => 2]]]);

    $response = $app->test()->get('/users');

    expect($response->status())->toBe(200);
    expect($response->json())->toHaveKey('data');
    expect($response->json()['data'])->toHaveCount(2);
});
```

The `json()` method returns the decoded array, making assertions easier.

## Sending Query Parameters

GET requests often need query strings. Pass an array as the second argument:

```php
$response = $app->test()->get('/search', ['q' => 'test', 'page' => 2]);
```

This generates a request to `/search?q=test&page=2`.

## Posting Data

Routes that accept POST data can be tested by passing an array:

```php
$response = $app->test()->post('/users', [
    'name' => 'John',
    'email' => 'john@example.com'
]);
```

The test client automatically encodes the data as JSON and sets the `Content-Type: application/json` header.

## Adding Headers

Routes that require authentication headers or other HTTP headers can use `withHeader()`:

```php
$response = $app->test()
    ->withHeader('Authorization', 'Bearer token123')
    ->get('/me');
```

You can chain multiple `withHeader()` calls to add several headers:

```php
$response = $app->test()
    ->withHeader('Authorization', 'Bearer token123')
    ->withHeader('X-Api-Version', 'v2')
    ->get('/users');
```

## Setting Cookies

Some routes depend on cookies for session data. Use `withCookie()` to attach cookies:

```php
$response = $app->test()
    ->withCookie('session', 'abc123')
    ->get('/dashboard');
```

Chain multiple cookies when needed:

```php
$response = $app->test()
    ->withCookie('session', 'abc123')
    ->withCookie('preference', 'dark-mode')
    ->get('/settings');
```

## Testing All HTTP Methods

The test client supports every HTTP method your routes might use:

```php
// GET
$app->test()->get('/users');
$app->test()->get('/users', ['page' => 1]);

// POST
$app->test()->post('/users', ['name' => 'John']);

// PUT
$app->test()->put('/users/1', ['name' => 'Jane']);

// PATCH
$app->test()->patch('/users/1', ['email' => 'jane@example.com']);

// DELETE
$app->test()->delete('/users/1');
```

All methods except GET accept a data array that's automatically JSON-encoded.

## Chaining Test Client Methods

The `withHeader()` and `withCookie()` methods return a new TestClient instance, allowing you to chain calls:

```php
$response = $app->test()
    ->withHeader('Authorization', 'Bearer token123')
    ->withHeader('Accept', 'application/json')
    ->withCookie('session', 'abc123')
    ->post('/users', ['name' => 'John']);

expect($response->status())->toBe(201);
```

## Inspecting Response Data

The Response object provides several methods for inspecting the response:

```php
$response = $app->test()->get('/users/1');

// Status code
$status = $response->status();             // 200

// Body as string
$body = $response->body();                 // '{"id":1,"name":"John"}'

// Decoded JSON
$data = $response->json();                 // ['id' => 1, 'name' => 'John']

// Headers
$contentType = $response->getHeader('Content-Type');  // ['application/json']
```

## Testing Error Responses

Verify your error handling works correctly:

```php
it('returns 404 for missing users', function() {
    $app = new App();
    $app->get('/users/{id}', function($id) {
        if ($id !== '1') {
            return response('Not found', 404);
        }
        return ['id' => $id];
    });

    $response = $app->test()->get('/users/999');

    expect($response->status())->toBe(404);
    expect($response->body())->toBe('Not found');
});
```

## Testing Middleware

Middleware is automatically invoked during tests:

```php
it('applies middleware to routes', function() {
    $app = new App();

    $app->use(function($request, $next) {
        $response = $next($request);
        return $response->withHeader('X-Custom', 'value');
    });

    $app->get('/', fn() => 'Hello');

    $response = $app->test()->get('/');

    expect($response->getHeader('X-Custom'))->toBe(['value']);
});
```

## Testing Route Parameters

Routes with parameters work exactly as expected:

```php
it('receives route parameters', function() {
    $app = new App();
    $app->get('/users/{id}/posts/{postId}', function($id, $postId) {
        return ['userId' => $id, 'postId' => $postId];
    });

    $response = $app->test()->get('/users/123/posts/456');

    expect($response->json())->toBe([
        'userId' => '123',
        'postId' => '456'
    ]);
});
```

## Testing Dependency Injection

Route handlers that type-hint dependencies receive them from the container:

```php
it('injects dependencies into handlers', function() {
    $app = new App();

    $app->bind(UserService::class, fn() => new class {
        public function getUser($id) {
            return ['id' => $id, 'name' => 'Test User'];
        }
    });

    $app->get('/users/{id}', fn($id, UserService $service) => $service->getUser($id));

    $response = $app->test()->get('/users/1');

    expect($response->json())->toBe(['id' => '1', 'name' => 'Test User']);
});
```

## Testing Route Logic Without HTTP

Route handlers are just functions. For pure logic tests, skip the test client entirely:

```php
it('formats user correctly', function() {
    $handler = fn($id) => ['id' => $id, 'formatted' => true];

    expect($handler('123'))->toBe([
        'id' => '123',
        'formatted' => true
    ]);
});
```

This is faster and more focused when you're testing business logic rather than HTTP concerns.

## Complete Test Example

```php
use Verge\App;

describe('User API', function() {
    it('creates a new user', function() {
        $app = new App();

        // Mock repository
        $app->bind(UserRepository::class, fn() => new class {
            public function create(array $data) {
                return ['id' => 123, ...$data];
            }
        });

        // Define route
        $app->post('/users', function(Request $request, UserRepository $repo) {
            return $repo->create($request->getParsedBody());
        });

        // Test request
        $response = $app->test()
            ->withHeader('Authorization', 'Bearer token123')
            ->post('/users', [
                'name' => 'John',
                'email' => 'john@example.com'
            ]);

        // Assertions
        expect($response->status())->toBe(200);
        expect($response->json())->toHaveKeys(['id', 'name', 'email']);
        expect($response->json()['name'])->toBe('John');
    });
});
```
