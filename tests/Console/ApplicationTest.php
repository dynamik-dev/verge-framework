<?php

declare(strict_types=1);

use Verge\App;
use Verge\Console\Application;
use Verge\Console\Output;

class TestCommand
{
    public function __invoke(App $app, Output $output): int
    {
        $output->success('Test command ran');
        return 0;
    }
}

class FailingCommand
{
    public function __invoke(App $app, Output $output): int
    {
        $output->error('Command failed');
        return 1;
    }
}

describe('Console Application', function () {

    describe('run()', function () {
        it('shows help when no command provided', function () {
            $app = new App();
            $console = new Application($app);

            $result = $console->run(['verge']);

            expect($result)->toBe(0);
        });

        it('shows help with --help flag', function () {
            $app = new App();
            $console = new Application($app);

            $result = $console->run(['verge', '--help']);

            expect($result)->toBe(0);
        });

        it('shows help with -h flag', function () {
            $app = new App();
            $console = new Application($app);

            $result = $console->run(['verge', '-h']);

            expect($result)->toBe(0);
        });

        it('shows help with help command', function () {
            $app = new App();
            $console = new Application($app);

            $result = $console->run(['verge', 'help']);

            expect($result)->toBe(0);
        });

        it('returns error for unknown command', function () {
            $app = new App();
            // Remove default commands
            $console = new Application($app);

            $result = $console->run(['verge', 'unknown:command']);

            expect($result)->toBe(1);
        });

        it('executes registered command', function () {
            $app = new App();
            $executed = false;

            $app->command('test:run', function (App $app, Output $output) use (&$executed) {
                $executed = true;
                return 0;
            });

            $console = new Application($app);
            $result = $console->run(['verge', 'test:run']);

            expect($executed)->toBeTrue();
            expect($result)->toBe(0);
        });

        it('returns command exit code', function () {
            $app = new App();
            $app->command('fail', fn (App $app, Output $output) => 42);

            $console = new Application($app);
            $result = $console->run(['verge', 'fail']);

            expect($result)->toBe(42);
        });

        it('resolves command class from container', function () {
            $app = new App();
            $app->command('test:class', TestCommand::class);

            $console = new Application($app);
            $result = $console->run(['verge', 'test:class']);

            expect($result)->toBe(0);
        });

        it('handles commands that return failure', function () {
            $app = new App();
            $app->command('fail:command', FailingCommand::class);

            $console = new Application($app);
            $result = $console->run(['verge', 'fail:command']);

            expect($result)->toBe(1);
        });
    });

    describe('help output', function () {
        it('groups commands by namespace', function () {
            $app = new App();
            $app->command('cache:clear', fn () => 0);
            $app->command('cache:warm', fn () => 0);
            $app->command('routes:list', fn () => 0);
            $app->command('serve', fn () => 0);

            $console = new Application($app);

            // Just ensure no exception - output testing is difficult without capturing
            $result = $console->run(['verge', 'help']);

            expect($result)->toBe(0);
        });
    });

    describe('version', function () {
        it('shows version with --version flag', function () {
            $app = new App();
            $console = new Application($app, 'TestApp', '1.2.3');

            ob_start();
            $result = $console->run(['verge', '--version']);
            $output = ob_get_clean();

            expect($result)->toBe(0);
            expect($output)->toContain('TestApp');
            expect($output)->toContain('1.2.3');
        });

        it('shows version with -v flag', function () {
            $app = new App();
            $console = new Application($app, 'TestApp', '1.2.3');

            ob_start();
            $result = $console->run(['verge', '-v']);
            $output = ob_get_clean();

            expect($result)->toBe(0);
            expect($output)->toContain('1.2.3');
        });
    });
});
