---
title: Cache Methods
description: Complete reference for Verge cache API.
---

## Basic Operations

| Method | Description |
|--------|-------------|
| `$cache->get($key, $default)` | Retrieve value or return default |
| `$cache->set($key, $value, $ttl?)` | Store value with optional TTL (seconds) |
| `$cache->put($key, $value, $ttl?)` | Alias for `set()` |
| `$cache->forever($key, $value)` | Store value indefinitely |
| `$cache->has($key)` | Check if key exists |
| `$cache->missing($key)` | Check if key doesn't exist |
| `$cache->forget($key)` | Delete key |
| `$cache->flush()` | Clear all cache |

## Advanced Operations

| Method | Description |
|--------|-------------|
| `$cache->remember($key, $ttl, $callback)` | Get or store via callback |
| `$cache->rememberForever($key, $callback)` | Get or store permanently |
| `$cache->pull($key, $default)` | Get and delete in one operation |
| `$cache->increment($key, $value)` | Increment numeric value |
| `$cache->decrement($key, $value)` | Decrement numeric value |

## Batch Operations

| Method | Description |
|--------|-------------|
| `$cache->many($keys, $default)` | Retrieve multiple keys |
| `$cache->setMany($values, $ttl?)` | Store multiple key-value pairs |

## Examples

### Store and Retrieve

```php
$cache->set('user:123', $user, 3600);  // Store for 1 hour
$user = $cache->get('user:123');       // Retrieve
```

### Remember Pattern

```php
$users = $cache->remember('all-users', 600, fn() => fetchUsers());
```

### Pull Pattern

```php
$token = $cache->pull('reset-token:123');  // Get and delete
```

### Counters

```php
$cache->set('views', 0);
$cache->increment('views');        // 1
$cache->increment('views', 5);     // 6
$cache->decrement('views', 2);     // 4
```

### Batch Operations

```php
$values = $cache->many(['key1', 'key2', 'key3'], 'default');

$cache->setMany([
    'key1' => 'value1',
    'key2' => 'value2',
], 300);
```
