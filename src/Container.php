<?php

declare(strict_types=1);

namespace Verge;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

class Container implements ContainerInterface
{
    /** @var array<string, Closure> */
    protected array $bindings = [];

    /** @var array<string, mixed> */
    protected array $instances = [];

    /** @var array<string, bool> */
    protected array $singletons = [];

    /** @var array<string, bool> */
    protected array $scopedBindings = [];

    /** @var array<string, array>|null Cached reflection metadata */
    protected ?array $reflectionCache = null;

    public function defaults(): static
    {
        if (!$this->has(Routing\RouterInterface::class)) {
            $this->singleton(Routing\RouterInterface::class, fn() => new Routing\Router());
        }
        if (!$this->has(Env::class)) {
            $this->singleton(Env::class, fn() => new Env());
        }
        if (!$this->has(Events\EventDispatcher::class)) {
            $this->singleton(Events\EventDispatcher::class, fn($c) => new Events\EventDispatcher($c));
        }
        // Note: CacheInterface and LoggerInterface are now wired via App::driver()
        // These fallbacks exist only for backwards compatibility when container is used standalone
        if (!$this->has(Cache\CacheInterface::class)) {
            $this->singleton(Cache\CacheInterface::class, fn() => new Cache\Drivers\MemoryCacheDriver());
        }
        if (!$this->has(Log\LoggerInterface::class)) {
            $this->singleton(Log\LoggerInterface::class, fn() => new Log\Drivers\StreamLogDriver());
        }
        return $this;
    }

    public function bind(string $abstract, Closure|string $concrete): static
    {
        if (is_string($concrete)) {
            $concrete = fn() => $this->resolve($concrete);
        }

        $this->bindings[$abstract] = $concrete;
        return $this;
    }

    public function singleton(string $abstract, Closure|string $concrete): static
    {
        $this->bind($abstract, $concrete);
        $this->singletons[$abstract] = true;
        return $this;
    }

    public function scoped(string $abstract, Closure|string $concrete): static
    {
        $this->bind($abstract, $concrete);
        $this->singletons[$abstract] = true;
        $this->scopedBindings[$abstract] = true;
        return $this;
    }

    public function get(string $id): mixed
    {
        return $this->resolve($id);
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]) || class_exists($id);
    }

    public function resolve(string $abstract, array $parameters = []): mixed
    {
        // If parameters are passed, always build fresh (don't use cached instance)
        if (!empty($parameters)) {
            return $this->build($abstract, $parameters);
        }

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract]($this);

            if (isset($this->singletons[$abstract])) {
                $this->instances[$abstract] = $concrete;
            }

            return $concrete;
        }

        return $this->build($abstract);
    }

    public function build(string $class, array $parameters = []): object
    {
        if (!class_exists($class)) {
            throw new ContainerException("Class {$class} does not exist");
        }

        // Try to use cached reflection data
        if ($this->reflectionCache !== null && isset($this->reflectionCache[$class])) {
            return $this->buildFromCache($class, $parameters);
        }

        $reflector = new ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
            throw new ContainerException("Class {$class} is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Build a class using cached reflection data.
     */
    protected function buildFromCache(string $class, array $parameters = []): object
    {
        $cached = $this->reflectionCache[$class];

        if (!$cached['instantiable']) {
            throw new ContainerException("Class {$class} is not instantiable");
        }

        if ($cached['constructor'] === null) {
            return new $class();
        }

        $dependencies = $this->resolveDependenciesFromCache($cached['constructor'], $parameters);

        return new $class(...$dependencies);
    }

    /**
     * Resolve dependencies using cached parameter metadata.
     */
    protected function resolveDependenciesFromCache(array $cachedParams, array $parameters = []): array
    {
        $dependencies = [];

        foreach ($cachedParams as $param) {
            $name = $param['name'];

            // Check if parameter was explicitly provided
            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            // Check if we can resolve by type
            if ($param['type'] !== null && !$param['builtin']) {
                $dependencies[] = $this->resolve($param['type']);
            } elseif ($param['hasDefault']) {
                $dependencies[] = $param['default'];
            } else {
                throw new ContainerException(
                    "Cannot resolve parameter \${$name}"
                );
            }
        }

        return $dependencies;
    }

    /**
     * Set the reflection cache for faster dependency resolution.
     */
    public function setReflectionCache(array $cache): static
    {
        $this->reflectionCache = $cache;
        return $this;
    }

    /**
     * Check if reflection cache is loaded.
     */
    public function hasReflectionCache(): bool
    {
        return $this->reflectionCache !== null;
    }

    /**
     * @param ReflectionParameter[] $reflectionParams
     * @param array<string, mixed> $parameters
     * @return array<mixed>
     */
    public function resolveDependencies(array $reflectionParams, array $parameters = []): array
    {
        $dependencies = [];

        foreach ($reflectionParams as $param) {
            $name = $param->getName();

            // Check if parameter was explicitly provided
            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->resolve($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
            } else {
                throw new ContainerException(
                    "Cannot resolve parameter \${$name}"
                );
            }
        }

        return $dependencies;
    }

    public function call(callable $callback, array $parameters = []): mixed
    {
        if (is_array($callback)) {
            $reflection = new ReflectionMethod($callback[0], $callback[1]);
        } elseif ($callback instanceof Closure) {
            $reflection = new ReflectionFunction($callback);
        } elseif (is_string($callback) && function_exists($callback)) {
            $reflection = new ReflectionFunction($callback);
        } elseif (is_object($callback) && method_exists($callback, '__invoke')) {
            $reflection = new ReflectionMethod($callback, '__invoke');
        } else {
            throw new ContainerException('Cannot resolve callable');
        }

        $args = $this->resolveCallableParameters($reflection->getParameters(), $parameters);

        return $callback(...$args);
    }

    /**
     * @param ReflectionParameter[] $reflectionParams
     * @param array<string, mixed> $providedParams
     * @return array<mixed>
     */
    protected function resolveCallableParameters(array $reflectionParams, array $providedParams): array
    {
        $args = [];

        foreach ($reflectionParams as $param) {
            $name = $param->getName();
            $type = $param->getType();

            if (array_key_exists($name, $providedParams)) {
                $args[] = $providedParams[$name];
            } elseif ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->resolve($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new ContainerException(
                    "Cannot resolve parameter \${$name}"
                );
            }
        }

        return $args;
    }

    public function instance(string $abstract, mixed $instance): static
    {
        $this->instances[$abstract] = $instance;
        return $this;
    }

    public function forgetScopedInstances(): void
    {
        foreach ($this->scopedBindings as $abstract => $_) {
            unset($this->instances[$abstract]);
        }
    }
}
