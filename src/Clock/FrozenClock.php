<?php

declare(strict_types=1);

namespace Verge\Clock;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * A clock frozen at a specific time, useful for testing.
 */
class FrozenClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $time
    ) {
    }

    public static function at(string $time, ?\DateTimeZone $timezone = null): self
    {
        return new self(new DateTimeImmutable($time, $timezone));
    }

    public static function fromNow(?\DateTimeZone $timezone = null): self
    {
        return new self(new DateTimeImmutable('now', $timezone));
    }

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }

    public function setTo(DateTimeImmutable $time): void
    {
        $this->time = $time;
    }

    public function advance(string $interval): void
    {
        $this->time = $this->time->modify($interval);
    }
}
