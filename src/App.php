<?php

declare(strict_types=1);

namespace Verge;

use Closure;
use Verge\Cache;
use Verge\Log;
use Verge\Concerns\HasMiddleware;
use Verge\Events\EventDispatcher;
use Verge\Http\Request;
use Verge\Http\Response;
use Verge\Routing\Route;
use Verge\Routing\Router;
use Verge\Routing\RouterInterface;
use Verge\Routing\RouteGroup;
use Verge\Routing\Routes;
use Verge\Testing\TestClient;
use Psr\Container\ContainerInterface;

class App
{
    use HasMiddleware;

    public Container $container;
    protected ?RouterInterface $router = null;
    protected ?Env $env = null;
    protected ?EventDispatcher $events = null;
    protected string $currentPrefix = '';
    protected ?RouteGroup $currentGroup = null;
    protected bool $booted = false;

    /** @var array<string, array<string, \Closure>> */
    protected array $drivers = [];

    /** @var array<string, string> Default driver names per service */
    protected array $defaultDrivers = [];

    public function __construct(?ContainerInterface $container = null)
    {
        if ($container === null) {
            // No container = use defaults (Router, Env)
            $this->container = (new Container())->defaults();
        } elseif ($container instanceof Container) {
            $this->container = $container;
        } else {
            // External PSR-11 container - must be wrapped or incompatible if we rely on Container methods
            // For now, if it's not our Container, we can't assign it to public Container $container.
            throw new \InvalidArgumentException('App requires instance of Verge\Container');
        }

        // Make App resolvable from container
        $this->container->instance(App::class, $this);
        $this->container->instance(static::class, $this);

        $this->registerDefaultDrivers();
    }

    /**
     * Register framework default drivers.
     */
    protected function registerDefaultDrivers(): void
    {
        // Cache drivers
        $this->driver('cache', 'memory', fn () => new Cache\Drivers\MemoryCacheDriver());
        $this->defaultDriver('cache', 'memory');

        // Wire CacheInterface to use driver system
        $this->singleton(Cache\CacheInterface::class, fn () => $this->driver('cache'));

        // Log drivers
        $logPath = $this->env('LOG_PATH', 'php://stderr');
        $logLevel = $this->env('LOG_LEVEL', 'debug');

        $this->driver('log', 'stream', fn () => new Log\Drivers\StreamLogDriver(
            is_scalar($logPath) ? (string)$logPath : 'php://stderr',
            Log\LogLevel::from(is_scalar($logLevel) ? (string)$logLevel : 'debug')
        ));
        $this->driver('log', 'array', fn () => new Log\Drivers\ArrayLogDriver());
        $this->defaultDriver('log', 'stream');

        // Wire LoggerInterface to use driver system
        $this->singleton(Log\LoggerInterface::class, fn () => $this->driver('log'));
    }

    protected function router(): RouterInterface
    {
        if ($this->router !== null) {
            return $this->router;
        }
        $router = $this->container->resolve(RouterInterface::class);
        if (!$router instanceof RouterInterface) {
            throw new \RuntimeException('Resolved service is not a RouterInterface');
        }
        return $this->router = $router;
    }

    protected function getEnv(): Env
    {
        if ($this->env !== null) {
            return $this->env;
        }
        $env = $this->container->resolve(Env::class);
        if (!$env instanceof Env) {
            throw new \RuntimeException('Resolved service is not an Env');
        }
        return $this->env = $env;
    }

    protected function events(): EventDispatcher
    {
        if ($this->events !== null) {
            return $this->events;
        }
        $events = $this->container->resolve(EventDispatcher::class);
        if (!$events instanceof EventDispatcher) {
            throw new \RuntimeException('Resolved service is not an EventDispatcher');
        }
        return $this->events = $events;
    }

    /**
     * Boot the application.
     *
     * Emits 'app.ready' event once, allowing deferred configuration.
     */
    protected function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;
        $this->emit('app.ready');
    }

    /**
     * Check if the application has been booted.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    // Event methods

    /**
     * Register an event listener.
     */
    public function on(string $event, callable|string $listener): static
    {
        $this->events()->on($event, $listener);
        return $this;
    }

    /**
     * Emit an event to all registered listeners.
     * @param array<mixed> $payload
     */
    public function emit(string $event, array $payload = []): static
    {
        $this->events()->emit($event, $payload);
        return $this;
    }

    /**
     * Check if any listeners are registered for an event.
     */
    public function hasListeners(string $event): bool
    {
        return $this->events()->hasListeners($event);
    }

    // Routing methods (forwarded to router)

    /**
     * @param array<int, string> $middleware
     * @param callable|array<mixed>|string $handler
     */
    public function get(string $path, callable|array|string $handler, array $middleware = [], ?string $name = null): static
    {
        $this->addRoute('GET', $path, $handler, $middleware, $name);
        return $this;
    }

    /**
     * @param array<int, string> $middleware
     * @param callable|array<mixed>|string $handler
     */
    public function post(string $path, callable|array|string $handler, array $middleware = [], ?string $name = null): static
    {
        $this->addRoute('POST', $path, $handler, $middleware, $name);
        return $this;
    }

    /**
     * @param array<int, string> $middleware
     * @param callable|array<mixed>|string $handler
     */
    public function put(string $path, callable|array|string $handler, array $middleware = [], ?string $name = null): static
    {
        $this->addRoute('PUT', $path, $handler, $middleware, $name);
        return $this;
    }

    /**
     * @param array<int, string> $middleware
     * @param callable|array<mixed>|string $handler
     */
    public function patch(string $path, callable|array|string $handler, array $middleware = [], ?string $name = null): static
    {
        $this->addRoute('PATCH', $path, $handler, $middleware, $name);
        return $this;
    }

    /**
     * @param array<int, string> $middleware
     * @param callable|array<mixed>|string $handler
     */
    public function delete(string $path, callable|array|string $handler, array $middleware = [], ?string $name = null): static
    {
        $this->addRoute('DELETE', $path, $handler, $middleware, $name);
        return $this;
    }

    /**
     * @param array<int, string> $middleware
     * @param callable|array<mixed>|string $handler
     */
    public function any(string $path, callable|array|string $handler, array $middleware = [], ?string $name = null): static
    {
        $this->addRoute('ANY', $path, $handler, $middleware, $name);
        return $this;
    }

    /**
     * @param array<int, string> $middleware
     * @param callable|array<mixed>|string $handler
     */
    protected function addRoute(string $method, string $path, callable|array|string $handler, array $middleware = [], ?string $name = null): Route
    {
        $fullPath = $this->currentPrefix . $path;

        if ($method === 'ANY') {
            $route = $this->router()->any($fullPath, $handler);
        } else {
            $route = $this->router()->add($method, $fullPath, $handler);
        }

        // Apply global middleware to route
        foreach ($this->middleware as $mw) {
            $route->use($mw);
        }

        // Apply route-specific middleware
        foreach ($middleware as $mw) {
            $route->use($mw);
        }

        // Register named route if name provided
        if ($name !== null) {
            $route->name($name);
            $this->router()->registerNamedRoute($name, $route);
        }

        // Track route in current group if inside one
        if ($this->currentGroup !== null) {
            $this->currentGroup->addRoute($route);
        }

        return $route;
    }

    // Container methods

    public function bind(string $abstract, Closure|string $concrete): static
    {
        $this->container->bind($abstract, $concrete);
        return $this;
    }

    public function singleton(string $abstract, Closure|string $concrete): static
    {
        $this->container->singleton($abstract, $concrete);
        return $this;
    }

    public function scoped(string $abstract, Closure|string $concrete): static
    {
        $this->container->scoped($abstract, $concrete);
        return $this;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->container->resolve($abstract, $parameters);
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->container->instance($abstract, $instance);
    }

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    /**
     * Get the underlying PSR-11 container.
     */
    public function container(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Register or resolve a driver.
     *
     * When called with 3 args: registers a driver factory
     * When called with 1 arg: resolves the current driver based on ENV
     *
     * @param string $service The service name (e.g., 'cache', 'log', 'queue')
     * @param string|null $name The driver name (e.g., 'redis', 'file')
     * @param \Closure|null $factory Factory that creates the driver instance
     * @return static|mixed Returns $this when registering, driver instance when resolving
     */
    public function driver(string $service, ?string $name = null, ?\Closure $factory = null): mixed
    {
        // Resolving: driver('cache') -> reads CACHE_DRIVER env, returns instance
        if ($name === null && $factory === null) {
            $envKey = strtoupper($service) . '_DRIVER';
            $driverName = $this->env($envKey) ?? ($this->defaultDrivers[$service] ?? null);

            $driverName = is_scalar($driverName) ? (string) $driverName : null;

            if ($driverName === null) {
                throw new \RuntimeException(
                    "No driver configured for '{$service}'. Set the {$envKey} environment variable."
                );
            }

            if (!isset($this->drivers[$service][$driverName])) {
                throw new \RuntimeException(
                    "Unknown {$service} driver '{$driverName}'. Available drivers: " .
                    implode(', ', array_keys($this->drivers[$service] ?? []))
                );
            }

            return $this->drivers[$service][$driverName]($this);
        }

        // Registering: driver('cache', 'redis', fn() => new RedisCache())
        if ($name !== null && $factory !== null) {
            $this->drivers[$service][$name] = $factory;
            return $this;
        }

        throw new \InvalidArgumentException(
            'driver() requires either 1 argument (to resolve) or 3 arguments (to register)'
        );
    }

    /**
     * Set the default driver for a service.
     *
     * @param string $service The service name (e.g., 'cache', 'log')
     * @param string $name The default driver name
     */
    public function defaultDriver(string $service, string $name): static
    {
        $this->defaultDrivers[$service] = $name;
        return $this;
    }

    /**
     * Generate URL for a named route.
     * @param array<string, mixed> $params
     */
    public function url(string $name, array $params = []): string
    {
        return $this->router()->url($name, $params);
    }

    // Configuration

    public function env(string $key, mixed $default = null): mixed
    {
        return $this->getEnv()->get($key, $default);
    }

    /**
     * @param callable(App): void|string|array<mixed> $provider
     */
    public function configure(callable|string|array $provider): static
    {
        if (is_array($provider)) {
            foreach ($provider as $p) {
                /** @var callable(App): void|string|array<mixed> $p */
                $this->configure($p);
            }
            return $this;
        }

        if (is_string($provider)) {
            $provider = $this->container->resolve($provider);
        }

        if (!is_callable($provider)) {
            throw new \RuntimeException('Provider must be callable');
        }
        $provider($this);
        return $this;
    }

    /**
     * Get route introspection or configure routes.
     *
     * When called with no arguments, returns a Routes instance for introspection.
     * When called with a callable or RouterInterface, configures routes.
     *
     * @param callable|RouterInterface|null $routes
     * @return ($routes is null ? Routes : static)
     */
    public function routes(callable|RouterInterface|null $routes = null): Routes|static
    {
        // No arguments - return Routes for introspection
        if ($routes === null) {
            return new Routes($this->router());
        }

        if ($routes instanceof RouterInterface) {
            // Merge routes from provided router
            foreach ($routes->getRoutes() as $method => $methodRoutes) {
                foreach ($methodRoutes as $route) {
                    // Apply global middleware to each route
                    foreach ($this->middleware as $middleware) {
                        $route->use($middleware);
                    }
                }
            }
            // Replace router with the provided one
            $this->router = $routes;
            return $this;
        }

        // Callable - pass router
        $routes($this->router());
        return $this;
    }

    public function group(string $prefix, callable $callback): RouteGroup
    {
        $group = new RouteGroup($prefix);

        // Save current context
        $previousPrefix = $this->currentPrefix;
        $previousGroup = $this->currentGroup;

        // Set new context
        $this->currentPrefix = $previousPrefix . $prefix;
        $this->currentGroup = $group;

        try {
            $callback($this);
        } finally {
            // Restore previous context
            $this->currentPrefix = $previousPrefix;
            $this->currentGroup = $previousGroup;
        }

        return $group;
    }

    /**
     * Mount a sub-app at a prefix (Hono-style).
     */
    public function route(string $prefix, App $subApp): static
    {
        $routes = $subApp->router()->getRoutes();
        foreach ($routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {

                $handler = $route->handler;
                if (!is_array($handler) && !is_string($handler) && !is_callable($handler)) {
                    // Should theoretically not happen if Route is consistent
                    throw new \RuntimeException('Invalid handler type in sub-app route');
                }

                $newPath = $prefix . $route->path;
                /** @var array<mixed>|callable|string $handler */
                $newRoute = $this->router()->add($method, $newPath, $handler);

                // Copy middleware from sub-app route
                foreach ($route->getMiddleware() as $middleware) {
                    $newRoute->use($middleware);
                }

                // Apply sub-app's global middleware
                foreach ($subApp->getMiddleware() as $middleware) {
                    $newRoute->use($middleware);
                }

                // Apply this app's global middleware
                foreach ($this->middleware as $middleware) {
                    $newRoute->use($middleware);
                }
            }
        }

        return $this;
    }

    // Lifecycle

    public function run(?Request $request = null): void
    {
        $this->boot();
        $request = $request ?? Request::capture();
        $response = $this->handle($request);
        $response->send();
    }

    public function handle(Request $request): Response
    {
        $this->boot();

        try {
            $this->container->instance(Request::class, $request);

            $match = $this->router()->match($request);

            if (!$match->matched) {
                $json = json_encode(['error' => 'Not Found']);
                return new Response(
                    $json !== false ? $json : '{"error": "Not Found"}',
                    404,
                    ['Content-Type' => 'application/json']
                );
            }

            $route = $match->route;
            // $match->route is guaranteed not null if matched is true based on Router logic,
            // but PHPStan doesn't track that dependency unless we assert.
            if ($route === null) {
                // Should be unreachable given !$match->matched check above
                throw new \RuntimeException('Route matched but route object is null');
            }
            $params = $match->params;

            // Build middleware stack
            $middlewareStack = $route->getMiddleware();

            // Create the final handler - always returns a Response
            $handler = fn (Request $req) => $this->prepareResponse(
                $this->executeHandler($route->handler, $match->params, $req)
            );

            // Wrap handler with middleware
            $pipeline = array_reduce(
                array_reverse($middlewareStack),
                fn ($next, $middleware) => fn (Request $req) => $this->executeMiddleware($middleware, $req, $next),
                $handler
            );

            $result = $pipeline($request);
            if (!$result instanceof Response) {
                // Should have been prepared by prepareResponse
                $content = is_scalar($result) || $result instanceof \Stringable ? (string) $result : '';
                return new Response($content, 200);
            }
            return $result;
        } finally {
            $this->container->forgetScopedInstances();
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function executeHandler(mixed $handler, array $params, Request $request): mixed
    {
        // Closure
        if ($handler instanceof Closure) {
            return $this->container->call($handler, $params);
        }

        // [Controller::class, 'method']
        if (is_array($handler)) {
            [$class, $method] = $handler;
            if (!is_string($class) || !is_string($method)) {
                throw new \RuntimeException('Invalid array handler: expected [class, method]');
            }
            $classStr = $class;
            $instance = $this->container->resolve($classStr);
            $methodStr = $method; // is_string check passed

            $callback = [$instance, $methodStr];
            if (!is_callable($callback)) {
                throw new \RuntimeException("Method {$methodStr} not callable on resolved instance");
            }
            return $this->container->call($callback, $params);
        }

        // Invokable class string
        if (is_string($handler) && class_exists($handler)) {
            $instance = $this->container->resolve($handler);
            if (!is_callable($instance)) {
                throw new \RuntimeException("Resolved handler {$handler} is not invokable");
            }
            return $this->container->call($instance, $params);
        }

        throw new \RuntimeException('Invalid route handler');
    }

    protected function executeMiddleware(callable|string|object $middleware, Request $request, callable $next): mixed
    {
        // Resolve class string through container
        if (is_string($middleware)) {
            $middleware = $this->container->resolve($middleware);
        }

        if (!is_callable($middleware)) {
            throw new \RuntimeException('Middleware must be callable');
        }

        return $middleware($request, $next);
    }

    protected function prepareResponse(mixed $result): Response
    {
        // Already a Response
        if ($result instanceof Response) {
            return $result;
        }

        // Null -> 204 No Content
        if ($result === null) {
            return new Response('', 204);
        }

        // Array -> JSON
        if (is_array($result)) {
            $json = json_encode($result);
            if ($json === false) {
                throw new \RuntimeException('Failed to encode response to JSON: ' . json_last_error_msg());
            }
            return new Response(
                $json,
                200,
                ['Content-Type' => 'application/json']
            );
        }

        // String -> text/plain
        if (is_string($result)) {
            return new Response($result, 200, ['Content-Type' => 'text/plain']);
        }

        // Stringable object
        // PHPStan strictly wants strict Stringable check before cast
        if ($result instanceof \Stringable || (is_object($result) && method_exists($result, '__toString'))) {
            return new Response((string) $result, 200, ['Content-Type' => 'text/plain']);
        }

        throw new \RuntimeException('Unable to convert handler result to response');
    }

    // Testing

    public function test(): TestClient
    {
        return new TestClient($this);
    }

    /**
     * Create an app with optional callback for configuration.
     */
    public static function create(?callable $callback = null): static
    {
        $app = new static();

        if ($callback !== null) {
            $callback($app);
        }

        return $app;
    }

    /**
     * @deprecated Use new App() or App::create() instead
     */
    public static function build(?callable $callback = null): static
    {
        $container = new Container();

        if ($callback !== null) {
            $callback($container);
        }

        return new static($container);
    }

    /**
     * @deprecated Use new App() instead
     */
    public static function buildDefaults(): static
    {
        return new static();
    }
}
