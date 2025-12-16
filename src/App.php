<?php

declare(strict_types=1);

namespace Verge;

use Closure;
use Verge\Cache;
use Verge\Log;
use Verge\Concerns\HasMiddleware;
use Verge\Env\EnvInterface;
use Verge\Events\EventDispatcherInterface;
use Verge\Http\Request;
use Verge\Http\RequestHandlerInterface;
use Verge\Http\Response;
use Verge\Routing\Route;
use Verge\Routing\Router;
use Verge\Routing\RouterInterface;
use Verge\Routing\RouteMatcherInterface;
use Verge\Routing\RouteGroup;
use Verge\Routing\Explorer\RouteExplorer;
use Verge\Testing\TestClient;
use Psr\Container\ContainerInterface;

class App
{
    use HasMiddleware;

    public Container $container;
    protected RouterInterface|RouteMatcherInterface|null $router = null;
    protected ?EnvInterface $env = null;
    protected ?EventDispatcherInterface $events = null;
    protected ?Config\Config $config = null;
    protected string $currentPrefix = '';
    protected ?RouteGroup $currentGroup = null;
    protected bool $booted = false;
    protected ?string $basePath = null;

    /** @var array<string, array<string, \Closure>> */
    protected array $drivers = [];

    /** @var array<string, string> Default driver names per service */
    protected array $defaultDrivers = [];

    public function __construct(?ContainerInterface $container = null)
    {
        if ($container === null) {
            $this->container = new Container();
        } elseif ($container instanceof Container) {
            $this->container = $container;
        } else {
            throw new \InvalidArgumentException('App requires instance of Verge\Container');
        }

        // Make App resolvable from container
        $this->container->instance(App::class, $this);
        $this->container->instance(static::class, $this);

        // Bootstrap framework via service providers
        $this->configure(new AppBuilder());
    }

    protected function router(): RouterInterface
    {
        if ($this->router instanceof RouterInterface) {
            return $this->router;
        }
        $router = $this->container->resolve(RouterInterface::class);
        if (!$router instanceof RouterInterface) {
            throw new \RuntimeException('Resolved service is not a RouterInterface');
        }
        return $this->router = $router;
    }

    protected function getEnv(): EnvInterface
    {
        if ($this->env !== null) {
            return $this->env;
        }
        $env = $this->container->resolve(EnvInterface::class);
        if (!$env instanceof EnvInterface) {
            throw new \RuntimeException('Resolved service is not an EnvInterface');
        }
        return $this->env = $env;
    }

    protected function events(): EventDispatcherInterface
    {
        if ($this->events !== null) {
            return $this->events;
        }
        $events = $this->container->resolve(EventDispatcherInterface::class);
        if (!$events instanceof EventDispatcherInterface) {
            throw new \RuntimeException('Resolved service is not an EventDispatcherInterface');
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

    /**
     * Register a callback to run after all modules are loaded.
     *
     * @param callable(): void $callback
     */
    public function ready(callable $callback): static
    {
        return $this->on('app.ready', $callback);
    }

    // Routing methods (forwarded to router)

    /**
     * @param array<int, string> $middleware
     * @param callable|array<mixed>|string $handler
     */
    public function get(string $path, callable|array|string $handler, array $middleware = [], ?string $name = null): Route
    {
        return $this->addRoute('GET', $path, $handler, $middleware, $name);
    }

    /**
     * @param array<int, string> $middleware
     * @param callable|array<mixed>|string $handler
     */
    public function post(string $path, callable|array|string $handler, array $middleware = [], ?string $name = null): Route
    {
        return $this->addRoute('POST', $path, $handler, $middleware, $name);
    }

    /**
     * @param array<int, string> $middleware
     * @param callable|array<mixed>|string $handler
     */
    public function put(string $path, callable|array|string $handler, array $middleware = [], ?string $name = null): Route
    {
        return $this->addRoute('PUT', $path, $handler, $middleware, $name);
    }

    /**
     * @param array<int, string> $middleware
     * @param callable|array<mixed>|string $handler
     */
    public function patch(string $path, callable|array|string $handler, array $middleware = [], ?string $name = null): Route
    {
        return $this->addRoute('PATCH', $path, $handler, $middleware, $name);
    }

    /**
     * @param array<int, string> $middleware
     * @param callable|array<mixed>|string $handler
     */
    public function delete(string $path, callable|array|string $handler, array $middleware = [], ?string $name = null): Route
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware, $name);
    }

    /**
     * @param array<int, string> $middleware
     * @param callable|array<mixed>|string $handler
     */
    public function any(string $path, callable|array|string $handler, array $middleware = [], ?string $name = null): Route
    {
        return $this->addRoute('ANY', $path, $handler, $middleware, $name);
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
     * Make the preceding binding contextual for specific classes.
     *
     * @param string|string[] $contexts
     */
    public function for(string|array $contexts): static
    {
        $this->container->for($contexts);
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
     * Set the base path for the application.
     */
    public function setBasePath(string $path): static
    {
        $this->basePath = rtrim($path, '/\\');
        return $this;
    }

    /**
     * Get the base path, optionally appending a sub-path.
     */
    public function basePath(string $path = ''): string
    {
        if ($this->basePath === null) {
            throw new \RuntimeException('Base path not set. Call setBasePath() first.');
        }

        if ($path === '') {
            return $this->basePath;
        }

        return $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    /**
     * Get the config instance.
     */
    protected function getConfig(): Config\Config
    {
        if ($this->config !== null) {
            return $this->config;
        }

        $config = $this->container->resolve(Config\Config::class);
        if (!$config instanceof Config\Config) {
            throw new \RuntimeException('Resolved service is not a Config instance');
        }

        return $this->config = $config;
    }

    /**
     * Get or set config values.
     *
     * @param string|array<string, mixed>|null $key
     */
    public function config(string|array|null $key = null, mixed $default = null): mixed
    {
        $config = $this->getConfig();

        // Get all config
        if ($key === null) {
            return $config->all();
        }

        // Set config values
        if (is_array($key)) {
            $config->set($key);
            return null;
        }

        // Get config value
        return $config->get($key, $default);
    }

    /**
     * Load config from a file.
     */
    public function loadConfig(string $path, ?string $namespace = null): static
    {
        $this->getConfig()->load($path, $namespace);
        return $this;
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
            throw new \RuntimeException('Module must be callable');
        }
        $provider($this);
        return $this;
    }

    /**
     * Register a module.
     *
     * @param class-string $module
     */
    public function module(string $module): static
    {
        return $this->configure($module);
    }

    /**
     * Get route introspection or set a router.
     *
     * @param RouteMatcherInterface|null $router
     * @return ($router is null ? RouteExplorer : static)
     */
    public function routes(?RouteMatcherInterface $router = null): RouteExplorer|static
    {
        if ($router === null) {
            return new RouteExplorer($this->router());
        }

        // Merge routes from provided router (used by BootstrapCache)
        foreach ($router->getRoutes() as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {
                foreach ($this->middleware as $middleware) {
                    $route->use($middleware);
                }
            }
        }
        $this->router = $router;

        // Update container binding so RequestHandler gets the new router
        $this->container->instance(RouteMatcherInterface::class, $router);

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
     * Register a controller using attribute-based routing.
     *
     * @param class-string|object $controller
     */
    public function controller(string|object $controller): static
    {
        $loader = new Routing\RouteLoader($this->router());
        $loader->registerController($controller);
        return $this;
    }

    /**
     * Register multiple controllers using attribute-based routing.
     *
     * @param array<class-string|object> $controllers
     */
    public function controllers(array $controllers): static
    {
        $loader = new Routing\RouteLoader($this->router());
        $loader->registerControllers($controllers);
        return $this;
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

        $handler = $this->container->resolve(RequestHandlerInterface::class);
        if (!$handler instanceof RequestHandlerInterface) {
            throw new \RuntimeException('Resolved service is not a RequestHandlerInterface');
        }

        return $handler->handle($request);
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
}
