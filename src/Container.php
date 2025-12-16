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

    /** @var array<string, array<string, Closure>> */
    protected array $contextualBindings = [];

    /** @var array{abstract: string, concrete: Closure}|null */
    protected ?array $pendingBinding = null;

    /** @var string[] */
    protected array $buildStack = [];

    /** @var array<string, array{instantiable: bool, constructor: ?array<int, array{name: string, type: ?string, builtin: bool, hasDefault: bool, default: mixed}>}>|null */
    protected ?array $reflectionCache = null;

    public function bind(string $abstract, Closure|string $concrete): static
    {
        if (is_string($concrete)) {
            $concrete = fn () => $this->resolve($concrete);
        }

        $this->bindings[$abstract] = $concrete;
        $this->pendingBinding = ['abstract' => $abstract, 'concrete' => $concrete];

        return $this;
    }

    /**
     * Make the preceding binding contextual for specific classes.
     *
     * @param string|string[] $contexts
     */
    public function for(string|array $contexts): static
    {
        if ($this->pendingBinding === null) {
            throw new ContainerException('Cannot call for() without a preceding bind(), singleton(), or instance() call');
        }

        $abstract = $this->pendingBinding['abstract'];
        $concrete = $this->pendingBinding['concrete'];

        $contexts = is_array($contexts) ? $contexts : [$contexts];

        foreach ($contexts as $context) {
            $this->contextualBindings[$abstract][$context] = $concrete;
        }

        // Remove from default bindings - this binding is contextual only
        unset($this->bindings[$abstract]);
        unset($this->singletons[$abstract]);

        $this->pendingBinding = null;

        return $this;
    }

    /**
     * Get a contextual binding if one exists for the current build context.
     */
    protected function getContextualConcrete(string $abstract): ?Closure
    {
        if (empty($this->buildStack) || !isset($this->contextualBindings[$abstract])) {
            return null;
        }

        $context = end($this->buildStack);

        return $this->contextualBindings[$abstract][$context] ?? null;
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

    /**
     * @param array<string, mixed> $parameters
     */
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

    /**
     * @param array<string, mixed> $parameters
     */
    public function build(string $class, array $parameters = []): object
    {
        if (!class_exists($class)) {
            throw new ContainerException("Class {$class} does not exist");
        }

        $this->buildStack[] = $class;

        try {
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
        } finally {
            array_pop($this->buildStack);
        }
    }

    /**
     * Build a class using cached reflection data.
     *
     * @param array<string, mixed> $parameters
     */
    protected function buildFromCache(string $class, array $parameters = []): object
    {
        if ($this->reflectionCache === null || !isset($this->reflectionCache[$class])) {
            throw new ContainerException("Class {$class} not found in reflection cache");
        }
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
     *
     * @param array<int, array{name: string, type: ?string, builtin: bool, hasDefault: bool, default: mixed}> $cachedParams
     * @param array<string, mixed> $parameters
     * @return array<mixed>
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
                // Check for contextual binding first
                $contextual = $this->getContextualConcrete($param['type']);
                if ($contextual !== null) {
                    $dependencies[] = $contextual($this);
                } else {
                    $dependencies[] = $this->resolve($param['type']);
                }
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
     * @param array<string, array{instantiable: bool, constructor: ?array<int, array{name: string, type: ?string, builtin: bool, hasDefault: bool, default: mixed}>}> $cache
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
                $typeName = $type->getName();

                // Check for contextual binding first
                $contextual = $this->getContextualConcrete($typeName);
                if ($contextual !== null) {
                    $dependencies[] = $contextual($this);
                } else {
                    $dependencies[] = $this->resolve($typeName);
                }
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

    /**
     * @param array<string, mixed> $parameters
     */
    public function call(callable $callback, array $parameters = []): mixed
    {
        if (is_array($callback)) {
            /** @var object|string $class */
            $class = $callback[0];
            /** @var string $method */
            $method = $callback[1];
            $reflection = new ReflectionMethod($class, $method);
        } elseif ($callback instanceof Closure) {
            $reflection = new ReflectionFunction($callback);
        } elseif (is_string($callback)) {
            $reflection = new ReflectionFunction($callback);
        } elseif (is_object($callback) && method_exists($callback, '__invoke')) {
            $reflection = new ReflectionMethod($callback, '__invoke');
        } else {
            throw new ContainerException('Cannot resolve callable');
        }

        $args = $this->resolveDependencies($reflection->getParameters(), $parameters);

        return $callback(...$args);
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
