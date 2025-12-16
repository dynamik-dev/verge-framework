<?php

declare(strict_types=1);

namespace Verge\Console;

use Minicli\Output\OutputHandler;

/**
 * CLI output helper.
 */
class Output
{
    public function __construct(
        private OutputHandler $handler
    ) {
    }

    /**
     * Print an info message (cyan).
     */
    public function info(string $message): void
    {
        $this->handler->info($message);
    }

    /**
     * Print an error message (red).
     */
    public function error(string $message): void
    {
        $this->handler->error($message);
    }

    /**
     * Print a success message (green).
     */
    public function success(string $message): void
    {
        $this->handler->success($message);
    }

    /**
     * Print a line.
     */
    public function line(string $message = ''): void
    {
        $this->handler->display($message);
    }

    /**
     * Print a table.
     *
     * @param array<string> $headers
     * @param array<array<string>> $rows
     */
    public function table(array $headers, array $rows): void
    {
        // Calculate column widths
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($header);
        }
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen($cell));
            }
        }

        // Print header
        $headerLine = '|';
        $separator = '+';
        foreach ($headers as $i => $header) {
            $headerLine .= ' ' . str_pad($header, $widths[$i]) . ' |';
            $separator .= str_repeat('-', $widths[$i] + 2) . '+';
        }

        $this->line($separator);
        $this->line($headerLine);
        $this->line($separator);

        // Print rows
        foreach ($rows as $row) {
            $rowLine = '|';
            foreach ($row as $i => $cell) {
                $rowLine .= ' ' . str_pad($cell, $widths[$i]) . ' |';
            }
            $this->line($rowLine);
        }
        $this->line($separator);
    }
}
