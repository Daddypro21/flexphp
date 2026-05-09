<?php

declare(strict_types=1);

/**
 * Console command registry.
 * Add your custom commands here.
 */
return [
    'make:controller' => FlexPHP\Console\Commands\MakeControllerCommand::class,
    'make:model'      => FlexPHP\Console\Commands\MakeModelCommand::class,
    'make:migration'  => FlexPHP\Console\Commands\MakeMigrationCommand::class,
    'migrate'         => FlexPHP\Console\Commands\MigrateCommand::class,
    'serve'           => FlexPHP\Console\Commands\ServeCommand::class,
    'route:list'      => FlexPHP\Console\Commands\RouteListCommand::class,
];
