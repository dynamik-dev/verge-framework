---
title: Returning Responses
description: Return JSON, text, redirects, and custom HTTP responses from your routes.
---

Verge converts your return values into HTTP responses automatically:

```php
app()
    ->get('/text', fn() => 'Hello')           // 200 text/plain
    ->get('/json', fn() => ['ok' => true])    // 200 application/json
    ->delete('/users/{id}', fn($id) => null); // 204 No Content
```

## Returning JSON

APIs typically send data as JSON. Return an array and Verge handles the rest:

```php
app()->get('/users', fn() => [
    'data' => [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ],
    'count' => 2
]);
```

Verge sets `Content-Type: application/json` and status `200` automatically.

## Returning Plain Text

Simple text responses come from returning a string:

```php
app()->get('/ping', fn() => 'pong');
```

## Returning HTML

When you need to render HTML pages, use the `html()` helper to set the proper Content-Type:

```php
use function Verge\html;

app()->get('/welcome', fn() => html('<h1>Welcome to Verge</h1>'));
```

This sets `Content-Type: text/html` automatically. Without the helper, returning a string gives you `text/plain` instead.

## Returning No Content

After deleting a resource or processing a fire-and-forget webhook, return `null` for a `204 No Content` response:

```php
app()->delete('/users/{id}', function($id) {
    // Delete the user from your database
    return null;
});
```

## Setting Custom Status Codes

When creating resources, use `201 Created` to signal success. The `json()` helper lets you specify the status:

```php
use function Verge\json;

app()->post('/users', function(Request $req) {
    $data = $req->json();
    // Insert into database and get the new record
    $user = ['id' => 1, 'name' => $data['name']];
    return json($user, 201);
});
```

For error responses, return the appropriate 4xx or 5xx status:

```php
use function Verge\json;

app()->get('/users/{id}', function($id) {
    // Fetch user from your database
    $user = null; // or ['id' => $id, 'name' => 'Alice']

    if (!$user) {
        return json(['error' => 'User not found'], 404);
    }

    return $user;
});
```

## Redirecting to Another URL

Send users to a different location with `redirect()`:

```php
use function Verge\redirect;

app()
    ->get('/old-page', fn() => redirect('/new-page'))
    ->get('/github', fn() => redirect('https://github.com', 301));
```

The default status is `302` (temporary redirect). Pass `301` for permanent redirects.

## Serving Files

When you want to display a file in the browser—like showing a PDF or image inline—use the `file()` helper:

```php
use function Verge\file;

app()->get('/reports/{id}', function($id) {
    return file("/var/reports/{$id}.pdf");
});
```

This streams the file content directly to the browser. Verge automatically detects the MIME type based on the file extension and sets the appropriate `Content-Type` header.

You can override the content type if needed:

```php
return file('/path/to/data.xml', 'application/xml');
```

## Downloading Files

To force a file download instead of displaying it inline, use the `download()` helper:

```php
use function Verge\download;

app()->get('/reports/{id}/download', function($id) {
    return download(
        "/var/reports/{$id}.pdf",
        "report-{$id}.pdf"
    );
});
```

This adds the `Content-Disposition: attachment` header, which tells the browser to save the file with the specified filename rather than displaying it.

If you need to specify the content type explicitly:

```php
return download('/path/to/export.bin', 'data.csv', 'text/csv');
```

## Building Custom Responses

For cases where you need full control over headers, use the `response()` helper:

```php
use function Verge\response;

app()->get('/data', function() {
    $content = generateCsvData();

    return response($content)
        ->header('Content-Type', 'text/csv')
        ->header('Content-Disposition', 'attachment; filename="data.csv"')
        ->header('Cache-Control', 'no-cache');
});
```

The `header()` method is chainable for setting multiple headers.

## Quick Reference

Import helpers from the Verge namespace:

```php
use function Verge\response;
use function Verge\json;
use function Verge\html;
use function Verge\redirect;
use function Verge\file;
use function Verge\download;
```

| Helper | Purpose |
|--------|---------|
| `response($body, $status, $headers)` | Full control over the response |
| `json($data, $status, $headers)` | JSON with proper Content-Type |
| `html($content, $status, $headers)` | HTML with proper Content-Type |
| `redirect($url, $status)` | Redirect (default 302) |
| `file($path, $contentType)` | Stream file for inline display |
| `download($path, $filename, $contentType)` | Force file download |

| Return Value | Response |
|--------------|----------|
| `'string'` | 200 text/plain |
| `['array']` | 200 application/json |
| `null` | 204 No Content |
| `Response` | Sent as-is |
