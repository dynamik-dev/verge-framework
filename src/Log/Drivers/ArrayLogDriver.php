<?php

declare(strict_types=1);

namespace Verge\Log\Drivers;

use Verge\Log\LoggerInterface;
use Verge\Log\LogLevel;

/**
 * In-memory logger for testing.
 */
class ArrayLogDriver implements LoggerInterface
{
    /** @var array<int, array{level: LogLevel, message: string, context: array}> */
    private array $logs = [];

    public function emergency(string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log(LogLevel $level, string $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    /**
     * Get all logged entries.
     *
     * @return array<int, array{level: LogLevel, message: string, context: array}>
     */
    public function all(): array
    {
        return $this->logs;
    }

    /**
     * Get logs filtered by level.
     *
     * @return array<int, array{level: LogLevel, message: string, context: array}>
     */
    public function level(LogLevel $level): array
    {
        return array_values(array_filter(
            $this->logs,
            fn($log) => $log['level'] === $level
        ));
    }

    /**
     * Check if a message was logged.
     */
    public function hasLogged(string $message, ?LogLevel $level = null): bool
    {
        foreach ($this->logs as $log) {
            if (str_contains($log['message'], $message)) {
                if ($level === null || $log['level'] === $level) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get the count of logged entries.
     */
    public function count(): int
    {
        return count($this->logs);
    }

    /**
     * Clear all logs.
     */
    public function clear(): void
    {
        $this->logs = [];
    }
}
