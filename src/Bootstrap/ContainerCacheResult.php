<?php

declare(strict_types=1);

namespace Verge\Bootstrap;

/**
 * Result of a container cache warming operation.
 */
class ContainerCacheResult
{
    /**
     * @param int $cached Number of classes successfully cached
     * @param array $failed Classes that failed to cache with reasons
     */
    public function __construct(
        public readonly int $cached,
        public readonly array $failed
    ) {}

    /**
     * Check if any classes failed to cache.
     */
    public function hasFailed(): bool
    {
        return count($this->failed) > 0;
    }

    /**
     * Get formatted warnings for failed classes.
     */
    public function getWarnings(): array
    {
        $warnings = [];
        foreach ($this->failed as $item) {
            $warnings[] = "{$item['class']}: {$item['reason']}";
        }
        return $warnings;
    }
}
