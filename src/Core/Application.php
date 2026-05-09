<?php

declare(strict_types=1);

namespace FlexPHP\Core;

use Closure;
use Dotenv\Dotenv;
use FlexPHP\Http\Middleware\MiddlewareStack;
use FlexPHP\Http\Request;
use FlexPHP\Http\Response;
use FlexPHP\Http\Router;
use FlexPHP\Routing\RouteScanner;
use Throwable;

/**
 * The FlexPHP Application — central orchestrator of the framework.
 *
 * Responsibilities:
 *   - Bootstrap the environment (.env), configuration, and service providers.
 *   - Own the DI Container, Config manager, and HTTP Router.
 *   - Run the HTTP lifecycle: Request → Middleware → Router → Response → send().
 *
 * Typical usage (public/index.php):
 *   $app = new Application(dirname(__DIR__));
 *   $app->bootstrap();
 *   $app->run();
 */
class Application
{
    /**
     * The dependency injection container.
     */
    protected Container $container;

    /**
     * The configuration manager.
     */
    protected Config $config;

    /**
     * The HTTP router.
     */
    protected Router $router;

    /**
     * Registered service provider instances.
     *
     * @var ServiceProvider[]
     */
    protected array $providers = [];

    /**
     * @param string $basePath Absolute path to the application root directory.
     */
    public function __construct(protected string $basePath)
    {
        $this->container = new Container();
        $this->config    = new Config($this->basePath . DIRECTORY_SEPARATOR . 'config');
        $this->router    = new Router($this->container);
    }

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    /**
     * Bootstrap the application.
     *
     * Execution order:
     *   1. Load environment variables (.env)
     *   2. Load configuration files
     *   3. Register core framework bindings in the container
     *   4. Instantiate and register service providers
     *   5. Call boot() on all providers
     *   6. Load application routes
     *
     * @throws Throwable If any bootstrap step fails.
     */
    public function bootstrap(): void
    {
        $this->loadEnvironment();
        $this->loadConfig();
        $this->registerCoreBindings();
        $this->registerProviders();
        $this->bootProviders();
        $this->loadRoutes();
    }

    // -------------------------------------------------------------------------
    // HTTP lifecycle
    // -------------------------------------------------------------------------

    /**
     * Handle the current HTTP request and send the response to the client.
     *
     * The request travels through:
     *   - The global middleware stack (defined in config/app.php)
     *   - The router (which dispatches to the matched controller/closure)
     *
     * Any uncaught Throwable is converted to a 500 Internal Server Error
     * response in non-debug mode, or re-thrown in debug mode.
     */
    public function run(): void
    {
        try {
            $request = Request::fromGlobals();
            $this->container->instance(Request::class, $request);
            $stack   = $this->buildMiddlewareStack();

            $response = $stack->handle(
                $request,
                fn(Request $req): Response => $this->router->dispatch($req)
            );
        } catch (Throwable $e) {
            $response = $this->handleException($e);
        }

        $response->send();
    }

    // -------------------------------------------------------------------------
    // Container proxy methods
    // -------------------------------------------------------------------------

    /**
     * Register a transient binding in the container.
     *
     * @param string         $abstract The abstract type / identifier.
     * @param Closure|string $concrete Factory closure or concrete class name.
     */
    public function bind(string $abstract, Closure|string $concrete): void
    {
        $this->container->bind($abstract, $concrete);
    }

    /**
     * Register a singleton binding in the container.
     *
     * @param string         $abstract The abstract type / identifier.
     * @param Closure|string $concrete Factory closure or concrete class name.
     */
    public function singleton(string $abstract, Closure|string $concrete): void
    {
        $this->container->singleton($abstract, $concrete);
    }

    /**
     * Resolve an abstract type from the container.
     *
     * @param string               $abstract The abstract type / identifier.
     * @param array<string, mixed> $params   Optional constructor parameter overrides.
     *
     * @return mixed The resolved instance.
     */
    public function make(string $abstract, array $params = []): mixed
    {
        return $this->container->make($abstract, $params);
    }

    // -------------------------------------------------------------------------
    // Provider management
    // -------------------------------------------------------------------------

    /**
     * Register a service provider with the application.
     *
     * Calls register() immediately. boot() is called after all providers
     * have been registered (see bootProviders()).
     *
     * @param ServiceProvider $provider The provider to register.
     */
    public function registerProvider(ServiceProvider $provider): void
    {
        $provider->register();
        $this->providers[] = $provider;
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Return the DI container instance.
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Return the Config manager instance.
     *
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Return the HTTP Router instance.
     *
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Return the absolute base path of the application.
     *
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    // -------------------------------------------------------------------------
    // Bootstrap steps
    // -------------------------------------------------------------------------

    /**
     * Load environment variables from .env into $_ENV / $_SERVER.
     * If no .env file exists the step is silently skipped.
     */
    protected function loadEnvironment(): void
    {
        $envFile = $this->basePath . DIRECTORY_SEPARATOR . '.env';

        if (!is_file($envFile)) {
            return;
        }

        $dotenv = Dotenv::createImmutable($this->basePath);
        $dotenv->safeLoad();
    }

    /**
     * Apply timezone and error-reporting settings from config.
     * Config files are lazy-loaded; this just primes the cache.
     */
    protected function loadConfig(): void
    {
        $timezone = $this->config->get('app.timezone', 'UTC');
        date_default_timezone_set((string) $timezone);

        $debug = (bool) $this->config->get('app.debug', false);

        if ($debug) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }
    }

    /**
     * Register framework-level singleton bindings so that first-party
     * classes can be resolved from the container by interface or class name.
     */
    protected function registerCoreBindings(): void
    {
        // Register the application itself.
        $this->container->instance(Application::class, $this);

        // Register core collaborators.
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Config::class, $this->config);
        $this->container->instance(Router::class, $this->router);
    }

    /**
     * Instantiate and register all service providers listed in config/app.php.
     */
    protected function registerProviders(): void
    {
        /** @var string[] $providerClasses */
        $providerClasses = (array) $this->config->get('app.providers', []);

        foreach ($providerClasses as $class) {
            if (!class_exists($class)) {
                // Skip providers whose packages are not installed yet.
                continue;
            }

            /** @var ServiceProvider $provider */
            $provider = new $class($this);
            $this->registerProvider($provider);
        }
    }

    /**
     * Call boot() on every registered service provider.
     * This happens after all providers have been registered so that they can
     * safely depend on each other's bindings.
     */
    protected function bootProviders(): void
    {
        foreach ($this->providers as $provider) {
            $provider->boot();
        }
    }

    /**
     * Load routes from web.php and then scan controllers for route attributes.
     *
     * Route attributes are scanned after the file-based routes so that any
     * explicit routes in web.php take precedence on name conflicts.
     */
    protected function loadRoutes(): void
    {
        // 1. File-based routes (routes/web.php)
        $routesFile = $this->basePath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';

        if (is_file($routesFile)) {
            $router = $this->router;
            require $routesFile;
        }

        // 2. Attribute-based routes (scanned from controller directories)
        $this->scanAttributeRoutes();
    }

    /**
     * Scan controller directories for PHP 8 route attributes and register them.
     *
     * Configure scanned directories in config/app.php under 'route_scan_paths':
     *   'route_scan_paths' => [
     *       ['dir' => __DIR__ . '/../app/Controllers', 'namespace' => 'App\\Controllers'],
     *   ]
     */
    protected function scanAttributeRoutes(): void
    {
        /** @var array<int, array{dir: string, namespace: string}> $paths */
        $paths = (array) $this->config->get('app.route_scan_paths', []);

        // Default: always scan app/Controllers
        if (empty($paths)) {
            $paths = [[
                'dir'       => $this->basePath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Controllers',
                'namespace' => 'App\\Controllers',
            ]];
        }

        foreach ($paths as $entry) {
            $scanner = new RouteScanner(
                $this->router,
                [$entry['dir']],
                rtrim($entry['namespace'], '\\'),
            );
            $scanner->scan();
        }
    }

    // -------------------------------------------------------------------------
    // Middleware & error handling
    // -------------------------------------------------------------------------

    /**
     * Build and return the global HTTP middleware stack.
     * Middleware class names are read from config/app.php under "middleware".
     *
     * @return MiddlewareStack The configured pipeline.
     */
    protected function buildMiddlewareStack(): MiddlewareStack
    {
        $stack = new MiddlewareStack($this->container);

        /** @var string[] $middlewareClasses */
        $middlewareClasses = (array) $this->config->get('app.middleware', []);

        foreach ($middlewareClasses as $class) {
            if (class_exists($class)) {
                $stack->add($class);
            }
        }

        return $stack;
    }

    /**
     * Convert an uncaught Throwable into an appropriate HTTP Response.
     *
     * In debug mode the full exception message and trace are shown.
     * In production mode a generic 500 message is returned.
     *
     * @param Throwable $e The unhandled exception.
     *
     * @return Response A 500 Internal Server Error response.
     */
    protected function handleException(Throwable $e): Response
    {
        $debug = (bool) $this->config->get('app.debug', false);

        if ($debug) {
            $message = sprintf(
                "<h1>500 Internal Server Error</h1><pre>%s\n\n%s</pre>",
                htmlspecialchars($e->getMessage()),
                htmlspecialchars($e->getTraceAsString())
            );
        } else {
            $message = '<h1>500 Internal Server Error</h1><p>Something went wrong.</p>';
        }

        return Response::html($message, 500);
    }
}
