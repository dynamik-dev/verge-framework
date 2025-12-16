<?php

declare(strict_types=1);

namespace Verge\Console;

use Minicli\App as Minicli;
use Verge\App;

/**
 * Verge Console Application.
 */
class Application
{
    private Minicli $cli;
    private Output $output;

    public function __construct(
        private App $app,
        private string $name = 'Verge',
        private string $version = '0.1.0'
    ) {
        $this->cli = new Minicli();
        $this->cli->setSignature($this->getSignature());
        $this->output = new Output($this->cli->getPrinter());
    }

    /**
     * Run the CLI application.
     *
     * @param array<string> $argv
     */
    public function run(array $argv): int
    {
        // If no command, show help
        if (count($argv) < 2) {
            $this->showHelp();
            return 0;
        }

        $commandName = $argv[1];

        // Handle help flag
        if ($commandName === 'help' || $commandName === '--help' || $commandName === '-h') {
            $this->showHelp();
            return 0;
        }

        // Handle version flag
        if ($commandName === '--version' || $commandName === '-v') {
            echo "{$this->name} {$this->version}\n";
            return 0;
        }

        // Get commands from App
        $commands = $this->app->getCommands();

        // Find and run command
        if (!isset($commands[$commandName])) {
            $this->output->error("Unknown command: {$commandName}");
            $this->showHelp();
            return 1;
        }

        $handler = $commands[$commandName];

        // Resolve class string through container
        if (is_string($handler)) {
            $handler = $this->app->make($handler);
        }

        if (!is_callable($handler)) {
            $this->output->error("Command handler is not callable: {$commandName}");
            return 1;
        }

        // Call with App and Output
        return (int) $handler($this->app, $this->output);
    }

    private function getSignature(): string
    {
        return <<<SIGNATURE
        {$this->name} {$this->version}

        Usage: verge <command> [options]
        SIGNATURE;
    }

    private function showHelp(): void
    {
        $this->output->line("{$this->name} {$this->version}");
        $this->output->line('');
        $this->output->line('Usage: verge <command> [options]');
        $this->output->line('');
        $this->output->info('Available commands:');
        $this->output->line('');

        // Get commands from App
        $commands = $this->app->getCommands();

        // Group commands by namespace
        $grouped = [];
        foreach (array_keys($commands) as $name) {
            $parts = explode(':', $name);
            $namespace = count($parts) > 1 ? $parts[0] : 'general';
            $grouped[$namespace][] = $name;
        }

        ksort($grouped);

        foreach ($grouped as $namespace => $names) {
            if ($namespace !== 'general') {
                $this->output->line("  {$namespace}");
            }
            sort($names);
            foreach ($names as $name) {
                $this->output->line("    {$name}");
            }
        }

        $this->output->line('');
        $this->output->line('Options:');
        $this->output->line('  --help, -h     Show this help message');
        $this->output->line('  --version, -v  Show version');
    }
}
