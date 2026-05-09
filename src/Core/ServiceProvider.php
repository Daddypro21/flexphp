<?php

declare(strict_types=1);

namespace FlexPHP\Core;

/**
 * Base class for all service providers.
 * Service providers are the central place to configure and bootstrap
 * framework components and application services.
 */
abstract class ServiceProvider
{
    public function __construct(protected Application $app)
    {
    }

    /**
     * Register bindings into the container.
     * This method is called before boot().
     */
    abstract public function register(): void;

    /**
     * Bootstrap any application services.
     * This method is called after all providers are registered.
     */
    public function boot(): void
    {
        // Override in subclasses if needed
    }
}
