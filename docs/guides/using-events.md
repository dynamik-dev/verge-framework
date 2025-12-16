---
title: Responding to Application Events
description: Run code in response to user actions, system events, and custom triggers using the event dispatcher.
---

When you need to run code in response to application events—sending welcome emails after user registration, invalidating caches when data changes, logging payment activity—register event listeners that fire when those events occur.

## Listening for Events

Register a listener with `on()` and trigger it with `emit()`:

```php
app()->on('user.created', fn($payload) => sendWelcomeEmail($payload['user']));

// Later, when a user is created
app()->emit('user.created', ['user' => $user]);
```

The listener runs whenever you emit that event.

## Using Class-Based Listeners

Listeners can be classes that resolve through the container:

```php
class SendWelcomeEmail
{
    public function __construct(private EmailService $emails) {}

    public function __invoke(array $payload): void
    {
        $this->emails->send($payload['user']['email'], 'Welcome!');
    }
}

app()->on('user.created', SendWelcomeEmail::class);
```

Dependencies are automatically injected when the listener runs.

## Running Multiple Actions per Event

You can attach multiple listeners to the same event:

```php
app()
    ->on('user.created', SendWelcomeEmail::class)
    ->on('user.created', UpdateAnalytics::class)
    ->on('user.created', LogUserCreation::class);
```

Listeners execute in registration order.

## Listening to Event Patterns

Use wildcards to match multiple events:

```php
// Match user.created, user.updated, user.deleted, etc.
app()->on('user.*', fn($payload) => logUserEvent($payload));

// Match every event
app()->on('*', fn($payload) => logAllEvents($payload));
```

## Invalidating Caches on Data Changes

Events are useful for cache invalidation without coupling your business logic to caching:

```php
app()->on('product.updated', function($payload) use ($cache) {
    $cache->forget('product:' . $payload['id']);
});

// Elsewhere, after updating a product
app()->emit('product.updated', ['id' => $product->id]);
```

## Sending Notifications

Run multiple side effects when something important happens:

```php
app()->on('order.completed', function($payload) {
    $order = $payload['order'];
    sendCustomerEmail($order);
    notifyWarehouse($order);
    updateInventory($order);
});
```

## Logging Activity

Track important events across a domain:

```php
app()->on('payment.*', function($payload) use ($logger) {
    $logger->info('Payment event', $payload);
});
```

This catches `payment.succeeded`, `payment.failed`, `payment.refunded`, and any other payment events you emit.

## Checking for Listeners

Skip emitting events if nothing is listening:

```php
if (app()->hasListeners('user.created')) {
    app()->emit('user.created', ['user' => $user]);
}
```

## Naming Events

Use dot notation to namespace related events:

- `user.created`, `user.updated`, `user.deleted`
- `order.completed`, `order.refunded`
- `payment.succeeded`, `payment.failed`

This makes wildcard matching intuitive and keeps your events organized.
