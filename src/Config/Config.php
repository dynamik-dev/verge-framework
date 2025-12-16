<?php

declare(strict_types=1);

namespace Verge\Config;

class Config
{
    /** @var array<string, mixed> */
    protected array $items = [];

    /**
     * Get a config value using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getNestedValue($this->items, $key, $default);
    }

    /**
     * Set config values.
     *
     * @param array<string, mixed> $values
     */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->setNestedValue($this->items, $key, $value);
        }
    }

    /**
     * Check if a config key exists.
     */
    public function has(string $key): bool
    {
        return $this->getNestedValue($this->items, $key, $this) !== $this;
    }

    /**
     * Get all config items.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Load config from a file and namespace it.
     */
    public function load(string $path, ?string $namespace = null): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Config file not found: {$path}");
        }

        $config = require $path;

        if (!is_array($config)) {
            throw new \RuntimeException("Config file must return an array: {$path}");
        }

        // Use filename (without extension) as namespace if not provided
        if ($namespace === null) {
            $namespace = pathinfo($path, PATHINFO_FILENAME);
        }

        $this->set([$namespace => $config]);
    }

    /**
     * Get a nested value using dot notation.
     *
     * @param array<string, mixed> $array
     */
    protected function getNestedValue(array $array, string $key, mixed $default): mixed
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        $segments = explode('.', $key);
        $current = $array;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Set a nested value using dot notation.
     *
     * @param array<string, mixed> $array
     */
    protected function setNestedValue(array &$array, string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $current = &$array;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }
}
