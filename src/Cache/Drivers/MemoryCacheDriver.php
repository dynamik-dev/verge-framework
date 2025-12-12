<?php

declare(strict_types=1);

namespace Verge\Cache\Drivers;

use Verge\Cache\CacheInterface;

class MemoryCacheDriver implements CacheInterface
{
    /** @var array<string, array{value: mixed, expires: ?int}> */
    private array $cache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->cache[$key]['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->cache[$key] = [
            'value' => $value,
            'expires' => $ttl !== null ? time() + $ttl : null,
        ];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->cache[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];
        return true;
    }

    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        $expires = $this->cache[$key]['expires'];

        if ($expires !== null && $expires < time()) {
            unset($this->cache[$key]);
            return false;
        }

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }
}
