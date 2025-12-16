<?php

declare(strict_types=1);

namespace Verge\Clock;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * System clock that returns the current time.
 */
class Clock implements ClockInterface
{
    public function __construct(
        private ?\DateTimeZone $timezone = null
    ) {
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
}
