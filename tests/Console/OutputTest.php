<?php

declare(strict_types=1);

use Minicli\Output\OutputHandler;
use Verge\Console\Output;

describe('Output', function () {

    it('delegates info() to handler', function () {
        $handler = Mockery::mock(OutputHandler::class);
        $handler->shouldReceive('info')->once()->with('Info message');

        $output = new Output($handler);
        $output->info('Info message');
    });

    it('delegates error() to handler', function () {
        $handler = Mockery::mock(OutputHandler::class);
        $handler->shouldReceive('error')->once()->with('Error message');

        $output = new Output($handler);
        $output->error('Error message');
    });

    it('delegates success() to handler', function () {
        $handler = Mockery::mock(OutputHandler::class);
        $handler->shouldReceive('success')->once()->with('Success message');

        $output = new Output($handler);
        $output->success('Success message');
    });

    it('delegates line() to handler display', function () {
        $handler = Mockery::mock(OutputHandler::class);
        $handler->shouldReceive('display')->once()->with('A line');

        $output = new Output($handler);
        $output->line('A line');
    });

    it('handles empty line()', function () {
        $handler = Mockery::mock(OutputHandler::class);
        $handler->shouldReceive('display')->once()->with('');

        $output = new Output($handler);
        $output->line();
    });

    describe('table()', function () {
        it('formats headers and rows into table', function () {
            $lines = [];
            $handler = Mockery::mock(OutputHandler::class);
            $handler->shouldReceive('display')->andReturnUsing(function ($line) use (&$lines) {
                $lines[] = $line;
            });

            $output = new Output($handler);
            $output->table(
                ['Name', 'Age'],
                [
                    ['Alice', '30'],
                    ['Bob', '25'],
                ]
            );

            // Check structure: separator, header, separator, row1, row2, separator
            expect(count($lines))->toBe(6);
            expect($lines[0])->toContain('+');
            expect($lines[1])->toContain('Name');
            expect($lines[1])->toContain('Age');
            expect($lines[3])->toContain('Alice');
            expect($lines[3])->toContain('30');
        });

        it('pads columns to maximum width', function () {
            $lines = [];
            $handler = Mockery::mock(OutputHandler::class);
            $handler->shouldReceive('display')->andReturnUsing(function ($line) use (&$lines) {
                $lines[] = $line;
            });

            $output = new Output($handler);
            $output->table(
                ['Short', 'LongerHeader'],
                [
                    ['X', 'Y'],
                    ['LongerValue', 'Z'],
                ]
            );

            // All rows should have same separator width
            expect($lines[0])->toBe($lines[2]);
            expect($lines[0])->toBe($lines[count($lines) - 1]);
        });

        it('handles empty table', function () {
            $lines = [];
            $handler = Mockery::mock(OutputHandler::class);
            $handler->shouldReceive('display')->andReturnUsing(function ($line) use (&$lines) {
                $lines[] = $line;
            });

            $output = new Output($handler);
            $output->table(['A', 'B'], []);

            // Should have separator, header, separator, closing separator
            expect(count($lines))->toBe(4);
        });
    });
});
