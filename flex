#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * FlexPHP CLI entry point.
 * Usage: php flex <command> [arguments] [options]
 */

// Register Composer autoloader
$autoloader = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    echo "\033[31mError: Composer autoloader not found. Run: composer install\033[0m\n";
    exit(1);
}
require_once $autoloader;

// Boot the application (without HTTP handling)
$app = new FlexPHP\Core\Application(__DIR__);
$app->bootstrap();

// Build and run the console application
$console = new FlexPHP\Console\Application();
$console->setApp($app);

$console->register('make:controller', FlexPHP\Console\Commands\MakeControllerCommand::class);
$console->register('make:model',      FlexPHP\Console\Commands\MakeModelCommand::class);
$console->register('make:migration',  FlexPHP\Console\Commands\MakeMigrationCommand::class);
$console->register('migrate',         FlexPHP\Console\Commands\MigrateCommand::class);
$console->register('serve',           FlexPHP\Console\Commands\ServeCommand::class);
$console->register('route:list',      FlexPHP\Console\Commands\RouteListCommand::class);

exit($console->run($argv));
