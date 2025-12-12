<?php

declare(strict_types=1);

namespace Verge\Cache;

class Cache
{
    public function __construct(
        protected CacheInterface $driver
    ) {}

    /**
     * Get an item from the cache.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->driver->get($key, $default);
    }

    /**
     * Store an item in the cache.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): static
    {
        $this->driver->set($key, $value, $ttl);
        return $this;
    }

    /**
     * Alias for set().
     */
    public function put(string $key, mixed $value, ?int $ttl = null): static
    {
        return $this->set($key, $value, $ttl);
    }

    /**
     * Get an item from cache, or store and return a default value.
     */
    public function remember(string $key, ?int $ttl, callable $callback): mixed
    {
        if ($this->driver->has($key)) {
            return $this->driver->get($key);
        }

        $value = $callback();
        $this->driver->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Get an item from cache, or store forever and return a default value.
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->remember($key, null, $callback);
    }

    /**
     * Store an item in the cache indefinitely.
     */
    public function forever(string $key, mixed $value): static
    {
        $this->driver->set($key, $value, null);
        return $this;
    }

    /**
     * Remove an item from the cache.
     */
    public function forget(string $key): static
    {
        $this->driver->delete($key);
        return $this;
    }

    /**
     * Remove all items from the cache.
     */
    public function flush(): static
    {
        $this->driver->clear();
        return $this;
    }

    /**
     * Determine if an item exists in the cache.
     */
    public function has(string $key): bool
    {
        return $this->driver->has($key);
    }

    /**
     * Determine if an item doesn't exist in the cache.
     */
    public function missing(string $key): bool
    {
        return !$this->has($key);
    }

    /**
     * Retrieve and delete an item from the cache.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    /**
     * Increment a cached value.
     */
    public function increment(string $key, int $value = 1): int
    {
        $current = (int) $this->get($key, 0);
        $new = $current + $value;
        $this->driver->set($key, $new);
        return $new;
    }

    /**
     * Decrement a cached value.
     */
    public function decrement(string $key, int $value = 1): int
    {
        return $this->increment($key, -$value);
    }

    /**
     * Get multiple items from the cache.
     *
     * @param iterable<string> $keys
     * @return array<string, mixed>
     */
    public function many(iterable $keys, mixed $default = null): array
    {
        return (array) $this->driver->getMultiple($keys, $default);
    }

    /**
     * Store multiple items in the cache.
     *
     * @param iterable<string, mixed> $values
     */
    public function setMany(iterable $values, ?int $ttl = null): static
    {
        $this->driver->setMultiple($values, $ttl);
        return $this;
    }

    /**
     * Get the underlying cache driver.
     */
    public function driver(): CacheInterface
    {
        return $this->driver;
    }
}
