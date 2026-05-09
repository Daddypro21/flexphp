<?php

declare(strict_types=1);

namespace FlexPHP\Log;

use FlexPHP\Core\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Registers the PSR-3 Logger as a singleton in the container.
 */
class LogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoggerInterface::class, function () {
            $basePath = $this->app->getBasePath();
            $level    = $this->app->getConfig()->get('app.log_level', 'debug');
            return new Logger($basePath . '/storage/logs/app.log', $level);
        });

        // Also alias the concrete class
        $this->app->singleton(Logger::class, fn() => $this->app->make(LoggerInterface::class));
    }
}
