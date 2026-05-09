# FlexPHP Framework

## Project Overview

FlexPHP is a lightweight, async-capable PHP framework that follows all PSR standards. It provides a full-featured web framework (routing, DI container, ORM, templating, CLI) with a unique async system that lets developers selectively make any part of the UI work without full page reloads.

## Architecture

### Directory Structure

```
flexphp/
├── app/                        # Application code (user-land)
│   ├── Controllers/            # HTTP controllers
│   ├── Models/                 # Cycle ORM entity classes
│   ├── Middleware/             # Custom PSR-15 middleware
│   └── Providers/              # Application service providers
├── bootstrap/
│   └── app.php                 # Application bootstrapper (entry point)
├── config/
│   ├── app.php                 # Core app settings, providers list
│   ├── database.php            # DBAL / ORM configuration
│   ├── commands.php            # Custom CLI command registration
│   ├── logging.php             # PSR-3 logger configuration
│   └── view.php                # Template engine settings
├── database/
│   ├── migrations/             # Migration files (managed by `php flex migrate`)
│   └── seeders/                # Database seeders
├── docs/                       # Framework documentation
│   ├── getting-started.md
│   ├── async.md
│   ├── orm.md
│   └── cli.md
├── public/
│   ├── index.php               # Front controller
│   └── js/
│       └── flex.js             # Async JS library (~2 kb)
├── routes/
│   ├── web.php                 # Web (HTML) routes
│   └── api.php                 # API routes (JSON, no session)
├── src/                        # Framework core (FlexPHP\ namespace)
│   ├── Async/                  # Server-side async helpers
│   │   └── AsyncResponse.php
│   ├── Console/                # CLI foundation
│   │   ├── Application.php     # Symfony Console application wrapper
│   │   ├── BaseCommand.php     # Abstract command every command extends
│   │   └── Commands/           # Built-in flex commands
│   ├── Core/
│   │   ├── Application.php     # Bootstrap + HTTP lifecycle orchestrator
│   │   ├── Container.php       # PSR-11 DI container with auto-wiring
│   │   ├── Config.php          # Dot-notation config reader
│   │   └── ServiceProvider.php # Base class for all service providers
│   ├── Database/
│   │   ├── DatabaseManager.php         # Cycle DBAL + ORM factory
│   │   ├── BaseRepository.php          # Generic CRUD repository
│   │   └── DatabaseServiceProvider.php # Registers DB services
│   ├── Events/
│   │   ├── EventDispatcher.php # PSR-14 event dispatcher
│   │   └── ListenerProvider.php
│   ├── Http/
│   │   ├── Router.php          # FastRoute-powered HTTP router
│   │   ├── Request.php         # PSR-7 server request wrapper
│   │   ├── Response.php        # PSR-7 response wrapper
│   │   ├── JsonResponse.php    # Convenience JSON response
│   │   ├── RedirectResponse.php
│   │   └── Middleware/
│   │       └── MiddlewarePipeline.php  # PSR-15 middleware runner
│   ├── Log/
│   │   └── Logger.php          # PSR-3 Monolog wrapper
│   └── View/
│       ├── ViewEngine.php      # PHP template engine (layouts + sections)
│       └── ViewServiceProvider.php
├── storage/
│   ├── cache/
│   │   └── views/              # Compiled view cache
│   └── logs/                   # Application log files
├── tests/
│   ├── Unit/                   # PHPUnit unit tests
│   └── Feature/                # Integration / feature tests
├── views/                      # PHP template files
│   ├── layouts/
│   │   └── app.php             # Default layout
│   └── errors/
│       ├── 404.php
│       └── 500.php
├── .env.example                # Environment variable template
├── composer.json
├── flex                        # CLI entry point  (`php flex <command>`)
├── phpunit.xml
└── CLAUDE.md                   # This file
```

### Key Components

- **Container** (`src/Core/Container.php`): PSR-11 DI container with auto-wiring
- **Application** (`src/Core/Application.php`): Bootstrap and HTTP lifecycle orchestrator
- **Router** (`src/Http/Router.php`): Fast-route-powered HTTP router
- **Request/Response** (`src/Http/`): PSR-7 HTTP message wrappers
- **ViewEngine** (`src/View/ViewEngine.php`): PHP template engine with layout/section system
- **EventDispatcher** (`src/Events/EventDispatcher.php`): PSR-14 event system
- **Cycle ORM** (`src/Database/`): Database layer via Cycle ORM 2.x
- **Console** (`src/Console/`): CLI application (`php flex <command>`)
- **Async System** (`public/js/flex.js` + `src/Async/`): Selective async via HTML attributes

## Development Commands

- `composer install` — install dependencies
- `php flex serve` — start development server on localhost:8000
- `php flex make:controller UserController` — generate a controller
- `php flex make:controller UserController --resource` — generate CRUD controller
- `php flex make:model Post` — generate a Cycle ORM entity
- `php flex make:migration create_posts_table` — generate migration file
- `php flex migrate` — run pending migrations
- `php flex migrate --rollback` — roll back last batch
- `php flex route:list` — list all routes

## PSR Standards

- PSR-1, PSR-12: Coding style
- PSR-3: Logger (`FlexPHP\Log\Logger`)
- PSR-4: Autoloading (namespace `FlexPHP\` → `src/`, `App\` → `app/`)
- PSR-7: HTTP Messages (Request, Response)
- PSR-11: Container interface
- PSR-14: Event Dispatcher
- PSR-15: HTTP Middleware

## Async System

The async system consists of two parts:
1. `public/js/flex.js` — client-side library (~2kb) that intercepts clicks/submits and uses fetch()
2. Server-side: `Request::isAsyncRequest()` detects async requests, `ViewEngine` returns fragments

### HTML Attributes

- `flex-async` — marks element as async-capable
- `flex-target="#selector"` — where to inject response
- `flex-swap="innerHTML|outerHTML|append|prepend"` — injection method
- `flex-trigger="click|submit|load|hover"` — when to fire
- `flex-method="GET|POST"` — HTTP method override
- `flex-loading="#selector"` — loading indicator element

## Adding New Routes

Edit `routes/web.php`. Available methods: `get`, `post`, `put`, `patch`, `delete`, `any`.

```php
// routes/web.php
use FlexPHP\Http\Router;

$router->get('/', [HomeController::class, 'index']);
$router->get('/users/{id}', [UserController::class, 'show'])->name('users.show');

$router->group(['prefix' => '/admin', 'middleware' => ['auth']], function (Router $r) {
    $r->get('/dashboard', [AdminController::class, 'dashboard']);
});
```

## Service Providers

Register services in `config/app.php` under `providers`. Each provider implements `register()` and optionally `boot()`.

```php
// src/Core/ServiceProvider.php — base class
abstract class ServiceProvider {
    abstract public function register(): void;
    public function boot(): void {}
}
```

## Environment Variables

Copy `.env.example` to `.env` and configure. All `$_ENV` values are loaded via `vlucas/phpdotenv`.

```dotenv
APP_NAME=FlexPHP
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=flexphp
DB_USERNAME=root
DB_PASSWORD=

LOG_CHANNEL=file
LOG_LEVEL=debug
```

## Testing

Tests live in `tests/Unit/` and `tests/Feature/`. Run the full suite:

```bash
./vendor/bin/phpunit
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite Feature
./vendor/bin/phpunit --filter ContainerTest
```

## Coding Standards

All PHP files must:
- Start with `<?php` + `declare(strict_types=1);`
- Use PSR-12 formatting
- Target PHP 8.1+ syntax (enums, readonly, intersection types, fibers where needed)
- Include DocBlocks on all public and protected members
- Use constructor property promotion where appropriate
