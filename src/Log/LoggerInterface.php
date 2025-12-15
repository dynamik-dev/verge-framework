<?php

declare(strict_types=1);

namespace Verge\Log;

/**
 * PSR-3 compatible logger interface.
 */
interface LoggerInterface
{
    /**
     * System is unusable.
     * @param array<string, mixed> $context
     */
    public function emergency(string $message, array $context = []): void;

    /**
     * Action must be taken immediately.
     * @param array<string, mixed> $context
     */
    public function alert(string $message, array $context = []): void;

    /**
     * Critical conditions.
     * @param array<string, mixed> $context
     */
    public function critical(string $message, array $context = []): void;

    /**
     * Runtime errors that do not require immediate action.
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void;

    /**
     * Exceptional occurrences that are not errors.
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Normal but significant events.
     * @param array<string, mixed> $context
     */
    public function notice(string $message, array $context = []): void;

    /**
     * Interesting events.
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void;

    /**
     * Detailed debug information.
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void;

    /**
     * Logs with an arbitrary level.
     * @param array<string, mixed> $context
     */
    public function log(LogLevel $level, string $message, array $context = []): void;
}
