---
title: Request Methods
description: Complete reference for Verge\Http\Request methods.
---

## Body Methods

| Method | Description |
|--------|-------------|
| `$req->json()` | Parsed JSON body as array |
| `$req->body()` | Raw request body string |

## Parameter Methods

| Method | Description |
|--------|-------------|
| `$req->input($key, $default)` | Body or query parameter |
| `$req->query($key, $default)` | Query string parameter |

## Header Methods

| Method | Description |
|--------|-------------|
| `$req->header($key)` | Single header value |
| `$req->headers()` | All headers as array |

## File Methods

| Method | Description |
|--------|-------------|
| `$req->file($key)` | UploadedFile object or null |

## Request Info

| Method | Description |
|--------|-------------|
| `$req->method()` | HTTP method (GET, POST, etc.) |
| `$req->path()` | Request path (e.g., `/users/1`) |
| `$req->url()` | Full URL |

## URL Methods

| Method | Description |
|--------|-------------|
| `$req->fullUrl()` | Full URL with scheme, host, path, and query string |
| `$req->hasValidSignature()` | Returns true if URL has valid, unexpired signature |

## Usage Example

```php
app()->post('/users', function(Request $req) {
    $body = $req->json();           // Parsed JSON body
    $name = $req->input('name');    // Body or query param
    $id = $req->query('id');        // Query string param
    $token = $req->header('Authorization');
    $file = $req->file('avatar');

    return ['received' => $body];
});
```
