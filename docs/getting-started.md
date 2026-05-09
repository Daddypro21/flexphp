# Getting Started with FlexPHP

This guide takes you from zero to a running application in under 15 minutes.

---

## 1. Requirements

| Requirement | Minimum version |
|-------------|----------------|
| PHP         | 8.1            |
| Composer    | 2.x            |
| Database    | MySQL 8+, PostgreSQL 14+, or SQLite 3 |
| Web server  | Apache 2.4+, Nginx 1.18+, or the built-in dev server |

Verify your PHP version:

```bash
php -v
# PHP 8.1.x (cli)
```

---

## 2. Installation

### Clone the repository

```bash
git clone https://github.com/your-org/flexphp.git my-app
cd my-app
```

### Install Composer dependencies

```bash
composer install
```

### Set up environment variables

```bash
cp .env.example .env
```

Open `.env` and update the values for your environment:

```dotenv
APP_NAME=MyApp
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_app
DB_USERNAME=root
DB_PASSWORD=secret
```

### Set directory permissions (Linux / macOS)

```bash
chmod -R 775 storage/
chmod -R 775 database/migrations/
```

---

## 3. Running the Development Server

```bash
php flex serve
# Server running at http://localhost:8000
```

To use a different host or port:

```bash
php flex serve --host=0.0.0.0 --port=9000
```

Open `http://localhost:8000` in your browser. You should see the FlexPHP welcome page.

---

## 4. Your First Route

Routes are registered in `routes/web.php`. Open that file and add:

```php
<?php
// routes/web.php

use FlexPHP\Http\Request;
use FlexPHP\Http\Response;

// Simple closure route
$router->get('/hello', function (Request $request) {
    return new Response('<h1>Hello from FlexPHP!</h1>', 200);
});

// Route with a URI parameter
$router->get('/hello/{name}', function (Request $request, string $name) {
    return new Response("<h1>Hello, {$name}!</h1>", 200);
});
```

Visit `http://localhost:8000/hello` and `http://localhost:8000/hello/World` to see them in action.

### Available route methods

```php
$router->get('/path',    $handler);
$router->post('/path',   $handler);
$router->put('/path',    $handler);
$router->patch('/path',  $handler);
$router->delete('/path', $handler);
$router->any('/path',    $handler); // matches all methods
```

---

## 5. Your First Controller

Generate a controller using the CLI:

```bash
php flex make:controller PageController
# Created: app/Controllers/PageController.php
```

For a full CRUD resource controller:

```bash
php flex make:controller PostController --resource
# Created: app/Controllers/PostController.php (with index, show, create, store, edit, update, destroy)
```

The generated controller looks like this:

```php
<?php
// app/Controllers/PageController.php

declare(strict_types=1);

namespace App\Controllers;

use FlexPHP\Http\Request;
use FlexPHP\Http\Response;

class PageController
{
    public function index(Request $request): Response
    {
        return new Response('Hello from PageController!', 200);
    }
}
```

Register it in `routes/web.php`:

```php
use App\Controllers\PageController;

$router->get('/page', [PageController::class, 'index']);
```

---

## 6. Returning Responses

### Plain HTML response

```php
return new Response('<p>Hello!</p>', 200);
```

### JSON response

```php
use FlexPHP\Http\JsonResponse;

return new JsonResponse(['status' => 'ok', 'user' => $user], 200);

// Shorthand helper available inside controllers:
return $this->json(['status' => 'ok'], 200);
```

### Redirect response

```php
use FlexPHP\Http\RedirectResponse;

return new RedirectResponse('/dashboard', 302);

// Named route redirect:
return new RedirectResponse($router->route('dashboard'), 302);
```

### View response

```php
return $this->view('pages.home', ['title' => 'Welcome']);
```

---

## 7. Your First View Template

### Layout file

Create `views/layouts/app.php`:

```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $this->section('title', 'FlexPHP App') ?></title>
</head>
<body>
    <nav><!-- navigation --></nav>

    <main>
        <?= $this->section('content') ?>
    </main>

    <footer>FlexPHP <?= date('Y') ?></footer>
</body>
</html>
```

### Child view

Create `views/pages/home.php`:

```php
<?php $this->extends('layouts.app') ?>

<?php $this->startSection('title') ?>
    Home — <?= htmlspecialchars($title) ?>
<?php $this->endSection() ?>

<?php $this->startSection('content') ?>
    <h1>Welcome to <?= htmlspecialchars($title) ?>!</h1>
    <p>You are running FlexPHP.</p>
<?php $this->endSection() ?>
```

### Render from a controller

```php
public function home(Request $request): Response
{
    return $this->view('pages.home', [
        'title' => 'My App',
    ]);
}
```

### Template helpers

```php
// Escape output
<?= $this->e($userInput) ?>

// Include a partial
<?= $this->include('partials.navbar') ?>

// Check if a section has content
<?php if ($this->hasSection('sidebar')): ?>
    <aside><?= $this->section('sidebar') ?></aside>
<?php endif ?>
```

---

## 8. Database Setup

### Configure the connection

In `.env`:

```dotenv
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_app
DB_USERNAME=root
DB_PASSWORD=secret
```

The full driver configuration is in `config/database.php`.

### Create the database

```bash
mysql -u root -p -e "CREATE DATABASE my_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Generate a migration

```bash
php flex make:migration create_users_table
# Created: database/migrations/2024_01_15_120000_create_users_table.php
```

Edit the generated file:

```php
<?php

use Cycle\Migrations\Migration;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->table('users')
            ->addColumn('id',         'primary')
            ->addColumn('name',       'string',   ['nullable' => false])
            ->addColumn('email',      'string',   ['nullable' => false])
            ->addColumn('password',   'string',   ['nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->addIndex(['email'], ['unique' => true])
            ->create();
    }

    public function down(): void
    {
        $this->table('users')->drop();
    }
}
```

### Run migrations

```bash
php flex migrate
# Migrated: 2024_01_15_120000_create_users_table
```

### Roll back

```bash
php flex migrate --rollback
```

---

## 9. Your First Model

Generate a Cycle ORM entity:

```bash
php flex make:model Post
# Created: app/Models/Post.php
```

The generated entity uses PHP 8 attributes:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;

#[Entity(table: 'posts')]
class Post
{
    #[Column(type: 'primary')]
    private int $id;

    #[Column(type: 'string')]
    private string $title;

    #[Column(type: 'text')]
    private string $body;

    #[Column(type: 'datetime')]
    private \DateTimeImmutable $createdAt;

    // getters / setters …
}
```

### Create a repository

```bash
php flex make:repository PostRepository
# Created: app/Repositories/PostRepository.php
```

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Post;
use FlexPHP\Database\BaseRepository;

/** @extends BaseRepository<Post> */
class PostRepository extends BaseRepository
{
    protected string $entityClass = Post::class;

    /** Find the most recent N posts. */
    public function findLatest(int $limit = 10): array
    {
        return $this->select()
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->fetchAll();
    }
}
```

Use it in a controller:

```php
public function index(Request $request, PostRepository $repo): Response
{
    $posts = $repo->findLatest(10);
    return $this->view('posts.index', compact('posts'));
}
```

---

## 10. Async Example

Make a list of posts load asynchronously without writing any JavaScript.

### The route (handles both normal and async requests)

```php
$router->get('/posts', [PostController::class, 'index'])->name('posts.index');
```

### The controller

```php
public function index(Request $request, PostRepository $repo): Response
{
    $posts = $repo->findAll();

    // On async requests the view engine returns only the targeted fragment.
    return $this->view('posts.index', compact('posts'));
}
```

### The view

```html
<!-- views/posts/index.php -->
<?php $this->extends('layouts.app') ?>

<?php $this->startSection('content') ?>

<!-- This button triggers an async load of the list fragment -->
<button
    flex-async
    flex-target="#post-list"
    flex-swap="innerHTML"
    flex-trigger="click"
    flex-method="GET"
    data-url="/posts?fragment=list">
    Reload list
</button>

<!-- The container that will be replaced -->
<div id="post-list" flex-async flex-trigger="load" flex-target="#post-list"
     flex-swap="innerHTML" data-url="/posts?fragment=list">
    <?php foreach ($posts as $post): ?>
        <article>
            <h2><?= $this->e($post->getTitle()) ?></h2>
        </article>
    <?php endforeach ?>
</div>

<?php $this->endSection() ?>
```

The server detects the `X-Flex-Async` header and returns only the `#post-list` fragment, not the full layout. No extra endpoints needed.

---

## 11. CLI Commands Overview

```
php flex serve                          Start the built-in development server
php flex route:list                     List all registered routes
php flex make:controller <Name>         Generate a controller
php flex make:controller <Name> --resource  Generate a resource controller
php flex make:model <Name>              Generate a Cycle ORM entity
php flex make:migration <name>          Generate a migration file
php flex make:repository <Name>         Generate a repository class
php flex make:command <Name>            Generate a custom CLI command
php flex migrate                        Run pending migrations
php flex migrate --rollback             Rollback last migration batch
php flex migrate --status               Show migration status
php flex db:seed                        Run all database seeders
php flex cache:clear                    Clear view + application cache
php flex config:cache                   Cache the merged configuration
```

For detailed documentation on each command, see [docs/cli.md](cli.md).
