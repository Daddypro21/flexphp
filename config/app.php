<?php

declare(strict_types=1);

/**
 * Application configuration.
 */
return [
    'name'     => $_ENV['APP_NAME'] ?? 'FlexPHP App',
    'env'      => $_ENV['APP_ENV'] ?? 'production',
    'debug'    => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url'      => $_ENV['APP_URL'] ?? 'http://localhost:8000',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',
    'locale'   => $_ENV['APP_LOCALE'] ?? 'en',

    // Service providers loaded on every request
    'providers' => [
        FlexPHP\Database\DatabaseServiceProvider::class,
        FlexPHP\Log\LogServiceProvider::class,
        FlexPHP\View\ViewServiceProvider::class,
        FlexPHP\Events\EventServiceProvider::class,
    ],

    // Middleware applied globally to every HTTP request
    'middleware' => [
        FlexPHP\Http\Middleware\CsrfMiddleware::class,
    ],
];
