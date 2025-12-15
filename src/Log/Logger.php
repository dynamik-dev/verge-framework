<?php

declare(strict_types=1);

namespace Verge\Log;

class Logger
{
    /** @var array<string, mixed> */
    protected array $context = [];

    public function __construct(
        protected LoggerInterface $driver
    ) {
    }

    /**
     * Add default context to all log entries.
     * @param array<string, mixed> $context
     */
    public function withContext(array $context): static
    {
        $new = clone $this;
        $new->context = array_merge($this->context, $context);
        return $new;
    }

    /**
     * Create a logger with a channel name in context.
     */
    public function channel(string $name): static
    {
        return $this->withContext(['channel' => $name]);
    }

    /** @param array<string, mixed> $context */
    public function emergency(string $message, array $context = []): static
    {
        $this->driver->emergency($message, $this->mergeContext($context));
        return $this;
    }

    /** @param array<string, mixed> $context */
    public function alert(string $message, array $context = []): static
    {
        $this->driver->alert($message, $this->mergeContext($context));
        return $this;
    }

    /** @param array<string, mixed> $context */
    public function critical(string $message, array $context = []): static
    {
        $this->driver->critical($message, $this->mergeContext($context));
        return $this;
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): static
    {
        $this->driver->error($message, $this->mergeContext($context));
        return $this;
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): static
    {
        $this->driver->warning($message, $this->mergeContext($context));
        return $this;
    }

    /** @param array<string, mixed> $context */
    public function notice(string $message, array $context = []): static
    {
        $this->driver->notice($message, $this->mergeContext($context));
        return $this;
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): static
    {
        $this->driver->info($message, $this->mergeContext($context));
        return $this;
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): static
    {
        $this->driver->debug($message, $this->mergeContext($context));
        return $this;
    }

    /** @param array<string, mixed> $context */
    public function log(LogLevel $level, string $message, array $context = []): static
    {
        $this->driver->log($level, $message, $this->mergeContext($context));
        return $this;
    }

    /**
     * Get the underlying driver.
     */
    public function driver(): LoggerInterface
    {
        return $this->driver;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function mergeContext(array $context): array
    {
        return array_merge($this->context, $context);
    }
}
