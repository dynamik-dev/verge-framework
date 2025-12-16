---
title: Organizing Routes with Controllers
description: Extract route handlers into dedicated classes for larger applications.
---

Once your closures grow beyond a few lines, extract them into controller classes:

```php
<?php

use App\Controllers\UserController;

app()
    ->get('/users', [UserController::class, 'index'])
    ->post('/users', [UserController::class, 'store'])
    ->get('/users/{id}', [UserController::class, 'show']);
```

## Creating Controllers

Controllers are plain PHP classes. Type-hint dependencies in the constructor and the container injects them automatically:

```php
<?php

namespace App\Controllers;

use App\Repositories\UserRepository;
use Verge\Http\Request;

class UserController
{
    public function __construct(private UserRepository $users) {}

    public function index()
    {
        return $this->users->all();
    }

    public function store(Request $req)
    {
        return $this->users->create($req->json());
    }

    public function show($id)
    {
        return $this->users->find($id);
    }
}
```

Route parameters and type-hinted objects are both injected into each method.

## Injecting Services into Methods

When different actions need different dependencies, type-hint them in the method signature:

```php
<?php

namespace App\Controllers;

use App\Repositories\OrderRepository;
use App\Services\PaymentGateway;
use Verge\Http\Request;

class OrderController
{
    public function store(Request $req, PaymentGateway $payments, OrderRepository $orders)
    {
        $order = $orders->create($req->json());
        $payments->charge($order['total']);

        return $order;
    }
}
```

The container resolves each dependency for every request.

## Using Invokable Controllers

Single-purpose endpoints like webhooks or OAuth callbacks work well as invokable classes:

```php
<?php

use App\Handlers\WebhookHandler;

app()->post('/webhook', WebhookHandler::class);
```

Define the handler logic in an `__invoke` method:

```php
<?php

namespace App\Handlers;

use App\Services\WebhookService;
use Verge\Http\Request;

class WebhookHandler
{
    public function __construct(private WebhookService $webhooks) {}

    public function __invoke(Request $req)
    {
        return $this->webhooks->process($req->json());
    }
}
```

## When to Use Controllers

Use closures for prototypes, simple APIs, and endpoints with minimal logic. Switch to controllers when:

- Handler logic exceeds a few lines
- Multiple routes share dependencies
- You need to unit test handlers in isolation
- Your team prefers explicit class-based structure

There's no performance difference between the two approaches.
