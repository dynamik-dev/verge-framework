---
title: Defining Routes
description: Map URLs to handlers and capture dynamic segments from the path.
---

Every web application needs to map incoming URLs to code that handles them. Verge makes this straightforward:

```php
<?php

use Verge\Http\Request;

app()
    ->get('/', fn() => 'Hello Verge')
    ->get('/users', fn() => $db->fetchAll('users'))
    ->post('/users', fn(Request $req) => $db->insert('users', $req->json()))
    ->put('/users/{id}', fn($id, Request $req) => $db->update('users', $id, $req->json()))
    ->delete('/users/{id}', fn($id) => $db->delete('users', $id));
```

Each routing method returns the app instance, so you can chain calls together.

## Building a REST API

Most APIs follow REST conventions where each resource gets multiple endpoints. Here's what a typical user resource looks like:

```php
<?php

use Verge\Http\Request;

app()
    ->get('/users', fn() => $db->fetchAll('users'))              // List all
    ->post('/users', fn(Request $req) => createUser($req))       // Create new
    ->get('/users/{id}', fn($id) => $db->fetch('users', $id))    // Get one
    ->put('/users/{id}', fn($id, Request $req) => replaceUser($id, $req))           // Replace
    ->patch('/users/{id}', fn($id, Request $req) => updateUser($id, $req))          // Partial update
    ->delete('/users/{id}', fn($id) => $db->delete('users', $id)); // Remove
```

The URL stays the same, the HTTP method determines what happens.

## Capturing URL Parameters

Wrap dynamic segments in curly braces to pull values from the URL:

```php
app()->get('/users/{id}', fn($id) => $db->fetch('users', $id));
```

Your handler parameters must match the placeholder names. Capture multiple segments for nested resources:

```php
app()->get('/posts/{postId}/comments/{commentId}', function($postId, $commentId) {
    return [
        'post' => findPost($postId),
        'comment' => findComment($commentId)
    ];
});
```

Parameters are passed to your handler in the order they appear in the URL.

## Accepting Multiple HTTP Methods

Some endpoints need to respond to multiple HTTP methods. Webhooks often send GET for verification and POST for actual events:

```php
<?php

use Verge\Http\Request;

app()->any('/webhook', function(Request $req) {
    if ($req->getMethod() === 'GET') {
        return verifyWebhook($req);
    }

    return handleWebhookEvent($req);
});
```

The `any()` method responds to GET, POST, PUT, PATCH, and DELETE requests.

## Organizing Routes with Prefixes

When multiple routes share a common URL prefix—like an API version or admin section—group them together:

```php
app()->group('/api/v1', function($app) {
    $app->get('/users', fn() => ['users' => []]);
    $app->get('/posts', fn() => ['posts' => []]);
    $app->get('/comments', fn() => ['comments' => []]);
});
```

Now you have `/api/v1/users`, `/api/v1/posts`, and `/api/v1/comments` without repeating the prefix. Groups can also share middleware and other options—see the [Route Groups](/docs/guides/route-groups) guide for details.

## Mounting a Sub-Application

For larger applications, you might want to completely isolate sections with their own middleware stacks. Create separate App instances and mount them at a prefix:

```php
<?php

use Verge\App;

$admin = new App();
$admin->use(AuthMiddleware::class);
$admin->get('/dashboard', fn() => 'Admin Dashboard');
$admin->get('/users', fn() => ['admins' => []]);

app()->route('/admin', $admin);
```

Requests to `/admin/dashboard` and `/admin/users` are handled entirely by the sub-application, including its middleware. See the [Sub-Applications](/docs/guides/sub-applications) guide for more.

## Naming Routes for URL Generation

When you need to generate URLs programmatically—for redirects, emails, or API responses—name your routes:

```php
app()
    ->get('/users/{id}', fn($id) => findUser($id))->name('users.show')
    ->get('/posts/{slug}', fn($slug) => findPost($slug))->name('posts.show');
```

Generate URLs from route names using the `route()` helper:

```php
use function Verge\route;

$url = route('users.show', ['id' => 123]);      // /users/123
$url = route('posts.show', ['slug' => 'hello']); // /posts/hello
```

Or call `url()` directly on the app:

```php
$url = app()->url('users.show', ['id' => 123]); // /users/123
```

This is especially useful in email templates or when building API responses with links to related resources.

## Constraining Route Parameters

Sometimes you need to ensure a parameter matches a specific format—numeric IDs only or properly formatted dates. Add a regex constraint after the parameter name:

```php
app()->get('/users/{id:\d+}', fn($id) => "User $id");
```

The route only matches numeric IDs. A request to `/users/123` works, but `/users/abc` returns 404.

Common constraint patterns:

```php
// Numeric only
app()->get('/posts/{id:\d+}', fn($id) => findPost($id));

// Date format (YYYY-MM-DD)
app()->get('/archive/{date:\d{4}-\d{2}-\d{2}}', fn($date) => getArchive($date));

// Alphanumeric slug with hyphens
app()->get('/posts/{slug:[a-z0-9-]+}', fn($slug) => findPostBySlug($slug));

// UUID format
app()->get('/items/{uuid:[a-f0-9-]{36}}', fn($uuid) => findItem($uuid));
```

Constraints ensure your handlers only run when the URL format is valid, making your application more predictable and secure.

## Quick Reference

| Method | HTTP Verb |
|--------|-----------|
| `->get($path, $handler)` | GET |
| `->post($path, $handler)` | POST |
| `->put($path, $handler)` | PUT |
| `->patch($path, $handler)` | PATCH |
| `->delete($path, $handler)` | DELETE |
| `->any($path, $handler)` | GET, POST, PUT, PATCH, DELETE |
| `->group($prefix, $callback)` | Group with shared prefix |
| `->route($prefix, $subApp)` | Mount sub-application |

All routing methods accept optional middleware and name parameters:

```php
app()->get('/admin/users', $handler, [AuthMiddleware::class], 'admin.users');
```
