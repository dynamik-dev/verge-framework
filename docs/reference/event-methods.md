---
title: Event Methods
description: Complete reference for event dispatcher methods.
---

## Core Methods

| Method | Description |
|--------|-------------|
| `->on($event, $listener)` | Register event listener |
| `->emit($event, $payload)` | Emit event to all listeners |
| `->hasListeners($event)` | Check if event has listeners |

## Event Names

Use dot notation for namespacing:

- `user.created`, `user.updated`, `user.deleted`
- `order.completed`, `order.refunded`
- `payment.succeeded`, `payment.failed`

## Listener Types

### Closure Listeners

```php
app()->on('user.created', fn($payload) => sendEmail($payload['user']));
```

### Class String Listeners

```php
app()->on('user.created', SendWelcomeEmail::class);
```

Class is resolved through the container with dependency injection.

### Invokable Class Listeners

```php
class SendWelcomeEmail
{
    public function __construct(private EmailService $emails) {}

    public function __invoke(array $payload): void
    {
        $this->emails->send($payload['user']['email'], 'Welcome!');
    }
}
```

## Wildcard Matching

| Pattern | Matches |
|---------|---------|
| `user.*` | `user.created`, `user.updated`, `user.deleted` |
| `*` | All events |

## Examples

### Multiple Listeners

```php
app()
    ->on('user.created', SendWelcomeEmail::class)
    ->on('user.created', UpdateAnalytics::class)
    ->on('user.created', LogUserCreation::class);
```

### Wildcard Listener

```php
app()->on('user.*', fn($payload) => logUserEvent($payload));
app()->on('*', fn($payload) => logAllEvents($payload));
```

### Conditional Emit

```php
if (app()->hasListeners('order.completed')) {
    app()->emit('order.completed', ['order' => $order]);
}
```

### Event Payload

```php
app()->emit('payment.succeeded', [
    'amount' => 99.99,
    'currency' => 'USD',
    'customer_id' => 123,
    'timestamp' => time()
]);
```
