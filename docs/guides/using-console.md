---
title: Running CLI Commands
description: Execute commands, run maintenance tasks, and build custom CLI tools.
---

Most applications need to do more than handle HTTP requests—warm caches, sync data, clear old records. Verge includes a CLI tool at `bin/verge` that makes this straightforward.

## Running Built-in Commands

Verge ships with commands for common maintenance tasks. List your routes to verify they're registered correctly:

```bash
./bin/verge routes:list
```

Before deploying to production, warm your caches to speed up the first request:

```bash
./bin/verge cache:warm
```

When you need to clear cached data during development:

```bash
./bin/verge cache:clear
```

## Bootstrapping Your Application

The CLI needs access to your app instance to execute commands. Create a `bootstrap/app.php` file that returns your configured application:

```php
<?php

use Verge\App;

$app = new App();

// Configure your app
$app->get('/users', UserController::class);
$app->use(AuthMiddleware::class);

return $app;
```

Verge looks for this file in two locations by default:

1. `bootstrap/app.php`
2. `app.php`

If your bootstrap file is somewhere else, specify it explicitly:

```bash
./bin/verge routes:list --app=/path/to/my-app.php
```

## Registering Custom Commands

When you need to run scheduled tasks, data migrations, or one-off scripts, register a command using `command()`:

```php
<?php

use Verge\App;
use Verge\Console\Output;

$app = new App();

$app->command('users:sync', function(App $app, Output $output) {
    $users = fetchUsersFromExternalAPI();

    foreach ($users as $user) {
        saveUser($user);
        $output->line("Synced user: {$user['email']}");
    }

    $output->success("Synced " . count($users) . " users");
    return 0;
});

return $app;
```

Command names typically use a `namespace:action` format. This groups related commands together when listing them.

## Understanding Command Handlers

Every command handler receives two parameters:

```php
function(App $app, Output $output): int
```

The `App` instance lets you resolve services from the container. The `Output` helper formats console messages. Return `0` for success or `1` for failure—just like any Unix command.

```php
$app->command('posts:cleanup', function(App $app, Output $output) {
    try {
        $deleted = $app->make(PostRepository::class)->deleteOldPosts();
        $output->success("Deleted {$deleted} old posts");
        return 0;
    } catch (Exception $e) {
        $output->error("Cleanup failed: " . $e->getMessage());
        return 1;
    }
});
```

## Formatting Console Output

The `Output` helper makes your commands readable. Use different methods for different message types:

```php
$output->line('Starting sync...');
$output->info('Processing batch 1 of 5');
$output->success('All users synced successfully');
$output->error('Connection to API failed');
```

When showing tabular data, use `table()` to format columns automatically:

```php
$app->command('users:list', function(App $app, Output $output) {
    $users = fetchUsers();

    $rows = array_map(fn($u) => [
        $u['id'],
        $u['name'],
        $u['email']
    ], $users);

    $output->table(
        ['ID', 'Name', 'Email'],
        $rows
    );

    return 0;
});
```

## Creating Command Classes

For commands with complex logic, use a class instead of a closure. This makes testing easier and keeps your bootstrap file clean:

```php
<?php

namespace App\Console;

use Verge\App;
use Verge\Console\Output;

class UserSyncCommand
{
    public function __construct(
        private UserRepository $users,
        private ExternalAPI $api
    ) {}

    public function __invoke(App $app, Output $output): int
    {
        $output->info('Fetching users from API...');

        $externalUsers = $this->api->fetchUsers();

        foreach ($externalUsers as $data) {
            $this->users->createOrUpdate($data);
            $output->line("Synced: {$data['email']}");
        }

        $output->success("Synced " . count($externalUsers) . " users");
        return 0;
    }
}
```

Register the class by its name—Verge resolves it through the container, injecting any dependencies:

```php
<?php

use App\Console\UserSyncCommand;

$app = new App();

$app->command('users:sync', UserSyncCommand::class);

return $app;
```

Constructor injection works exactly like it does for controllers and middleware.

## Resolving Dependencies in Commands

Commands can type-hint services from the container just like routes and middleware:

```php
<?php

namespace App\Console;

use Verge\App;
use Verge\Console\Output;
use Verge\Cache\Cache;
use Verge\Log\Logger;

class CacheStatsCommand
{
    public function __construct(
        private Cache $cache,
        private Logger $log
    ) {}

    public function __invoke(App $app, Output $output): int
    {
        $this->log->info('Generating cache statistics');

        // Use injected dependencies
        $stats = $this->cache->stats();

        $output->info('Cache Statistics:');
        $output->line("Hit rate: {$stats['hit_rate']}%");
        $output->line("Memory used: {$stats['memory_mb']} MB");

        return 0;
    }
}
```

## Running Commands from Code

Sometimes you need to trigger a command from within your application—maybe from a webhook or scheduled job. Execute commands programmatically by resolving them through the container:

```php
$app->post('/webhooks/sync', function(App $app, Output $output) {
    $commands = $app->getCommands();
    $handler = $app->make($commands['users:sync']);

    $exitCode = $handler($app, $output);

    return json(['success' => $exitCode === 0]);
});
```

## Grouping Related Commands

Use consistent namespaces to keep commands organized:

```php
$app->command('cache:warm', CacheWarmCommand::class);
$app->command('cache:clear', CacheClearCommand::class);
$app->command('cache:stats', CacheStatsCommand::class);

$app->command('users:sync', UserSyncCommand::class);
$app->command('users:cleanup', UserCleanupCommand::class);
```

When you run `./bin/verge` with no arguments, commands are grouped by namespace automatically.

## Handling Command Errors

Commands should catch exceptions and return appropriate exit codes:

```php
class DataImportCommand
{
    public function __invoke(App $app, Output $output): int
    {
        try {
            $output->info('Starting import...');
            $this->import();
            $output->success('Import complete');
            return 0;
        } catch (Exception $e) {
            $output->error("Import failed: " . $e->getMessage());
            return 1;
        }
    }
}
```

Non-zero exit codes tell the shell and CI tools that something went wrong.

## Showing Progress for Long Operations

Keep users informed during long-running commands:

```php
$app->command('posts:migrate', function(App $app, Output $output) {
    $posts = fetchAllPosts();
    $total = count($posts);

    $output->info("Migrating {$total} posts...");

    foreach ($posts as $i => $post) {
        migratePost($post);

        if ($i % 100 === 0) {
            $output->line("Processed {$i}/{$total}");
        }
    }

    $output->success("Migration complete");
    return 0;
});
```

## Testing Commands

Test commands like any other class by injecting mocked dependencies:

```php
use App\Console\UserSyncCommand;
use Mockery;

it('syncs users from external API', function() {
    $users = Mockery::mock(UserRepository::class);
    $api = Mockery::mock(ExternalAPI::class);

    $api->shouldReceive('fetchUsers')->andReturn([
        ['email' => 'test@example.com']
    ]);

    $users->shouldReceive('createOrUpdate')->once();

    $command = new UserSyncCommand($users, $api);
    $output = new Output(new NullOutput());

    $exitCode = $command(app(), $output);

    expect($exitCode)->toBe(0);
});
```

## Common Command Patterns

Most commands fall into a few categories. Data cleanup commands run periodically:

```php
$app->command('sessions:clean', function(App $app, Output $output) {
    $deleted = deleteExpiredSessions();
    $output->success("Deleted {$deleted} expired sessions");
    return 0;
});
```

Reporting commands generate summaries:

```php
$app->command('stats:daily', function(App $app, Output $output) {
    $stats = generateDailyStats();

    $output->table(
        ['Metric', 'Value'],
        [
            ['Users', $stats['users']],
            ['Posts', $stats['posts']],
            ['Revenue', '$' . number_format($stats['revenue'], 2)]
        ]
    );

    return 0;
});
```

Maintenance commands prepare your application for deployment:

```php
$app->command('deploy:prepare', function(App $app, Output $output) {
    $output->info('Warming caches...');
    warmCaches();

    $output->info('Optimizing assets...');
    optimizeAssets();

    $output->success('Deployment preparation complete');
    return 0;
});
```
