---
title: Response Methods
description: Complete reference for response helpers and Response methods.
---

## Helper Functions

Import from the Verge namespace:

```php
use function Verge\response;
use function Verge\json;
use function Verge\html;
use function Verge\redirect;
use function Verge\file;
use function Verge\download;
```

| Function | Description |
|----------|-------------|
| `response($body, $status, $headers)` | Create Response with body, status, and headers |
| `json($data, $status, $headers)` | Create JSON response with Content-Type header |
| `html($content, $status, $headers)` | Create HTML response with Content-Type: text/html |
| `redirect($url, $status)` | Create redirect response (default 302) |
| `file($path, $contentType)` | Stream file inline (auto-detects MIME type) |
| `download($path, $filename, $contentType)` | Force file download with Content-Disposition header |

## Response Object Methods

| Method | Description |
|--------|-------------|
| `$response->body()` | Get response body |
| `$response->status()` | Get HTTP status code |
| `$response->header($name, $value)` | Add or set header, returns new Response |
| `$response->getHeader($name)` | Get header value(s) |
| `$response->json()` | Parse response body as JSON array (useful for testing) |

## Implicit Conversions

Verge automatically converts return values:

| Return Type | Response |
|-------------|----------|
| `string` | 200 OK, text/plain |
| `array` | 200 OK, application/json |
| `null` | 204 No Content |
| `Response` | Used as-is |

## Examples

### Plain Text

```php
app()->get('/hello', fn() => 'Hello World');
```

### JSON

```php
app()->get('/users', fn() => ['data' => User::all()]);
```

### With Status Code

```php
use function Verge\json;

app()->post('/users', fn() => json(['id' => 1], 201));
```

### With Headers

```php
use function Verge\response;

app()->get('/download', fn() => response('content')
    ->header('Content-Type', 'application/pdf')
    ->header('Content-Disposition', 'attachment')
);
```

### Redirect

```php
use function Verge\redirect;

app()->get('/old', fn() => redirect('/new'));
app()->get('/moved', fn() => redirect('/new-home', 301));
```

### HTML

```php
use function Verge\html;

app()->get('/welcome', fn() => html('<h1>Welcome</h1>'));
app()->get('/error', fn() => html('<p>Not found</p>', 404));
```

### File Streaming

```php
use function Verge\file;

app()->get('/image', fn() => file('/path/to/image.png'));
app()->get('/pdf', fn() => file('/path/to/doc.pdf', 'application/pdf'));
```

### File Download

```php
use function Verge\download;

app()->get('/export', fn() => download('/path/to/data.csv', 'users.csv'));
app()->get('/report', fn() => download('/path/to/file.pdf', 'report.pdf', 'application/pdf'));
```
