<?php

declare(strict_types=1);

namespace Verge\Bundler;

/**
 * Result of a build operation.
 */
class BuildResult
{
    /**
     * @param int $closuresConverted Number of closures converted to handlers
     * @param int $filesGenerated Number of handler files generated
     * @param int $filesCopied Number of files copied to dist
     * @param array<string, string> $handlers Map of route path => handler class
     * @param array<array{route: string, reason: string}> $skipped Routes skipped with reasons
     * @param array<string> $warnings Build warnings
     * @param string $outputPath Path to the dist folder
     * @param float $duration Build duration in seconds
     */
    public function __construct(
        public readonly int $closuresConverted,
        public readonly int $filesGenerated,
        public readonly int $filesCopied,
        public readonly array $handlers,
        public readonly array $skipped,
        public readonly array $warnings,
        public readonly string $outputPath,
        public readonly float $duration,
    ) {}

    /**
     * Get a human-readable summary.
     */
    public function summary(): string
    {
        $lines = [
            "Build completed in {$this->formatDuration()}",
            "",
            "Closures converted: {$this->closuresConverted}",
            "Handler files generated: {$this->filesGenerated}",
            "Files copied: {$this->filesCopied}",
            "Output: {$this->outputPath}",
        ];

        if (count($this->skipped) > 0) {
            $lines[] = "";
            $lines[] = "Skipped (" . count($this->skipped) . "):";
            foreach ($this->skipped as $item) {
                $lines[] = "  - {$item['route']}: {$item['reason']}";
            }
        }

        if (count($this->warnings) > 0) {
            $lines[] = "";
            $lines[] = "Warnings:";
            foreach ($this->warnings as $warning) {
                $lines[] = "  - {$warning}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Check if the build was successful (no errors).
     */
    public function isSuccessful(): bool
    {
        return count($this->warnings) === 0;
    }

    /**
     * Check if any routes were skipped.
     */
    public function hasSkipped(): bool
    {
        return count($this->skipped) > 0;
    }

    /**
     * Format the duration.
     */
    private function formatDuration(): string
    {
        if ($this->duration < 1) {
            return round($this->duration * 1000) . 'ms';
        }

        return round($this->duration, 2) . 's';
    }
}
