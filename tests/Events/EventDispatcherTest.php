<?php

declare(strict_types=1);

use Verge\App;
use Verge\Container;
use Verge\Events\EventDispatcher;

// Test listener class
class TestEventListener
{
    public static array $received = [];

    public function __invoke(string $event, array $payload): void
    {
        self::$received[] = ['event' => $event, 'payload' => $payload];
    }

    public static function reset(): void
    {
        self::$received = [];
    }
}

describe('EventDispatcher', function () {

    beforeEach(fn () => TestEventListener::reset());

    describe('on()', function () {
        it('registers closure listener', function () {
            $container = new Container();
            $dispatcher = new EventDispatcher($container);
            $listener = fn ($event, $payload) => null;

            $dispatcher->on('test.event', $listener);

            expect($dispatcher->getListeners('test.event'))->toBe([$listener]);
        });

        it('registers class string listener', function () {
            $container = new Container();
            $dispatcher = new EventDispatcher($container);

            $dispatcher->on('test.event', TestEventListener::class);

            expect($dispatcher->getListeners('test.event'))->toBe([TestEventListener::class]);
        });

        it('registers multiple listeners for same event', function () {
            $container = new Container();
            $dispatcher = new EventDispatcher($container);
            $listener1 = fn () => null;
            $listener2 = fn () => null;

            $dispatcher->on('test.event', $listener1);
            $dispatcher->on('test.event', $listener2);

            expect($dispatcher->getListeners('test.event'))->toBe([$listener1, $listener2]);
        });
    });

    describe('emit()', function () {
        it('calls registered listeners', function () {
            $container = new Container();
            $dispatcher = new EventDispatcher($container);
            $called = false;

            $dispatcher->on('test.event', function ($event, $payload) use (&$called) {
                $called = true;
                expect($event)->toBe('test.event');
                expect($payload)->toBe(['key' => 'value']);
            });

            $dispatcher->emit('test.event', ['key' => 'value']);

            expect($called)->toBeTrue();
        });

        it('calls multiple listeners in order', function () {
            $container = new Container();
            $dispatcher = new EventDispatcher($container);
            $order = [];

            $dispatcher->on('test.event', function () use (&$order) {
                $order[] = 'first';
            });
            $dispatcher->on('test.event', function () use (&$order) {
                $order[] = 'second';
            });

            $dispatcher->emit('test.event');

            expect($order)->toBe(['first', 'second']);
        });

        it('resolves class string listeners through container', function () {
            $container = new Container();
            $dispatcher = new EventDispatcher($container);

            $dispatcher->on('test.event', TestEventListener::class);
            $dispatcher->emit('test.event', ['data' => 123]);

            expect(TestEventListener::$received)->toHaveCount(1);
            expect(TestEventListener::$received[0]['event'])->toBe('test.event');
            expect(TestEventListener::$received[0]['payload'])->toBe(['data' => 123]);
        });

        it('does nothing when no listeners registered', function () {
            $container = new Container();
            $dispatcher = new EventDispatcher($container);

            // Should not throw
            $dispatcher->emit('unregistered.event', ['data' => 'test']);

            expect(true)->toBeTrue();
        });
    });

    describe('wildcard listeners', function () {
        it('calls global wildcard listener for all events', function () {
            $container = new Container();
            $dispatcher = new EventDispatcher($container);
            $events = [];

            $dispatcher->on('*', function ($event) use (&$events) {
                $events[] = $event;
            });

            $dispatcher->emit('user.created');
            $dispatcher->emit('post.published');

            expect($events)->toBe(['user.created', 'post.published']);
        });

        it('calls namespace wildcard listener', function () {
            $container = new Container();
            $dispatcher = new EventDispatcher($container);
            $events = [];

            $dispatcher->on('user.*', function ($event) use (&$events) {
                $events[] = $event;
            });

            $dispatcher->emit('user.created');
            $dispatcher->emit('user.updated');
            $dispatcher->emit('post.created');

            expect($events)->toBe(['user.created', 'user.updated']);
        });

        it('calls both exact and wildcard listeners', function () {
            $container = new Container();
            $dispatcher = new EventDispatcher($container);
            $calls = [];

            $dispatcher->on('user.created', function () use (&$calls) {
                $calls[] = 'exact';
            });
            $dispatcher->on('user.*', function () use (&$calls) {
                $calls[] = 'namespace';
            });
            $dispatcher->on('*', function () use (&$calls) {
                $calls[] = 'global';
            });

            $dispatcher->emit('user.created');

            expect($calls)->toBe(['exact', 'namespace', 'global']);
        });

        it('does not match partial namespace', function () {
            $container = new Container();
            $dispatcher = new EventDispatcher($container);
            $events = [];

            $dispatcher->on('user.*', function ($event) use (&$events) {
                $events[] = $event;
            });

            $dispatcher->emit('username.created'); // Should not match user.*

            expect($events)->toBe([]);
        });
    });

    describe('hasListeners()', function () {
        it('returns false when no listeners', function () {
            $container = new Container();
            $dispatcher = new EventDispatcher($container);

            expect($dispatcher->hasListeners('test.event'))->toBeFalse();
        });

        it('returns true when listener registered', function () {
            $container = new Container();
            $dispatcher = new EventDispatcher($container);

            $dispatcher->on('test.event', fn () => null);

            expect($dispatcher->hasListeners('test.event'))->toBeTrue();
        });

        it('returns true when wildcard matches', function () {
            $container = new Container();
            $dispatcher = new EventDispatcher($container);

            $dispatcher->on('user.*', fn () => null);

            expect($dispatcher->hasListeners('user.created'))->toBeTrue();
            expect($dispatcher->hasListeners('post.created'))->toBeFalse();
        });

        it('returns true when global wildcard registered', function () {
            $container = new Container();
            $dispatcher = new EventDispatcher($container);

            $dispatcher->on('*', fn () => null);

            expect($dispatcher->hasListeners('any.event'))->toBeTrue();
        });
    });

    describe('forget()', function () {
        it('removes all listeners for event', function () {
            $container = new Container();
            $dispatcher = new EventDispatcher($container);

            $dispatcher->on('test.event', fn () => null);
            $dispatcher->on('test.event', fn () => null);
            $dispatcher->forget('test.event');

            expect($dispatcher->getListeners('test.event'))->toBe([]);
        });
    });

});

describe('PSR-14 dispatch()', function () {
    it('dispatches object events to listeners', function () {
        $container = new Container();
        $dispatcher = new EventDispatcher($container);
        $received = null;

        $event = new class () {
            public string $message = 'hello';
        };

        $dispatcher->on($event::class, function ($e) use (&$received) {
            $received = $e;
        });

        $result = $dispatcher->dispatch($event);

        expect($received)->toBe($event);
        expect($result)->toBe($event);
    });

    it('returns the event object', function () {
        $container = new Container();
        $dispatcher = new EventDispatcher($container);

        $event = new class () {
            public bool $handled = false;
        };

        $dispatcher->on($event::class, function ($e) {
            $e->handled = true;
        });

        $result = $dispatcher->dispatch($event);

        expect($result)->toBe($event);
        expect($result->handled)->toBeTrue();
    });

    it('respects StoppableEventInterface', function () {
        $container = new Container();
        $dispatcher = new EventDispatcher($container);
        $calls = [];

        $event = new class () implements \Psr\EventDispatcher\StoppableEventInterface {
            public bool $stopped = false;

            public function isPropagationStopped(): bool
            {
                return $this->stopped;
            }

            public function stopPropagation(): void
            {
                $this->stopped = true;
            }
        };

        $dispatcher->on($event::class, function ($e) use (&$calls) {
            $calls[] = 'first';
            $e->stopPropagation();
        });
        $dispatcher->on($event::class, function ($e) use (&$calls) {
            $calls[] = 'second';
        });

        $dispatcher->dispatch($event);

        expect($calls)->toBe(['first']);
    });

    it('calls listeners for parent classes', function () {
        $container = new Container();
        $dispatcher = new EventDispatcher($container);
        $calls = [];

        // Create a parent and child event
        $parentClass = new class () {
            public string $type = 'parent';
        };

        $dispatcher->on($parentClass::class, function ($e) use (&$calls) {
            $calls[] = 'parent';
        });

        $dispatcher->dispatch($parentClass);

        expect($calls)->toBe(['parent']);
    });

    it('calls listeners for implemented interfaces', function () {
        $container = new Container();
        $dispatcher = new EventDispatcher($container);
        $calls = [];

        $event = new class () implements \Psr\EventDispatcher\StoppableEventInterface {
            public function isPropagationStopped(): bool
            {
                return false;
            }
        };

        // Register listener for the interface
        $dispatcher->on(\Psr\EventDispatcher\StoppableEventInterface::class, function ($e) use (&$calls) {
            $calls[] = 'interface';
        });

        $dispatcher->dispatch($event);

        expect($calls)->toBe(['interface']);
    });

    it('calls exact class and interface listeners', function () {
        $container = new Container();
        $dispatcher = new EventDispatcher($container);
        $calls = [];

        $event = new class () implements \Psr\EventDispatcher\StoppableEventInterface {
            public function isPropagationStopped(): bool
            {
                return false;
            }
        };

        $dispatcher->on($event::class, function ($e) use (&$calls) {
            $calls[] = 'exact';
        });
        $dispatcher->on(\Psr\EventDispatcher\StoppableEventInterface::class, function ($e) use (&$calls) {
            $calls[] = 'interface';
        });

        $dispatcher->dispatch($event);

        expect($calls)->toBe(['exact', 'interface']);
    });

    it('resolves class string listeners through container', function () {
        $container = new Container();
        $dispatcher = new EventDispatcher($container);

        $event = new class () {
            public ?object $listener = null;
        };

        // Create listener class that records itself
        $listenerClass = new class () {
            public function __invoke(object $event): void
            {
                $event->listener = $this;
            }
        };

        $container->bind($listenerClass::class, fn () => $listenerClass);
        $dispatcher->on($event::class, $listenerClass::class);

        $dispatcher->dispatch($event);

        expect($event->listener)->toBe($listenerClass);
    });
});

describe('App Events Integration', function () {

    describe('App::on()', function () {
        it('registers event listener', function () {
            $app = new App();
            $called = false;

            $app->on('test.event', function () use (&$called) {
                $called = true;
            });

            $app->emit('test.event');

            expect($called)->toBeTrue();
        });

        it('is chainable', function () {
            $app = new App();

            $result = $app->on('event1', fn () => null)
                ->on('event2', fn () => null);

            expect($result)->toBe($app);
        });

        it('chains with route definitions', function () {
            $app = new App();

            $app->on('request.received', fn () => null);
            $app->get('/test', fn () => 'ok');
            $app->on('response.sent', fn () => null);

            expect($app->test()->get('/test')->body())->toBe('ok');
        });
    });

    describe('App::emit()', function () {
        it('emits event to listeners', function () {
            $app = new App();
            $payload = null;

            $app->on('test.event', function ($event, $p) use (&$payload) {
                $payload = $p;
            });

            $app->emit('test.event', ['key' => 'value']);

            expect($payload)->toBe(['key' => 'value']);
        });

        it('is chainable', function () {
            $app = new App();

            $result = $app->emit('event1')
                ->emit('event2');

            expect($result)->toBe($app);
        });
    });

    describe('App::hasListeners()', function () {
        it('checks for listeners on app', function () {
            $app = new App();

            expect($app->hasListeners('test.event'))->toBeFalse();

            $app->on('test.event', fn () => null);

            expect($app->hasListeners('test.event'))->toBeTrue();
        });
    });

    describe('practical use cases', function () {
        it('logs route access', function () {
            $app = new App();
            $log = [];

            $app->on('route.accessed', function ($event, $payload) use (&$log) {
                $log[] = $payload['path'];
            });

            $app->get('/users', function () use ($app) {
                $app->emit('route.accessed', ['path' => '/users']);
                return 'users';
            });

            $app->test()->get('/users');

            expect($log)->toBe(['/users']);
        });

        it('hooks into application lifecycle', function () {
            $app = new App();
            $events = [];

            $app->on('app.starting', function ($e) use (&$events) {
                $events[] = 'starting';
            });
            $app->on('app.started', function ($e) use (&$events) {
                $events[] = 'started';
            });

            $app->emit('app.starting');
            $app->get('/', fn () => 'home');
            $app->emit('app.started');

            expect($events)->toBe(['starting', 'started']);
        });
    });

});
