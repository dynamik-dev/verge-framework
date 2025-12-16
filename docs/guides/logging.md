---
title: Recording Application Events
description: Track errors, user actions, and debug information in your application.
---

When you need to track what happens in your application—errors, user actions, performance metrics—type-hint Verge's PSR-3 logger in any route or class:

```php
use Verge\Log\Logger;

app()->get('/action', function(Logger $log) {
    $log->info('User accessed action');
    return ['status' => 'ok'];
});
```

## Choosing the Right Log Level

Different events need different levels of urgency. Use `error()` for actual problems, `info()` for normal events, and `debug()` for detailed troubleshooting:

```php
$log->error('Payment gateway timeout');
$log->warning('API rate limit approaching');
$log->info('User logged in');
$log->debug('Cache key generated', ['key' => $cacheKey]);
```

PSR-3 defines eight severity levels: `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, and `debug`.

## Including Contextual Data

Log messages are more useful when they include relevant details. Pass an array as the second parameter:

```php
$log->info('User logged in', [
    'user_id' => 123,
    'ip' => '192.168.1.1'
]);

$log->error('Payment failed', [
    'order_id' => 456,
    'amount' => 99.99,
    'error' => $exception->getMessage()
]);
```

## Adding Context to Every Log

When you need the same context in multiple logs—like a request ID for tracing—use `withContext()` to create a logger that includes it automatically:

```php
$log = $log->withContext(['request_id' => generateRequestId()]);

$log->info('Processing started');
$log->info('Processing complete');
// Both logs include request_id
```

## Organizing Logs by Channel

Separate different parts of your application into channels so you can filter logs later:

```php
$userLog = $log->channel('users');
$paymentLog = $log->channel('payments');

$userLog->info('User created', ['id' => 123]);
$paymentLog->info('Payment processed', ['amount' => 50.00]);
```

## Logging Exceptions

When catching exceptions, log them with enough context to debug the issue later:

```php
app()->post('/charge', function(Request $req, Logger $log, PaymentGateway $gateway) {
    try {
        $result = $gateway->charge($req->json());
        $log->info('Payment succeeded', ['amount' => $result->amount]);
        return $result;
    } catch (PaymentException $e) {
        $log->error('Payment failed', [
            'error' => $e->getMessage(),
            'data' => $req->json()
        ]);
        return json(['error' => 'Payment failed'], 400);
    }
});
```

## Logging All HTTP Requests

Track every request that hits your application with middleware that logs the method, path, status code, and response time:

```php
class RequestLogger
{
    public function __construct(private Logger $log) {}

    public function __invoke(Request $req, callable $next): Response
    {
        $start = microtime(true);
        $response = $next($req);
        $duration = microtime(true) - $start;

        $this->log->info('Request processed', [
            'method' => $req->method(),
            'path' => $req->path(),
            'status' => $response->status(),
            'duration_ms' => round($duration * 1000, 2)
        ]);

        return $response;
    }
}

app()->use(RequestLogger::class);
```

## Detecting Slow Database Queries

Find performance bottlenecks by logging queries that take too long:

```php
class DatabaseLogger
{
    public function __construct(private Logger $log) {}

    public function logQuery(string $sql, float $duration): void
    {
        if ($duration > 0.1) {
            $this->log->warning('Slow query detected', [
                'sql' => $sql,
                'duration_ms' => round($duration * 1000, 2)
            ]);
        }
    }
}
```

## Logging Application Events

Capture all events your application emits for debugging or auditing:

```php
app()->on('*', function($payload) use ($log) {
    $log->debug('Event emitted', $payload);
});
```

## Configuring Where Logs Go

By default, Verge writes logs to `php://stderr`. Change this with environment variables:

```bash
# .env
LOG_DRIVER=stream
LOG_PATH=php://stderr
LOG_LEVEL=debug
```

The logger automatically uses whatever driver you configure—no code changes needed:

```php
app()->get('/action', function(Logger $log) {
    $log->info('Action performed');
    return 'ok';
});
```

## Testing with the Array Driver

In tests, use the array driver to capture logs in memory instead of writing to disk:

```bash
LOG_DRIVER=array
```

```php
use Verge\App;
use Verge\Log\Logger;

it('logs messages', function() {
    putenv('LOG_DRIVER=array');
    $app = new App();

    $app->get('/action', function(Logger $log) {
        $log->info('Test message');
        return 'ok';
    });

    $response = $app->test()->get('/action');
    expect($response->body())->toBe('ok');
});
```

## Writing Logs to a Database

Register a custom driver to send logs anywhere you want:

```php
use App\Log\DatabaseLogger;

app()->driver('log', 'database', function(App $app) {
    return new DatabaseLogger(
        $app->make(Database::class)
    );
});
```

Then set `LOG_DRIVER=database` in your environment.

See [Configuring Drivers](/guides/configuring-drivers/) for details on the driver system.
