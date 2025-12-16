<?php

declare(strict_types=1);

namespace Verge\Env;

interface EnvInterface
{
    /**
     * Get an environment variable value.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Check if an environment variable exists.
     */
    public function has(string $key): bool;

    /**
     * Set an environment variable.
     */
    public function set(string $key, string $value): void;
}
