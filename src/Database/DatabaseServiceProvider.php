<?php

declare(strict_types=1);

namespace FlexPHP\Database;

use FlexPHP\Core\ServiceProvider;

/**
 * Registers the DatabaseManager and Cycle ORM in the DI container.
 */
class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DatabaseManager::class, function () {
            $config = $this->app->getConfig()->get('database');
            return new DatabaseManager($config);
        });
    }
}
