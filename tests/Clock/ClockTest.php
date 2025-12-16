<?php

declare(strict_types=1);

use Psr\Clock\ClockInterface;
use Verge\App;
use Verge\Clock\Clock;
use Verge\Clock\FrozenClock;

describe('Clock', function () {
    it('implements PSR-20 ClockInterface', function () {
        $clock = new Clock();

        expect($clock)->toBeInstanceOf(ClockInterface::class);
    });

    it('returns current time', function () {
        $clock = new Clock();
        $before = new DateTimeImmutable();

        $now = $clock->now();

        $after = new DateTimeImmutable();

        expect($now->getTimestamp())->toBeGreaterThanOrEqual($before->getTimestamp());
        expect($now->getTimestamp())->toBeLessThanOrEqual($after->getTimestamp());
    });

    it('respects timezone', function () {
        $timezone = new DateTimeZone('America/New_York');
        $clock = new Clock($timezone);

        $now = $clock->now();

        expect($now->getTimezone()->getName())->toBe('America/New_York');
    });
});

describe('FrozenClock', function () {
    it('implements PSR-20 ClockInterface', function () {
        $clock = FrozenClock::at('2024-01-15 12:00:00');

        expect($clock)->toBeInstanceOf(ClockInterface::class);
    });

    it('returns frozen time', function () {
        $clock = FrozenClock::at('2024-01-15 12:00:00');

        $now1 = $clock->now();
        usleep(1000); // 1ms
        $now2 = $clock->now();

        expect($now1)->toEqual($now2);
        expect($now1->format('Y-m-d H:i:s'))->toBe('2024-01-15 12:00:00');
    });

    it('creates from DateTimeImmutable', function () {
        $time = new DateTimeImmutable('2024-06-01 15:30:00');
        $clock = new FrozenClock($time);

        expect($clock->now())->toBe($time);
    });

    it('creates at specific time', function () {
        $clock = FrozenClock::at('2024-03-20 09:15:30');

        expect($clock->now()->format('Y-m-d H:i:s'))->toBe('2024-03-20 09:15:30');
    });

    it('creates at current time', function () {
        $before = new DateTimeImmutable();
        $clock = FrozenClock::fromNow();
        $after = new DateTimeImmutable();

        expect($clock->now()->getTimestamp())->toBeGreaterThanOrEqual($before->getTimestamp());
        expect($clock->now()->getTimestamp())->toBeLessThanOrEqual($after->getTimestamp());
    });

    it('respects timezone when creating', function () {
        $timezone = new DateTimeZone('Europe/London');
        $clock = FrozenClock::at('2024-01-15 12:00:00', $timezone);

        expect($clock->now()->getTimezone()->getName())->toBe('Europe/London');
    });

    it('can set time', function () {
        $clock = FrozenClock::at('2024-01-15 12:00:00');
        $newTime = new DateTimeImmutable('2024-12-25 00:00:00');

        $clock->setTo($newTime);

        expect($clock->now())->toBe($newTime);
    });

    it('can advance time', function () {
        $clock = FrozenClock::at('2024-01-15 12:00:00');

        $clock->advance('+1 hour');

        expect($clock->now()->format('Y-m-d H:i:s'))->toBe('2024-01-15 13:00:00');
    });

    it('can advance multiple times', function () {
        $clock = FrozenClock::at('2024-01-15 12:00:00');

        $clock->advance('+1 day');
        $clock->advance('+2 hours');
        $clock->advance('+30 minutes');

        expect($clock->now()->format('Y-m-d H:i:s'))->toBe('2024-01-16 14:30:00');
    });

    it('can go backward in time', function () {
        $clock = FrozenClock::at('2024-01-15 12:00:00');

        $clock->advance('-3 days');

        expect($clock->now()->format('Y-m-d H:i:s'))->toBe('2024-01-12 12:00:00');
    });
});

describe('ClockModule Integration', function () {
    it('registers Clock in container', function () {
        $app = new App();

        $clock = $app->make(Clock::class);

        expect($clock)->toBeInstanceOf(Clock::class);
    });

    it('registers PSR-20 interface', function () {
        $app = new App();

        $clock = $app->make(ClockInterface::class);

        expect($clock)->toBeInstanceOf(ClockInterface::class);
        expect($clock)->toBeInstanceOf(Clock::class);
    });

    it('returns singleton for Clock', function () {
        $app = new App();

        $clock1 = $app->make(Clock::class);
        $clock2 = $app->make(Clock::class);

        expect($clock1)->toBe($clock2);
    });

    it('can be injected into handlers', function () {
        $app = new App();

        $app->get('/time', function (ClockInterface $clock) {
            return ['time' => $clock->now()->format('Y-m-d H:i:s')];
        });

        $response = $app->test()->get('/time');

        expect($response->status())->toBe(200);
        expect($response->json())->toHaveKey('time');
    });

    it('can swap to frozen clock for testing', function () {
        $app = new App();
        $frozen = FrozenClock::at('2024-01-15 12:00:00');
        $app->instance(ClockInterface::class, $frozen);

        $app->get('/time', function (ClockInterface $clock) {
            return ['time' => $clock->now()->format('Y-m-d H:i:s')];
        });

        $response = $app->test()->get('/time');

        expect($response->json()['time'])->toBe('2024-01-15 12:00:00');
    });
});
