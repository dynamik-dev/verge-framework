<?php

declare(strict_types=1);

namespace Verge\Log\Drivers;

use Verge\Log\LoggerInterface;
use Verge\Log\LogLevel;

class StreamLogDriver implements LoggerInterface
{
    /** @var resource */
    private $stream;

    private LogLevel $minLevel;

    /**
     * @param resource|string $stream File path or stream resource (defaults to stderr)
     */
    public function __construct(
        mixed $stream = 'php://stderr',
        LogLevel $minLevel = LogLevel::DEBUG
    ) {
        $this->minLevel = $minLevel;

        if (is_string($stream)) {
            $resource = fopen($stream, 'a');
            if ($resource === false) {
                throw new \RuntimeException("Unable to open log stream: {$stream}");
            }
            $this->stream = $resource;
        } else {
            $this->stream = $stream;
        }
    }

    /** @param array<string, mixed> $context */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function alert(string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function notice(string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function log(LogLevel $level, string $message, array $context = []): void
    {
        if ($level->priority() > $this->minLevel->priority()) {
            return;
        }

        $message = $this->interpolate($message, $context);
        $formatted = $this->format($level, $message, $context);

        fwrite($this->stream, $formatted . PHP_EOL);
    }

    /** @param array<string, mixed> $context */
    protected function format(LogLevel $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelName = strtoupper($level->value);

        return "[{$timestamp}] {$levelName}: {$message}";
    }

    /**
     * Interpolates context values into message placeholders.
     * @param array<string, mixed> $context
     */
    protected function interpolate(string $message, array $context): string
    {
        $replacements = [];

        foreach ($context as $key => $value) {
            if (is_string($value) || is_numeric($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replacements);
    }
}
