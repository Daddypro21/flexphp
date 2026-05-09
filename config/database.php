<?php

declare(strict_types=1);

/**
 * Database configuration.
 * Supports MySQL, PostgreSQL, and SQLite via Cycle ORM.
 */
return [
    'default' => $_ENV['DB_DRIVER'] ?? 'mysql',

    'connections' => [
        'mysql' => [
            'driver'   => 'mysql',
            'host'     => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port'     => (int) ($_ENV['DB_PORT'] ?? 3306),
            'database' => $_ENV['DB_DATABASE'] ?? 'flexphp',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset'  => 'utf8mb4',
        ],

        'pgsql' => [
            'driver'   => 'postgres',
            'host'     => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port'     => (int) ($_ENV['DB_PORT'] ?? 5432),
            'database' => $_ENV['DB_DATABASE'] ?? 'flexphp',
            'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
        ],

        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => $_ENV['DB_DATABASE'] ?? dirname(__DIR__) . '/database/database.sqlite',
        ],
    ],

    // Directory where migration files are stored
    'migrations_path' => dirname(__DIR__) . '/database/migrations',

    // Entity directories scanned by Cycle ORM annotated schema builder
    'entity_dirs' => [
        dirname(__DIR__) . '/app/Models',
    ],
];
