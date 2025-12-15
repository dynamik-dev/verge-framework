<?php

declare(strict_types=1);

namespace Verge\Bootstrap;

use ReflectionClass;
use ReflectionNamedType;

/**
 * Caches reflection metadata for container dependency resolution.
 */
class ContainerCache
{
    public function __construct(
        private string $cachePath
    ) {}

    /**
     * Warm the container cache with reflection data for given classes.
     *
     * @param array<int, string> $classes Class names to cache
     */
    public function warm(array $classes): ContainerCacheResult
    {
        $cached = [];
        $failed = [];

        // Recursively discover dependencies
        $allClasses = $this->discoverDependencies($classes);

        foreach ($allClasses as $class) {
            try {
                $cached[$class] = $this->reflectClass($class);
            } catch (\ReflectionException $e) {
                $failed[] = [
                    'class' => $class,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        $this->writeCacheFile($cached);

        return new ContainerCacheResult(
            cached: count($cached),
            failed: $failed
        );
    }

    /**
     * Load cached reflection data.
     * @return array<string, array{instantiable: bool, constructor: ?array<int, array{name: string, type: ?string, builtin: bool, hasDefault: bool, default: mixed}>}>
     */
    public function load(): array
    {
        if (!$this->isCached()) {
            throw new \RuntimeException("Container cache not found at: {$this->cachePath}");
        }

        /** @var array<string, array{instantiable: bool, constructor: ?array<int, array{name: string, type: ?string, builtin: bool, hasDefault: bool, default: mixed}>}> */
        $data = require $this->cachePath;
        return $data;
    }

    /**
     * Check if the container cache exists.
     */
    public function isCached(): bool
    {
        return file_exists($this->cachePath);
    }

    /**
     * Clear the container cache.
     */
    public function clear(): bool
    {
        if (file_exists($this->cachePath)) {
            return unlink($this->cachePath);
        }
        return true;
    }

    /**
     * Get the cache file path.
     */
    public function getPath(): string
    {
        return $this->cachePath;
    }

    /**
     * Discover all dependencies recursively.
     * @param array<int, string> $classes
     * @param array<string, bool> $discovered
     * @return array<int, string>
     */
    private function discoverDependencies(array $classes, array &$discovered = []): array
    {
        foreach ($classes as $class) {
            if (isset($discovered[$class])) {
                continue;
            }

            if (!class_exists($class)) {
                continue;
            }

            $discovered[$class] = true;

            try {
                $reflector = new ReflectionClass($class);
                $constructor = $reflector->getConstructor();

                if ($constructor !== null) {
                    foreach ($constructor->getParameters() as $param) {
                        $type = $param->getType();
                        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                            $typeName = $type->getName();
                            if (class_exists($typeName) && !isset($discovered[$typeName])) {
                                $this->discoverDependencies([$typeName], $discovered);
                            }
                        }
                    }
                }
            } catch (\ReflectionException $e) {
                // Skip classes that can't be reflected
            }
        }

        return array_keys($discovered);
    }

    /**
     * Reflect a class and extract cacheable metadata.
     * @param string $class
     * @return array{instantiable: bool, constructor: ?array<int, array{name: string, type: string|null, builtin: bool, hasDefault: bool, default: mixed}>}
     */
    private function reflectClass(string $class): array
    {
        if (!class_exists($class)) {
             throw new \ReflectionException("Class {$class} does not exist");
        }
        $reflector = new ReflectionClass($class);

        $data = [
            'instantiable' => $reflector->isInstantiable(),
            'constructor' => null,
        ];

        $constructor = $reflector->getConstructor();
        if ($constructor !== null) {
            $data['constructor'] = [];

            foreach ($constructor->getParameters() as $param) {
                $paramData = [
                    'name' => $param->getName(),
                    'type' => null,
                    'builtin' => true,
                    'optional' => $param->isOptional(),
                    'default' => null,
                    'hasDefault' => $param->isDefaultValueAvailable(),
                ];

                $type = $param->getType();
                if ($type instanceof ReflectionNamedType) {
                    $paramData['type'] = $type->getName();
                    $paramData['builtin'] = $type->isBuiltin();
                }

                if ($param->isDefaultValueAvailable()) {
                    try {
                        $default = $param->getDefaultValue();
                        // Only cache serializable defaults
                        if ($this->isSerializable($default)) {
                            $paramData['default'] = $default;
                        }
                    } catch (\ReflectionException $e) {
                        // Can't get default value (e.g., for internal classes)
                    }
                }

                $data['constructor'][] = $paramData;
            }
        }

        /** @var array{instantiable: bool, constructor: ?array<int, array{name: string, type: string|null, builtin: bool, hasDefault: bool, default: mixed}>} $data */
        return $data;
    }

    /**
     * Check if a value is safely serializable to PHP code.
     */
    private function isSerializable(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_scalar($value)) {
            return true;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (!$this->isSerializable($item)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Write the cache file.
     * @param array<string, mixed> $cacheData
     */
    private function writeCacheFile(array $cacheData): void
    {
        $dir = dirname($this->cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = "<?php\n\n";
        $content .= "// Generated by Verge BootstrapCache\n";
        $content .= "// Generated: " . date('Y-m-d H:i:s') . "\n";
        $content .= "// Classes: " . count($cacheData) . "\n";
        $content .= "// DO NOT EDIT - This file is auto-generated\n\n";
        $content .= "return " . var_export($cacheData, true) . ";\n";

        file_put_contents($this->cachePath, $content);

        // Clear OPcache for this file if available
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->cachePath, true);
        }
    }
}
