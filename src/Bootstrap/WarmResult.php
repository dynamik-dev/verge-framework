<?php

declare(strict_types=1);

namespace Verge\Bootstrap;

/**
 * Result of a full cache warming operation.
 */
class WarmResult
{
    public function __construct(
        public readonly RouteCacheResult $routes,
        public readonly ContainerCacheResult $container
    ) {}

    /**
     * Get a summary of the warming operation.
     */
    public function summary(): string
    {
        $lines = [
            "Routes cached: {$this->routes->cached}",
            "Classes cached: {$this->container->cached}",
        ];

        if ($this->routes->hasSkipped()) {
            $lines[] = "Routes skipped: " . count($this->routes->skipped);
        }

        return implode("\n", $lines);
    }

    /**
     * Get all warnings from the warming operation.
     * @return array<int, string>
     */
    public function getWarnings(): array
    {
        return array_merge(
            $this->routes->getWarnings(),
            $this->container->getWarnings()
        );
    }

    /**
     * Check if there are any warnings.
     */
    public function hasWarnings(): bool
    {
        return count($this->getWarnings()) > 0;
    }
}
