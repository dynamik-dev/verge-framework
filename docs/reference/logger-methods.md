---
title: Logger Methods
description: Complete reference for PSR-3 logger methods.
---

## Log Levels

All methods accept a message string and optional context array:

| Method | Level | Use When |
|--------|-------|----------|
| `$log->emergency($msg, $ctx)` | Emergency | System is unusable |
| `$log->alert($msg, $ctx)` | Alert | Immediate action required |
| `$log->critical($msg, $ctx)` | Critical | Critical conditions |
| `$log->error($msg, $ctx)` | Error | Runtime errors |
| `$log->warning($msg, $ctx)` | Warning | Exceptional occurrences not errors |
| `$log->notice($msg, $ctx)` | Notice | Normal but significant |
| `$log->info($msg, $ctx)` | Info | Interesting events |
| `$log->debug($msg, $ctx)` | Debug | Detailed debug information |

## Additional Methods

| Method | Description |
|--------|-------------|
| `$log->withContext($context)` | Add default context to all messages |
| `$log->channel($name)` | Create logger with channel in context |

## Examples

### Basic Logging

```php
$log->info('User logged in');
$log->error('Database connection failed');
$log->debug('Query executed', ['sql' => $sql, 'time' => $duration]);
```

### With Context

```php
$log->info('Payment processed', [
    'user_id' => 123,
    'amount' => 50.00,
    'method' => 'credit_card'
]);
```

### Default Context

```php
$log = $log->withContext(['request_id' => generateId()]);

$log->info('Processing started');  // Includes request_id
$log->info('Processing complete'); // Includes request_id
```

### Channels

```php
$userLog = $log->channel('users');
$paymentLog = $log->channel('payments');

$userLog->info('User created', ['id' => 123]);
$paymentLog->info('Payment succeeded', ['amount' => 99.99]);
```

### Exception Logging

```php
try {
    riskyOperation();
} catch (Exception $e) {
    $log->error('Operation failed', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
```
