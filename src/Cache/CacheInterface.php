<?php

declare(strict_types=1);

namespace Verge\Cache;

/**
 * PSR-16 compatible cache interface.
 */
interface CacheInterface
{
    /**
     * Fetches a value from the cache.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Persists data in the cache.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Delete an item from the cache.
     */
    public function delete(string $key): bool;

    /**
     * Wipes clean the entire cache.
     */
    public function clear(): bool;

    /**
     * Determines whether an item is present in the cache.
     */
    public function has(string $key): bool;

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable<string> $keys
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable;

    /**
     * Persists a set of key => value pairs in the cache.
     *
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, ?int $ttl = null): bool;

    /**
     * Deletes multiple cache items.
     *
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool;
}
