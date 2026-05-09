<?php

declare(strict_types=1);

namespace FlexPHP\View;

use FlexPHP\Core\ServiceProvider;

/**
 * Registers the ViewEngine as a singleton in the service container.
 */
class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ViewEngine::class, function () {
            $basePath  = $this->app->getBasePath();
            $viewsPath = $basePath . '/app/Views';
            $cachePath = $basePath . '/storage/cache/views';
            return new ViewEngine($viewsPath, $cachePath);
        });
    }
}
