<?php

declare(strict_types=1);

use Verge\App;
use Verge\Log\Logger;
use Verge\Log\LoggerInterface;
use Verge\Log\LogLevel;
use Verge\Log\Drivers\ArrayLogDriver;
use Verge\Log\Drivers\StreamLogDriver;

describe('ArrayLogDriver', function () {

    describe('log levels', function () {
        it('logs emergency', function () {
            $driver = new ArrayLogDriver();
            $driver->emergency('System down');

            expect($driver->all())->toHaveCount(1);
            expect($driver->all()[0]['level'])->toBe(LogLevel::EMERGENCY);
            expect($driver->all()[0]['message'])->toBe('System down');
        });

        it('logs alert', function () {
            $driver = new ArrayLogDriver();
            $driver->alert('Action needed');

            expect($driver->level(LogLevel::ALERT))->toHaveCount(1);
        });

        it('logs critical', function () {
            $driver = new ArrayLogDriver();
            $driver->critical('Critical issue');

            expect($driver->level(LogLevel::CRITICAL))->toHaveCount(1);
        });

        it('logs error', function () {
            $driver = new ArrayLogDriver();
            $driver->error('Something failed');

            expect($driver->level(LogLevel::ERROR))->toHaveCount(1);
        });

        it('logs warning', function () {
            $driver = new ArrayLogDriver();
            $driver->warning('Be careful');

            expect($driver->level(LogLevel::WARNING))->toHaveCount(1);
        });

        it('logs notice', function () {
            $driver = new ArrayLogDriver();
            $driver->notice('FYI');

            expect($driver->level(LogLevel::NOTICE))->toHaveCount(1);
        });

        it('logs info', function () {
            $driver = new ArrayLogDriver();
            $driver->info('User logged in');

            expect($driver->level(LogLevel::INFO))->toHaveCount(1);
        });

        it('logs debug', function () {
            $driver = new ArrayLogDriver();
            $driver->debug('Variable value: 42');

            expect($driver->level(LogLevel::DEBUG))->toHaveCount(1);
        });
    });

    describe('context', function () {
        it('stores context with log', function () {
            $driver = new ArrayLogDriver();
            $driver->info('User action', ['user_id' => 123, 'action' => 'login']);

            expect($driver->all()[0]['context'])->toBe(['user_id' => 123, 'action' => 'login']);
        });
    });

    describe('filtering', function () {
        it('filters by level', function () {
            $driver = new ArrayLogDriver();
            $driver->info('Info message');
            $driver->error('Error message');
            $driver->info('Another info');

            expect($driver->level(LogLevel::INFO))->toHaveCount(2);
            expect($driver->level(LogLevel::ERROR))->toHaveCount(1);
        });

        it('checks if message was logged', function () {
            $driver = new ArrayLogDriver();
            $driver->info('User logged in');
            $driver->error('Connection failed');

            expect($driver->hasLogged('logged in'))->toBeTrue();
            expect($driver->hasLogged('logged in', LogLevel::INFO))->toBeTrue();
            expect($driver->hasLogged('logged in', LogLevel::ERROR))->toBeFalse();
            expect($driver->hasLogged('not there'))->toBeFalse();
        });

        it('counts logs', function () {
            $driver = new ArrayLogDriver();
            $driver->info('One');
            $driver->info('Two');
            $driver->info('Three');

            expect($driver->count())->toBe(3);
        });
    });

    describe('clear()', function () {
        it('removes all logs', function () {
            $driver = new ArrayLogDriver();
            $driver->info('One');
            $driver->info('Two');
            $driver->clear();

            expect($driver->count())->toBe(0);
            expect($driver->all())->toBe([]);
        });
    });

});

describe('StreamLogDriver', function () {

    it('writes to stream', function () {
        $stream = fopen('php://memory', 'r+');
        assert(is_resource($stream));

        $driver = new StreamLogDriver($stream);

        $driver->info('Test message');

        rewind($stream);
        $output = stream_get_contents($stream);

        expect($output)->toContain('INFO: Test message');
        expect($output)->toMatch('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/');
    });

    it('interpolates context placeholders', function () {
        $stream = fopen('php://memory', 'r+');
        assert(is_resource($stream));

        $driver = new StreamLogDriver($stream);

        $driver->info('User {name} logged in', ['name' => 'John']);

        rewind($stream);
        $output = stream_get_contents($stream);

        expect($output)->toContain('User John logged in');
    });

    it('respects minimum log level', function () {
        $stream = fopen('php://memory', 'r+');
        assert(is_resource($stream));

        $driver = new StreamLogDriver($stream, LogLevel::WARNING);

        $driver->debug('Debug message');
        $driver->info('Info message');
        $driver->warning('Warning message');
        $driver->error('Error message');

        rewind($stream);
        $output = stream_get_contents($stream);

        expect($output)->not->toContain('DEBUG');
        expect($output)->not->toContain('INFO');
        expect($output)->toContain('WARNING');
        expect($output)->toContain('ERROR');
    });

    it('writes to file path', function () {
        $path = sys_get_temp_dir() . '/verge-test-' . uniqid() . '.log';

        $driver = new StreamLogDriver($path);
        $driver->info('Test log entry');

        $content = file_get_contents($path);
        expect($content)->toContain('Test log entry');

        unlink($path);
    });

});

describe('LogLevel', function () {

    it('has correct priorities', function () {
        expect(LogLevel::EMERGENCY->priority())->toBe(0);
        expect(LogLevel::ALERT->priority())->toBe(1);
        expect(LogLevel::CRITICAL->priority())->toBe(2);
        expect(LogLevel::ERROR->priority())->toBe(3);
        expect(LogLevel::WARNING->priority())->toBe(4);
        expect(LogLevel::NOTICE->priority())->toBe(5);
        expect(LogLevel::INFO->priority())->toBe(6);
        expect(LogLevel::DEBUG->priority())->toBe(7);
    });

    it('has string values', function () {
        expect(LogLevel::ERROR->value)->toBe('error');
        expect(LogLevel::INFO->value)->toBe('info');
    });

});

describe('Logger Wrapper', function () {

    describe('fluent interface', function () {
        it('chains log calls', function () {
            $driver = new ArrayLogDriver();
            $logger = new Logger($driver);

            $result = $logger->info('One')->info('Two')->error('Three');

            expect($result)->toBeInstanceOf(Logger::class);
            expect($driver->count())->toBe(3);
        });
    });

    describe('withContext()', function () {
        it('adds default context to all logs', function () {
            $driver = new ArrayLogDriver();
            $logger = new Logger($driver);

            $contextLogger = $logger->withContext(['request_id' => 'abc123']);
            $contextLogger->info('Request started');
            $contextLogger->info('Request ended');

            expect($driver->all()[0]['context'])->toBe(['request_id' => 'abc123']);
            expect($driver->all()[1]['context'])->toBe(['request_id' => 'abc123']);
        });

        it('merges with per-call context', function () {
            $driver = new ArrayLogDriver();
            $logger = new Logger($driver);

            $contextLogger = $logger->withContext(['request_id' => 'abc123']);
            $contextLogger->info('User action', ['user_id' => 42]);

            expect($driver->all()[0]['context'])->toBe([
                'request_id' => 'abc123',
                'user_id' => 42,
            ]);
        });

        it('does not mutate original logger', function () {
            $driver = new ArrayLogDriver();
            $logger = new Logger($driver);

            $contextLogger = $logger->withContext(['extra' => 'data']);
            $logger->info('Original');
            $contextLogger->info('With context');

            expect($driver->all()[0]['context'])->toBe([]);
            expect($driver->all()[1]['context'])->toBe(['extra' => 'data']);
        });
    });

    describe('channel()', function () {
        it('adds channel to context', function () {
            $driver = new ArrayLogDriver();
            $logger = new Logger($driver);

            $logger->channel('api')->info('API request');
            $logger->channel('queue')->info('Job processed');

            expect($driver->all()[0]['context'])->toBe(['channel' => 'api']);
            expect($driver->all()[1]['context'])->toBe(['channel' => 'queue']);
        });
    });

    describe('driver()', function () {
        it('returns underlying driver', function () {
            $driver = new ArrayLogDriver();
            $logger = new Logger($driver);

            expect($logger->driver())->toBe($driver);
        });
    });

    describe('all log levels', function () {
        it('proxies all level methods', function () {
            $driver = new ArrayLogDriver();
            $logger = new Logger($driver);

            $logger->emergency('e');
            $logger->alert('a');
            $logger->critical('c');
            $logger->error('err');
            $logger->warning('w');
            $logger->notice('n');
            $logger->info('i');
            $logger->debug('d');
            $logger->log(LogLevel::INFO, 'via log');

            expect($driver->count())->toBe(9);
        });
    });

});

describe('App Logger Integration', function () {

    it('auto-wires Logger with default driver', function () {
        $app = new App();

        $logger = $app->container()->resolve(Logger::class);

        expect($logger)->toBeInstanceOf(Logger::class);
        expect($logger->driver())->toBeInstanceOf(StreamLogDriver::class);
    });

    it('allows swapping log driver', function () {
        $app = new App();
        $arrayDriver = new ArrayLogDriver();
        $app->container()->singleton(LoggerInterface::class, fn() => $arrayDriver);

        $logger = $app->container()->resolve(Logger::class);

        expect($logger->driver())->toBe($arrayDriver);
    });

    it('injects logger into handlers', function () {
        $app = new App();
        $arrayDriver = new ArrayLogDriver();
        $app->container()->singleton(LoggerInterface::class, fn() => $arrayDriver);

        $app->get('/test', function (Logger $logger) {
            $logger->info('Handler executed');
            return 'ok';
        });

        $app->test()->get('/test');

        expect($arrayDriver->hasLogged('Handler executed'))->toBeTrue();
    });

    it('shares logger singleton across requests', function () {
        $app = new App();
        $arrayDriver = new ArrayLogDriver();
        $app->container()->singleton(LoggerInterface::class, fn() => $arrayDriver);

        $app->get('/log', function (Logger $logger) {
            $logger->info('Request handled');
            return 'ok';
        });

        $app->test()->get('/log');
        $app->test()->get('/log');
        $app->test()->get('/log');

        expect($arrayDriver->count())->toBe(3);
    });

});
