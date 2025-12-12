<?php

declare(strict_types=1);

namespace Verge\Bootstrap;

/**
 * Result of a route cache warming operation.
 */
class RouteCacheResult
{
    /**
     * @param int $cached Number of routes successfully cached
     * @param array $skipped Routes that were skipped with reasons
     * @param array $handlers Handler class names for container caching
     */
    public function __construct(
        public readonly int $cached,
        public readonly array $skipped,
        public readonly array $handlers
    ) {}

    /**
     * Check if any routes were skipped.
     */
    public function hasSkipped(): bool
    {
        return count($this->skipped) > 0;
    }

    /**
     * Get formatted warnings for skipped routes.
     */
    public function getWarnings(): array
    {
        $warnings = [];
        foreach ($this->skipped as $route) {
            $warnings[] = "{$route['method']} {$route['path']}: {$route['reason']}";
        }
        return $warnings;
    }
}
