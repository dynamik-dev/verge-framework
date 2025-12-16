<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Verge\Container;
use Verge\ContainerException;

// Test fixtures
class SimpleClass
{
    public function hello(): string
    {
        return 'hello';
    }
}

class ClassWithDependency
{
    public function __construct(public SimpleClass $simple)
    {
    }
}

class ClassWithDefaultValue
{
    public function __construct(public string $name = 'default')
    {
    }
}

class ClassWithBuiltinParam
{
    public function __construct(public string $required)
    {
    }
}

class ClassWithMixedParams
{
    public function __construct(
        public SimpleClass $service,
        public string $name,
        public int $count = 10
    ) {
    }
}

// Contextual binding fixtures
interface HttpClientInterface
{
    public function getBaseUri(): ?string;
}

class HttpClient implements HttpClientInterface
{
    public function __construct(public ?string $baseUri = null)
    {
    }

    public function getBaseUri(): ?string
    {
        return $this->baseUri;
    }
}

class WeatherService
{
    public function __construct(public HttpClientInterface $client)
    {
    }
}

class StripeService
{
    public function __construct(public HttpClientInterface $client)
    {
    }
}

class GenericService
{
    public function __construct(public HttpClientInterface $client)
    {
    }
}

interface TestInterface
{
}

class TestImplementation implements TestInterface
{
}

abstract class AbstractClass
{
}

class InvokableClass
{
    public function __invoke(string $name): string
    {
        return "Hello, $name";
    }
}

describe('Container', function () {

    describe('implements ContainerInterface', function () {
        it('implements PSR-11', function () {
            $container = new Container();

            expect($container)->toBeInstanceOf(ContainerInterface::class);
        });
    });

    describe('bind()', function () {
        it('binds a closure', function () {
            $container = new Container();

            $container->bind('greeting', fn () => 'Hello World');

            expect($container->get('greeting'))->toBe('Hello World');
        });

        it('binds a class string', function () {
            $container = new Container();

            $container->bind(TestInterface::class, TestImplementation::class);

            expect($container->get(TestInterface::class))->toBeInstanceOf(TestImplementation::class);
        });

        it('passes container to closure', function () {
            $container = new Container();
            $container->bind('dep', fn () => 'dependency');
            $container->bind('service', fn (Container $c) => 'got: ' . $c->get('dep'));

            expect($container->get('service'))->toBe('got: dependency');
        });

        it('creates new instance each time', function () {
            $container = new Container();
            $container->bind(SimpleClass::class, fn () => new SimpleClass());

            $first = $container->get(SimpleClass::class);
            $second = $container->get(SimpleClass::class);

            expect($first)->not->toBe($second);
        });
    });

    describe('singleton()', function () {
        it('returns same instance', function () {
            $container = new Container();
            $container->singleton(SimpleClass::class, fn () => new SimpleClass());

            $first = $container->get(SimpleClass::class);
            $second = $container->get(SimpleClass::class);

            expect($first)->toBe($second);
        });

        it('caches the instance', function () {
            $container = new Container();
            $callCount = 0;
            $container->singleton('counter', function () use (&$callCount) {
                $callCount++;
                return $callCount;
            });

            $container->get('counter');
            $container->get('counter');
            $container->get('counter');

            expect($callCount)->toBe(1);
        });
    });

    describe('get()', function () {
        it('resolves bound value', function () {
            $container = new Container();
            $container->bind('value', fn () => 42);

            expect($container->get('value'))->toBe(42);
        });

        it('auto-wires classes', function () {
            $container = new Container();

            $instance = $container->get(SimpleClass::class);

            expect($instance)->toBeInstanceOf(SimpleClass::class);
        });

        it('throws for non-existent class', function () {
            $container = new Container();

            expect(fn () => $container->get('NonExistentClass'))
                ->toThrow(ContainerException::class);
        });
    });

    describe('has()', function () {
        it('returns true for bound values', function () {
            $container = new Container();
            $container->bind('test', fn () => 'value');

            expect($container->has('test'))->toBeTrue();
        });

        it('returns true for instances', function () {
            $container = new Container();
            $container->instance('test', 'value');

            expect($container->has('test'))->toBeTrue();
        });

        it('returns true for existing classes', function () {
            $container = new Container();

            expect($container->has(SimpleClass::class))->toBeTrue();
        });

        it('returns false for non-existent', function () {
            $container = new Container();

            expect($container->has('NonExistentClass'))->toBeFalse();
            expect($container->has('unbound'))->toBeFalse();
        });
    });

    describe('resolve()', function () {
        it('returns cached instance', function () {
            $container = new Container();
            $obj = new SimpleClass();
            $container->instance('cached', $obj);

            expect($container->resolve('cached'))->toBe($obj);
        });

        it('resolves binding', function () {
            $container = new Container();
            $container->bind('test', fn () => 'resolved');

            expect($container->resolve('test'))->toBe('resolved');
        });

        it('builds class if not bound', function () {
            $container = new Container();

            $instance = $container->resolve(SimpleClass::class);

            expect($instance)->toBeInstanceOf(SimpleClass::class);
        });

        it('passes parameters to build', function () {
            $container = new Container();

            $instance = $container->resolve(ClassWithMixedParams::class, ['name' => 'chris']);

            expect($instance->name)->toBe('chris');
            expect($instance->service)->toBeInstanceOf(SimpleClass::class);
            expect($instance->count)->toBe(10);
        });

        it('skips cache when parameters provided', function () {
            $container = new Container();
            $container->singleton(ClassWithMixedParams::class, fn ($c) => new ClassWithMixedParams(
                $c->resolve(SimpleClass::class),
                'cached',
                99
            ));

            // First call uses singleton
            $cached = $container->resolve(ClassWithMixedParams::class);
            expect($cached->name)->toBe('cached');

            // With parameters, builds fresh
            $fresh = $container->resolve(ClassWithMixedParams::class, ['name' => 'fresh']);
            expect($fresh->name)->toBe('fresh');
            expect($fresh->count)->toBe(10); // default, not 99
        });
    });

    describe('build()', function () {
        it('builds class without constructor', function () {
            $container = new Container();

            $instance = $container->build(SimpleClass::class);

            expect($instance)->toBeInstanceOf(SimpleClass::class);
        });

        it('builds class with dependencies', function () {
            $container = new Container();

            $instance = $container->build(ClassWithDependency::class);

            expect($instance)->toBeInstanceOf(ClassWithDependency::class);
            expect($instance->simple)->toBeInstanceOf(SimpleClass::class);
        });

        it('builds class with default values', function () {
            $container = new Container();

            $instance = $container->build(ClassWithDefaultValue::class);

            expect($instance->name)->toBe('default');
        });

        it('throws for non-existent class', function () {
            $container = new Container();

            expect(fn () => $container->build('NonExistentClass'))
                ->toThrow(ContainerException::class, 'does not exist');
        });

        it('throws for abstract class', function () {
            $container = new Container();

            expect(fn () => $container->build(AbstractClass::class))
                ->toThrow(ContainerException::class, 'not instantiable');
        });

        it('throws for interface', function () {
            $container = new Container();

            expect(fn () => $container->build(TestInterface::class))
                ->toThrow(ContainerException::class);
        });

        it('throws for unresolvable parameter', function () {
            $container = new Container();

            expect(fn () => $container->build(ClassWithBuiltinParam::class))
                ->toThrow(ContainerException::class, 'Cannot resolve parameter');
        });

        it('builds with explicit parameters', function () {
            $container = new Container();

            $instance = $container->build(ClassWithBuiltinParam::class, ['required' => 'provided']);

            expect($instance->required)->toBe('provided');
        });

        it('builds with mixed auto-wired and explicit parameters', function () {
            $container = new Container();

            $instance = $container->build(ClassWithMixedParams::class, [
                'name' => 'chris',
                'count' => 42,
            ]);

            expect($instance->service)->toBeInstanceOf(SimpleClass::class);
            expect($instance->name)->toBe('chris');
            expect($instance->count)->toBe(42);
        });

        it('explicit parameters override defaults', function () {
            $container = new Container();

            $instance = $container->build(ClassWithDefaultValue::class, ['name' => 'custom']);

            expect($instance->name)->toBe('custom');
        });
    });

    describe('call()', function () {
        it('calls closure', function () {
            $container = new Container();

            $result = $container->call(fn () => 'hello');

            expect($result)->toBe('hello');
        });

        it('calls closure with parameters', function () {
            $container = new Container();

            $result = $container->call(
                fn (string $name) => "Hello, $name",
                ['name' => 'World']
            );

            expect($result)->toBe('Hello, World');
        });

        it('auto-wires closure dependencies', function () {
            $container = new Container();

            $result = $container->call(fn (SimpleClass $simple) => $simple->hello());

            expect($result)->toBe('hello');
        });

        it('calls array callable', function () {
            $container = new Container();
            $obj = new SimpleClass();

            $result = $container->call([$obj, 'hello']);

            expect($result)->toBe('hello');
        });

        it('calls invokable object', function () {
            $container = new Container();
            $invokable = new InvokableClass();

            $result = $container->call($invokable, ['name' => 'World']);

            expect($result)->toBe('Hello, World');
        });

        it('prefers provided parameters over auto-wiring', function () {
            $container = new Container();
            $custom = new SimpleClass();

            $result = $container->call(
                fn (SimpleClass $simple) => $simple,
                ['simple' => $custom]
            );

            expect($result)->toBe($custom);
        });

        it('uses default values', function () {
            $container = new Container();

            $result = $container->call(fn (string $name = 'default') => $name);

            expect($result)->toBe('default');
        });

        it('throws for unresolvable callable', function () {
            $container = new Container();

            // PHP's callable type hint throws TypeError for non-existent functions
            expect(fn () => $container->call('not_a_function'))
                ->toThrow(TypeError::class);
        });
    });

    describe('scoped()', function () {
        it('returns same instance within scope', function () {
            $container = new Container();
            $container->scoped(SimpleClass::class, fn () => new SimpleClass());

            $first = $container->get(SimpleClass::class);
            $second = $container->get(SimpleClass::class);

            expect($first)->toBe($second);
        });

        it('caches the instance like singleton', function () {
            $container = new Container();
            $callCount = 0;
            $container->scoped('counter', function () use (&$callCount) {
                $callCount++;
                return $callCount;
            });

            $container->get('counter');
            $container->get('counter');
            $container->get('counter');

            expect($callCount)->toBe(1);
        });

        it('is cleared by forgetScopedInstances', function () {
            $container = new Container();
            $callCount = 0;
            $container->scoped('counter', function () use (&$callCount) {
                $callCount++;
                return $callCount;
            });

            $container->get('counter');
            expect($callCount)->toBe(1);

            $container->forgetScopedInstances();

            $container->get('counter');
            expect($callCount)->toBe(2);
        });
    });

    describe('forgetScopedInstances()', function () {
        it('clears scoped instances', function () {
            $container = new Container();
            $container->scoped(SimpleClass::class, fn () => new SimpleClass());

            $first = $container->get(SimpleClass::class);
            $container->forgetScopedInstances();
            $second = $container->get(SimpleClass::class);

            expect($first)->not->toBe($second);
        });

        it('does not clear singleton instances', function () {
            $container = new Container();
            $container->singleton(SimpleClass::class, fn () => new SimpleClass());

            $first = $container->get(SimpleClass::class);
            $container->forgetScopedInstances();
            $second = $container->get(SimpleClass::class);

            expect($first)->toBe($second);
        });

        it('does not clear regular instances', function () {
            $container = new Container();
            $obj = new SimpleClass();
            $container->instance('test', $obj);

            $container->forgetScopedInstances();

            expect($container->get('test'))->toBe($obj);
        });
    });

    describe('instance()', function () {
        it('stores instance directly', function () {
            $container = new Container();
            $obj = new SimpleClass();

            $container->instance('test', $obj);

            expect($container->get('test'))->toBe($obj);
        });

        it('stores any value', function () {
            $container = new Container();

            $container->instance('string', 'hello');
            $container->instance('array', [1, 2, 3]);
            $container->instance('int', 42);

            expect($container->get('string'))->toBe('hello');
            expect($container->get('array'))->toBe([1, 2, 3]);
            expect($container->get('int'))->toBe(42);
        });

        it('overrides existing instance', function () {
            $container = new Container();

            $container->instance('test', 'first');
            $container->instance('test', 'second');

            expect($container->get('test'))->toBe('second');
        });
    });

    describe('for() contextual binding', function () {
        it('binds different implementations for different consumers', function () {
            $container = new Container();

            $container->bind(HttpClientInterface::class, fn () => new HttpClient('https://api.weather.com'))
                ->for(WeatherService::class);

            $container->bind(HttpClientInterface::class, fn () => new HttpClient('https://api.stripe.com'))
                ->for(StripeService::class);

            $weather = $container->build(WeatherService::class);
            $stripe = $container->build(StripeService::class);

            expect($weather->client->getBaseUri())->toBe('https://api.weather.com');
            expect($stripe->client->getBaseUri())->toBe('https://api.stripe.com');
        });

        it('falls back to default binding when no contextual binding exists', function () {
            $container = new Container();

            $container->bind(HttpClientInterface::class, fn () => new HttpClient('https://api.weather.com'))
                ->for(WeatherService::class);

            // Default binding for everything else
            $container->bind(HttpClientInterface::class, fn () => new HttpClient('https://default.com'));

            $weather = $container->build(WeatherService::class);
            $generic = $container->build(GenericService::class);

            expect($weather->client->getBaseUri())->toBe('https://api.weather.com');
            expect($generic->client->getBaseUri())->toBe('https://default.com');
        });

        it('accepts array of contexts', function () {
            $container = new Container();
            $sharedClient = new HttpClient('https://shared.api.com');

            $container->bind(HttpClientInterface::class, fn () => $sharedClient)
                ->for([WeatherService::class, StripeService::class]);

            $weather = $container->build(WeatherService::class);
            $stripe = $container->build(StripeService::class);

            expect($weather->client)->toBe($sharedClient);
            expect($stripe->client)->toBe($sharedClient);
        });

        it('works with singleton contextual bindings', function () {
            $container = new Container();

            $container->singleton(HttpClientInterface::class, fn () => new HttpClient('https://api.weather.com'))
                ->for(WeatherService::class);

            // Default for others
            $container->bind(HttpClientInterface::class, fn () => new HttpClient('https://default.com'));

            $weather1 = $container->build(WeatherService::class);
            $weather2 = $container->build(WeatherService::class);

            // Each build creates new WeatherService, but client comes from contextual (not singleton cached)
            // Note: contextual bindings don't cache like singletons - they're resolved fresh each time
            expect($weather1->client->getBaseUri())->toBe('https://api.weather.com');
            expect($weather2->client->getBaseUri())->toBe('https://api.weather.com');
        });

        it('throws when for() called without preceding binding', function () {
            $container = new Container();

            expect(fn () => $container->for(WeatherService::class))
                ->toThrow(ContainerException::class, 'without a preceding');
        });

        it('removes binding from defaults when for() is called', function () {
            $container = new Container();

            $container->bind(HttpClientInterface::class, fn () => new HttpClient('https://contextual.com'))
                ->for(WeatherService::class);

            // No default binding exists, so this should fail for GenericService
            expect(fn () => $container->build(GenericService::class))
                ->toThrow(ContainerException::class);
        });
    });

});
