---
title: Replacing Dependencies in Tests
description: Swap real services with test doubles to isolate route logic.
---

Routes that depend on databases, APIs, or other external services need those dependencies replaced during testing. Use the container's `bind()` or `instance()` methods to swap implementations.

## Binding Test Implementations

Replace a real repository with an in-memory version that returns predictable data:

```php
it('returns a user from repository', function() {
    $app = new App();

    $app->bind(UserRepositoryInterface::class, fn() => new InMemoryUserRepository([
        '1' => ['id' => '1', 'name' => 'Test User']
    ]));

    $app->get('/users/{id}', fn($id, UserRepositoryInterface $repo) => $repo->find($id));

    $response = $app->test()->get('/users/1');

    expect($response->json()['name'])->toBe('Test User');
});
```

The route receives the test implementation instead of the real one.

## Using Anonymous Classes for Quick Doubles

Creating a file for every test double gets tedious. Anonymous classes let you define test implementations inline:

```php
$app->bind(UserRepositoryInterface::class, fn() => new class implements UserRepositoryInterface {
    public function find($id): ?array
    {
        return ['id' => $id, 'name' => 'Fake User'];
    }

    public function all(): array
    {
        return [];
    }
});
```

This works well for simple cases where you just need something that conforms to an interface.

## Creating Reusable In-Memory Implementations

For more complex scenarios, create proper test double classes you can reuse across tests:

```php
class InMemoryUserRepository implements UserRepositoryInterface
{
    public function __construct(private array $users = []) {}

    public function find($id): ?array
    {
        return $this->users[$id] ?? null;
    }

    public function all(): array
    {
        return array_values($this->users);
    }

    public function create(array $data): array
    {
        $id = (string) (count($this->users) + 1);
        $this->users[$id] = ['id' => $id, ...$data];
        return $this->users[$id];
    }
}
```

Now you can set up test data in each test and verify your routes handle different scenarios correctly.

## Using Mockery for Mocks and Spies

Routes that need to verify method calls or complex behavior work better with Mockery:

```php
use Mockery;

it('calls payment gateway', function() {
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('charge')
        ->once()
        ->with(1000)
        ->andReturn(['success' => true]);

    $app = new App();
    $app->instance(PaymentGateway::class, $gateway);

    $app->post('/charge', fn(PaymentGateway $gw) => $gw->charge(1000));

    $response = $app->test()->post('/charge');

    expect($response->json()['success'])->toBe(true);
});
```

The `instance()` method binds an already-constructed object, which is what mocking libraries return.

## Verifying Mock Expectations

Mockery expectations must be verified. Pest automatically runs `Mockery::close()` after each test, but you can be explicit:

```php
use Mockery;

it('sends welcome email when user created', function() {
    $mailer = Mockery::mock(Mailer::class);
    $mailer->shouldReceive('send')
        ->once()
        ->with('user@example.com', 'Welcome!');

    $app = new App();
    $app->instance(Mailer::class, $mailer);

    $app->post('/users', function(Request $req, Mailer $mailer) {
        $user = createUser($req->getParsedBody());
        $mailer->send($user['email'], 'Welcome!');
        return $user;
    });

    $response = $app->test()->post('/users', [
        'email' => 'user@example.com'
    ]);

    expect($response->status())->toBe(200);
    // Mockery::close() called automatically by Pest
});
```

## Using Spies to Verify Calls

Spies let you verify calls after execution, useful when you don't want to set up expectations beforehand:

```php
use Mockery;

it('logs user creation', function() {
    $logger = Mockery::spy(LoggerInterface::class);

    $app = new App();
    $app->instance(LoggerInterface::class, $logger);

    $app->post('/users', function(Request $req, LoggerInterface $logger) {
        $user = createUser($req->getParsedBody());
        $logger->info('User created', ['id' => $user['id']]);
        return $user;
    });

    $response = $app->test()->post('/users', ['name' => 'John']);

    $logger->shouldHaveReceived('info')
        ->with('User created', Mockery::type('array'));
});
```

## Partial Mocks

Sometimes you want to mock only specific methods while keeping others real:

```php
use Mockery;

it('uses real implementation for some methods', function() {
    $service = Mockery::mock(UserService::class)->makePartial();
    $service->shouldReceive('sendNotification')
        ->andReturn(true);

    $app = new App();
    $app->instance(UserService::class, $service);

    $app->post('/users', function(Request $req, UserService $service) {
        $user = $service->createUser($req->getParsedBody());  // Real method
        $service->sendNotification($user);                     // Mocked method
        return $user;
    });

    $response = $app->test()->post('/users', ['name' => 'John']);

    expect($response->status())->toBe(200);
});
```

## Testing Routes Protected by Middleware

Middleware often depends on services like authentication. Replace those services to control whether middleware passes or fails:

```php
it('rejects unauthorized requests', function() {
    $app = new App();

    $app->bind(AuthService::class, fn() => new class {
        public function check($token): bool
        {
            return $token === 'valid-token';
        }
    });

    $app->get('/admin', fn() => 'admin', middleware: [AuthMiddleware::class]);

    // Without token
    $response = $app->test()->get('/admin');
    expect($response->status())->toBe(401);

    // With valid token
    $response = $app->test()
        ->withHeader('Authorization', 'valid-token')
        ->get('/admin');
    expect($response->status())->toBe(200);
});
```

Your fake auth service lets you test both the success and failure paths without dealing with real authentication.

## Mocking External APIs

Routes that call external APIs should never make real HTTP requests in tests:

```php
use Mockery;
use Psr\Http\Client\ClientInterface;

it('fetches data from external API', function() {
    $httpClient = Mockery::mock(ClientInterface::class);
    $httpClient->shouldReceive('sendRequest')
        ->once()
        ->andReturn(new Response(200, [], json_encode(['data' => 'test'])));

    $app = new App();
    $app->instance(ClientInterface::class, $httpClient);

    $app->get('/external', function(ClientInterface $client) {
        $request = new Request('GET', 'https://api.example.com/data');
        $response = $client->sendRequest($request);
        return json_decode($response->getBody()->getContents(), true);
    });

    $response = $app->test()->get('/external');

    expect($response->json())->toBe(['data' => 'test']);
});
```

## Contextual Binding for Tests

Different routes might need different implementations of the same interface:

```php
it('uses different implementations per route', function() {
    $app = new App();

    // Admin routes use real implementation
    $app->bind(UserService::class, fn() => new RealUserService())
        ->for([AdminController::class]);

    // Public routes use fake
    $app->bind(UserService::class, fn() => new FakeUserService())
        ->for([PublicController::class]);

    $app->controller(AdminController::class);
    $app->controller(PublicController::class);

    // Test both routes with different implementations
    $response1 = $app->test()->get('/admin/users');
    $response2 = $app->test()->get('/public/users');

    expect($response1->status())->toBe(200);
    expect($response2->status())->toBe(200);
});
```

## Complete Mocking Example

```php
use Mockery;
use Verge\App;

describe('Order API', function() {
    it('processes order with mocked dependencies', function() {
        $app = new App();

        // Mock payment gateway
        $paymentGateway = Mockery::mock(PaymentGateway::class);
        $paymentGateway->shouldReceive('charge')
            ->once()
            ->with(100, 'tok_123')
            ->andReturn(['id' => 'ch_123', 'status' => 'succeeded']);

        // Spy on notification service
        $notifier = Mockery::spy(NotificationService::class);

        // Fake order repository
        $orderRepo = new class {
            public function create(array $data): array {
                return ['id' => 'ord_123', ...$data];
            }
        };

        // Bind all dependencies
        $app->instance(PaymentGateway::class, $paymentGateway);
        $app->instance(NotificationService::class, $notifier);
        $app->instance(OrderRepository::class, $orderRepo);

        // Define route
        $app->post('/orders', function(
            Request $request,
            PaymentGateway $payment,
            NotificationService $notifier,
            OrderRepository $orders
        ) {
            $data = $request->getParsedBody();

            // Charge payment
            $charge = $payment->charge($data['amount'], $data['token']);

            // Create order
            $order = $orders->create([
                'amount' => $data['amount'],
                'charge_id' => $charge['id']
            ]);

            // Send notification
            $notifier->send($data['email'], 'Order confirmed');

            return $order;
        });

        // Execute test
        $response = $app->test()->post('/orders', [
            'amount' => 100,
            'token' => 'tok_123',
            'email' => 'user@example.com'
        ]);

        // Verify response
        expect($response->status())->toBe(200);
        expect($response->json())->toHaveKey('id');

        // Verify notification was sent
        $notifier->shouldHaveReceived('send')
            ->with('user@example.com', 'Order confirmed');
    });
});
```
