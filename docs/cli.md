# CLI Commands Reference

FlexPHP ships with a rich command-line interface. The CLI entry point is the `flex` file in the project root:

```bash
php flex <command> [arguments] [--options]
```

Run `php flex list` to see all available commands, or `php flex help <command>` for detailed usage of any single command.

---

## Built-in Commands

### `serve` — Development server

Start the PHP built-in web server on a configurable host and port.

```
php flex serve [--host=<host>] [--port=<port>]
```

| Option    | Default       | Description                  |
|-----------|---------------|------------------------------|
| `--host`  | `127.0.0.1`   | IP address to listen on      |
| `--port`  | `8000`        | TCP port                     |

**Examples**

```bash
php flex serve
# Server running at http://127.0.0.1:8000

php flex serve --host=0.0.0.0 --port=9000
# Server running at http://0.0.0.0:9000 (accessible on the network)
```

---

### `route:list` — List registered routes

Displays all routes registered in `routes/web.php` and `routes/api.php` as a formatted table.

```
php flex route:list [--method=<METHOD>] [--name=<pattern>]
```

| Option     | Description                                      |
|------------|--------------------------------------------------|
| `--method` | Filter by HTTP method (GET, POST, etc.)          |
| `--name`   | Filter route names by substring                  |

**Output**

```
+--------+-------------------------+----------------------+----------+
| Method | URI                     | Action               | Name     |
+--------+-------------------------+----------------------+----------+
| GET    | /                       | HomeController@index | home     |
| GET    | /users                  | UserController@index | users.index |
| GET    | /users/{id}             | UserController@show  | users.show  |
| POST   | /users                  | UserController@store | users.store |
| PUT    | /users/{id}             | UserController@update| users.update|
| DELETE | /users/{id}             | UserController@destroy| users.destroy |
+--------+-------------------------+----------------------+----------+
```

---

### `make:controller` — Generate a controller

Creates a new controller class in `app/Controllers/`.

```
php flex make:controller <Name> [--resource] [--api]
```

| Argument / Option | Description                                                       |
|-------------------|-------------------------------------------------------------------|
| `Name`            | Controller class name (e.g. `UserController`)                     |
| `--resource`      | Generate a full CRUD resource controller (7 methods)             |
| `--api`           | Like `--resource` but without `create()` and `edit()` (view forms)|

**Examples**

```bash
# Minimal controller
php flex make:controller PageController
# → app/Controllers/PageController.php

# Full resource controller
php flex make:controller PostController --resource
# → app/Controllers/PostController.php  (index, show, create, store, edit, update, destroy)

# API resource controller (no HTML form methods)
php flex make:controller ApiPostController --api
# → app/Controllers/ApiPostController.php  (index, show, store, update, destroy)
```

**Generated resource controller skeleton**

```php
<?php
declare(strict_types=1);
namespace App\Controllers;

use FlexPHP\Http\Request;
use FlexPHP\Http\Response;

class PostController
{
    public function index(Request $request): Response  { /* list  */ }
    public function show(Request $request, int $id): Response  { /* detail */ }
    public function create(Request $request): Response { /* show form */ }
    public function store(Request $request): Response  { /* handle POST */ }
    public function edit(Request $request, int $id): Response  { /* edit form */ }
    public function update(Request $request, int $id): Response { /* handle PUT */ }
    public function destroy(Request $request, int $id): Response { /* delete */ }
}
```

---

### `make:model` — Generate a Cycle ORM entity

Creates a new Cycle ORM entity class in `app/Models/`.

```
php flex make:model <Name> [--migration]
```

| Argument / Option | Description                                                |
|-------------------|------------------------------------------------------------|
| `Name`            | Entity class name (e.g. `Post`)                            |
| `--migration`     | Also generate the corresponding migration file             |

**Examples**

```bash
php flex make:model Post
# → app/Models/Post.php

php flex make:model Comment --migration
# → app/Models/Comment.php
# → database/migrations/2024_01_15_create_comments_table.php
```

---

### `make:migration` — Generate a migration file

Creates a timestamped migration skeleton in `database/migrations/`.

```
php flex make:migration <name>
```

**Examples**

```bash
php flex make:migration create_posts_table
# → database/migrations/2024_01_15_143022_create_posts_table.php

php flex make:migration add_published_to_posts
# → database/migrations/2024_01_15_143100_add_published_to_posts.php
```

---

### `make:repository` — Generate a repository class

Creates a typed repository extending `BaseRepository` in `app/Repositories/`.

```
php flex make:repository <Name> [--model=<ModelClass>]
```

| Option    | Description                                     |
|-----------|-------------------------------------------------|
| `--model` | Model class the repository manages              |

**Example**

```bash
php flex make:repository PostRepository --model=Post
# → app/Repositories/PostRepository.php
```

---

### `make:command` — Generate a custom CLI command

Creates a custom command class in `app/Console/Commands/`.

```
php flex make:command <Name> [--signature=<signature>]
```

**Example**

```bash
php flex make:command SendNewsletterCommand --signature="newsletter:send {--queue}"
# → app/Console/Commands/SendNewsletterCommand.php
```

---

### `make:middleware` — Generate middleware

Creates a PSR-15 middleware in `app/Middleware/`.

```
php flex make:middleware <Name>
```

**Example**

```bash
php flex make:middleware AuthMiddleware
# → app/Middleware/AuthMiddleware.php
```

---

### `migrate` — Run database migrations

Runs all pending migrations in chronological order.

```
php flex migrate [--rollback] [--all] [--status] [--force]
```

| Option      | Description                                      |
|-------------|--------------------------------------------------|
| `--rollback`| Roll back the last batch of migrations           |
| `--all`     | Combined with `--rollback`: roll back everything |
| `--status`  | Show pending and executed migrations             |
| `--force`   | Run in production without confirmation prompt    |

**Examples**

```bash
php flex migrate
# [2024_01_15_143022] create_posts_table .......... done

php flex migrate --status
# Executed: create_users_table
# Pending:  create_posts_table

php flex migrate --rollback
# Rolled back: create_posts_table

php flex migrate --rollback --all
# Rolled back: create_posts_table, create_users_table
```

---

### `db:seed` — Run database seeders

Runs the `DatabaseSeeder` (or a specific seeder class).

```
php flex db:seed [--class=<SeederClass>] [--force]
```

**Examples**

```bash
php flex db:seed
# Running: DatabaseSeeder

php flex db:seed --class=UserSeeder
# Running: UserSeeder
```

---

### `cache:clear` — Clear the application cache

Removes compiled views and any cached configuration or route files.

```
php flex cache:clear [--views] [--config] [--all]
```

| Option    | Description                      |
|-----------|----------------------------------|
| `--views` | Clear only the view cache        |
| `--config`| Clear only the config cache      |
| `--all`   | Clear everything (default)       |

**Example**

```bash
php flex cache:clear
# Cleared: views, config, routes
```

---

### `config:cache` — Cache the merged configuration

Merges all files in `config/` into a single cached file for faster boot times.

```
php flex config:cache
```

---

### `key:generate` — Generate an application key

Generates a 32-byte random key and writes it to `APP_KEY` in `.env`.

```
php flex key:generate [--show]
```

| Option   | Description                             |
|----------|-----------------------------------------|
| `--show` | Print the key without writing to .env   |

---

## Creating a Custom Command

### Step 1 — Generate the scaffold

```bash
php flex make:command SendNewsletterCommand --signature="newsletter:send {list : The mailing list} {--queue : Dispatch to queue instead of running synchronously}"
```

### Step 2 — Implement the command

```php
<?php
// app/Console/Commands/SendNewsletterCommand.php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\SubscriberRepository;
use App\Services\MailService;
use FlexPHP\Console\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Sends the newsletter to all subscribers of a given list.
 */
class SendNewsletterCommand extends BaseCommand
{
    protected static string $defaultName = 'newsletter:send';

    public function __construct(
        private SubscriberRepository $subscribers,
        private MailService $mail,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Send the newsletter to a mailing list.')
            ->addArgument('list',    self::ARGUMENT_REQUIRED, 'The mailing list slug.')
            ->addOption('queue',     null, self::OPTION_NONE, 'Dispatch to queue.');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $list   = $input->getArgument('list');
        $queued = $input->getOption('queue');

        $subscribers = $this->subscribers->findByList($list);
        $total       = count($subscribers);

        $output->writeln("<info>Sending newsletter to {$total} subscribers in list '{$list}'…</info>");

        $progress = $this->createProgressBar($output, $total);

        foreach ($subscribers as $subscriber) {
            if ($queued) {
                $this->mail->queue($subscriber);
            } else {
                $this->mail->send($subscriber);
            }
            $progress->advance();
        }

        $progress->finish();
        $output->writeln('');
        $output->writeln('<info>Done.</info>');

        return self::SUCCESS;
    }
}
```

### BaseCommand API

| Method                           | Description                                                |
|----------------------------------|------------------------------------------------------------|
| `handle(input, output): int`     | Main entry point — implement your logic here               |
| `info(string $msg)`              | Write a green info line                                    |
| `error(string $msg)`             | Write a red error line                                     |
| `warn(string $msg)`              | Write a yellow warning line                                |
| `line(string $msg)`              | Write a plain line                                         |
| `table(array $headers, array $rows)` | Render a formatted table                               |
| `ask(string $question): string`  | Prompt for input                                           |
| `confirm(string $question): bool`| Prompt yes/no                                              |
| `choice(string $q, array $opts)` | Prompt with a list of choices                              |
| `createProgressBar(output, max)` | Return a Symfony ProgressBar instance                      |
| `call(string $command, array $args)` | Run another command from within this one               |

### Return codes

| Constant          | Value | Meaning                  |
|-------------------|-------|--------------------------|
| `self::SUCCESS`   | `0`   | Command ran successfully |
| `self::FAILURE`   | `1`   | Command failed           |
| `self::INVALID`   | `2`   | Invalid usage / arguments|

---

## Registering a Custom Command

Add your command class to `config/commands.php`:

```php
<?php
// config/commands.php

return [
    // Built-in commands are registered automatically.
    // Add your custom commands here:
    \App\Console\Commands\SendNewsletterCommand::class,
    \App\Console\Commands\ImportUsersCommand::class,
    \App\Console\Commands\GenerateReportCommand::class,
];
```

The CLI application resolves each command through the DI container, so constructor dependencies are auto-wired.

### Verifying registration

```bash
php flex list
# You should see your command in the output:
#   newsletter
#     newsletter:send   Send the newsletter to a mailing list.
```

---

## Scheduling Commands (Cron)

You can schedule commands by calling them from your system cron, or use the built-in scheduler (if enabled):

```
# Run every minute via system cron
* * * * * cd /var/www/my-app && php flex schedule:run >> /dev/null 2>&1
```

Define the schedule in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('newsletter:send weekly')->weekly()->at('08:00');
    $schedule->command('cache:clear')->daily()->at('03:00');
}
```
