---
title: Handling Requests
description: Access JSON bodies, query strings, headers, and uploaded files from incoming HTTP requests.
---

Type-hint `Request` in your route handler and the container will inject it automatically:

```php
app()->post('/users', function(Request $req) {
    $data = $req->json();
    return ['created' => $data];
});
```

## Reading JSON Request Bodies

APIs typically receive data as JSON. The `json()` method parses the body into an array:

```php
app()->post('/users', function(Request $req) {
    $data = $req->json();
    $name = $data['name'] ?? null;
    $email = $data['email'] ?? null;

    // Insert into your database
    return ['name' => $name, 'email' => $email];
});
```

If you need the raw body string instead:

```php
$raw = $req->body();
```

## Working with Query Parameters

For endpoints like `/search?q=verge&page=2`, use `query()` to pull values from the URL:

```php
app()->get('/search', function(Request $req) {
    $query = $req->query('q');
    $page = $req->query('page', 1);  // Default to page 1

    return ['query' => $query, 'page' => $page, 'results' => []];
});
```

Call `query()` without arguments to get all parameters at once:

```php
$allParams = $req->query();
```

## Accessing Headers

Authentication tokens and API metadata usually come through headers:

```php
app()->get('/me', function(Request $req) {
    $token = $req->header('Authorization');

    // Validate token and fetch user
    return ['token' => $token];
});
```

To inspect all headers:

```php
$allHeaders = $req->headers();
```

## Handling File Uploads

File uploads arrive as `UploadedFile` objects with methods for moving and inspecting the file:

```php
app()->post('/avatar', function(Request $req) {
    $file = $req->file('avatar');

    $file->moveTo('/uploads/' . $file->getClientFilename());

    return ['uploaded' => $file->getClientFilename()];
});
```

The `UploadedFile` object provides PSR-7 methods and helpful shortcuts:

| Method | Returns |
|--------|---------|
| `$file->moveTo($path)` | Moves file to destination |
| `$file->name()` | Original filename (alias for `getClientFilename()`) |
| `$file->type()` | MIME type (alias for `getClientMediaType()`) |
| `$file->size()` | File size in bytes (alias for `getSize()`) |
| `$file->path()` | Temporary file path |
| `$file->getError()` | Upload error code |
| `$file->getStream()` | File contents as stream |

## Checking Multiple Input Sources

When a parameter might come from either the request body or the query string, `input()` checks both:

```php
$search = $req->input('search');              // Body first, then query
$search = $req->input('search', 'default');   // With fallback
```

This is handy for endpoints that accept the same parameter in different contexts.

## Inspecting the Request

To get metadata about the request itself:

```php
$method = $req->method();  // GET, POST, etc.
$path = $req->path();      // /users/123
$url = $req->url();        // https://example.com/users/123?active=1
```

## Verifying Signed URLs

When you need to send time-limited, tamper-proof links—think password resets, email verification, or temporary download URLs—you can generate signed URLs and verify them in your route handlers.

Generate a signed URL using the `signed_route()` helper:

```php
use function Verge\signed_route;

// Create a signed URL that expires in 1 hour
$url = signed_route('verify-email', ['user' => 123], expiration: time() + 3600);

// Send this URL in an email
```

Then verify the signature in your route handler with `hasValidSignature()`:

```php
app()->get('/verify-email/{user}', function(Request $req, $user) {
    if (!$req->hasValidSignature()) {
        return json(['error' => 'Invalid or expired link'], 403);
    }

    // Signature is valid—the URL hasn't been tampered with and hasn't expired
    // Proceed with verification
    return ['verified' => true, 'user' => $user];
})->name('verify-email');
```

If someone modifies the URL parameters or the signature expires, `hasValidSignature()` returns false.

You can also get the complete URL including query parameters with `fullUrl()`:

```php
$fullUrl = $req->fullUrl();  // https://example.com/verify-email/123?expires=...&signature=...
```

## Quick Reference

| Method | Returns |
|--------|---------|
| `$req->json()` | Parsed JSON body as array |
| `$req->body()` | Raw request body string |
| `$req->input($key, $default)` | Body or query parameter |
| `$req->query($key, $default)` | Query string parameter |
| `$req->query()` | All query parameters |
| `$req->header($key)` | Single header value |
| `$req->headers()` | All headers |
| `$req->file($key)` | UploadedFile object |
| `$req->method()` | HTTP method (GET, POST, etc.) |
| `$req->path()` | Request path |
| `$req->url()` | Full URL |
| `$req->fullUrl()` | Full URL with query string |
| `$req->hasValidSignature()` | Whether URL signature is valid |
