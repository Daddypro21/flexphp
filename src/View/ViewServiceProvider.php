<?php

declare(strict_types=1);

namespace FlexPHP\View;

use FlexPHP\Core\ServiceProvider;
use FlexPHP\Http\Router;

/**
 * Registers the Twig-backed ViewEngine as a singleton in the DI container.
 */
class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ViewEngine::class, function () {
            $basePath  = $this->app->getBasePath();
            $debug     = (bool) $this->app->getConfig()->get('app.debug', false);
            $viewsPath = $basePath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views';
            $cachePath = $debug ? '' : $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'views';

            $engine = new ViewEngine($viewsPath, $cachePath, $debug);

            // Wire the router's url() method into Twig {{ url() }}
            $router = $this->app->make(Router::class);
            $engine->setUrlResolver(fn(string $name, array $params = []) => $router->url($name, $params));

            return $engine;
        });
    }
}
