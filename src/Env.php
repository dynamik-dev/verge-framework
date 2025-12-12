<?php

declare(strict_types=1);

namespace Verge;

class Env
{
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === '') {
            return $default;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }

    public function has(string $key): bool
    {
        return isset($_ENV[$key]) || getenv($key) !== false;
    }

    public function set(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}
