---
title: Getting Started
description: Install Verge and build your first app.
---

## Installing Verge

```bash
composer require verge/verge
```

## Building Your First App

Create `index.php`:

```php
<?php

require 'vendor/autoload.php';

app()
    ->get('/', fn() => 'Hello Verge')
    ->run();
```

Start PHP's built-in server:

```bash
php -S localhost:8000 index.php
```

Visit `http://localhost:8000`. That's it. You have a working web app.

## Understanding the app() Helper

The `app()` helper returns a global singleton instance of your application. This means you can define routes and configure services from anywhere in your codebase, and they all register to the same app:

```php
<?php

require 'vendor/autoload.php';

// Define your routes
app()
    ->get('/', fn() => 'Welcome')
    ->get('/about', fn() => 'About us');

// Configure dependencies
app()
    ->singleton('db', fn() => new Database())
    ->bind(UserRepository::class, PostgresUserRepository::class);

app()->run();
```

This approach makes Verge feel more like Express or Hono if you're coming from JavaScript. Split your routes across multiple files, require them in your entry point, and everything just works.

## When You Need Isolation

Sometimes you need a fresh app instance that doesn't share global state. This is common in testing or when embedding multiple mini-apps in the same process:

```php
use Verge\App;

$app = App::create();
$app->get('/', fn() => 'Isolated instance');
```

`App::create()` gives you a new instance every time, bypassing the singleton. Use this when you want explicit control over the app lifecycle.

## Organizing Larger Applications

As your app grows, you'll want to organize routes and configuration into logical chunks. Verge uses modules for this - callables that receive the app and configure it:

```php
<?php

require 'vendor/autoload.php';

// Define a module
$routes = function ($app) {
    $app->get('/', fn() => 'Home');
    $app->get('/about', fn() => 'About');
};

// Register it
app()->module($routes);
app()->run();
```

Modules can define routes, register services, or wire up event listeners. Think of them as configuration bundles. We'll cover this pattern in depth later, but it's good to know it exists as your app outgrows a single file.

## What's Next?

- [Defining Routes](/guides/routing-basics/) - Learn about route parameters and HTTP methods
- [Handling Incoming Requests](/guides/handling-requests/) - Access request data, headers, and files
- [Returning Different Response Types](/guides/returning-responses/) - Return JSON, redirects, and custom responses
