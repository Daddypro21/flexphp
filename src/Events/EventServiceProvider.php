<?php

declare(strict_types=1);

namespace FlexPHP\Events;

use FlexPHP\Core\ServiceProvider;

/**
 * Registers the EventDispatcher singleton in the container.
 */
class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EventDispatcher::class, fn() => new EventDispatcher());
    }
}
