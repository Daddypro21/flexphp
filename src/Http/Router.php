<?php

declare(strict_types=1);

namespace FlexPHP\Http;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use FlexPHP\Core\Container;
use RuntimeException;

use function FastRoute\simpleDispatcher;

/**
 * HTTP Router powered by nikic/fast-route.
 *
 * Supports closures and "Controller@method" handler strings, route groups
 * with an optional prefix and middleware, named routes, and URL generation.
 *
 * Usage:
 *   $router->get('/users', 'UserController@index', 'users.index');
 *   $router->post('/users', fn(Request $r) => Response::json([]), 'users.store');
 *   $router->group('/api/v1', function(Router $r) { ... }, ['auth']);
 *   $router->url('users.index');           // => '/users'
 *   $router->url('api.users.show', ['id' => 42]); // => '/api/users/42'
 */
class Router
{
    /**
     * All registered route definitions.
     *
     * Each entry:
     *   ['method' => string, 'pattern' => string, 'handler' => mixed,
     *    'name' => string|null, 'middleware' => string[]]
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $routes = [];

    /**
     * Named route lookup table: name => pattern (with group prefix applied).
     *
     * @var array<string, string>
     */
    protected array $namedRoutes = [];

    /**
     * Current group prefix accumulated during a group() call.
     */
    protected string $currentPrefix = '';

    /**
     * Middleware stack inherited from the current group.
     *
     * @var string[]
     */
    protected array $currentMiddleware = [];

    /**
     * The DI container used to instantiate controllers.
     */
    protected ?Container $container;

    /**
     * @param Container|null $container Optional DI container for controller resolution.
     */
    public function __construct(?Container $container = null)
    {
        $this->container = $container;
    }

    // -------------------------------------------------------------------------
    // Route registration helpers
    // -------------------------------------------------------------------------

    /**
     * Register a GET route.
     *
     * @param string                   $pattern    URI pattern (fast-route syntax).
     * @param callable|string          $handler    Closure or "Controller@method".
     * @param string|null              $name       Optional route name.
     * @param array<int, string>       $middleware Route-level middleware class names.
     */
    public function get(string $pattern, callable|string $handler, ?string $name = null, array $middleware = []): void
    {
        $this->addRoute('GET', $pattern, $handler, $name, $middleware);
    }

    /**
     * Register a POST route.
     *
     * @param string             $pattern    URI pattern.
     * @param callable|string    $handler    Closure or "Controller@method".
     * @param string|null        $name       Optional route name.
     * @param array<int, string> $middleware Route-level middleware.
     */
    public function post(string $pattern, callable|string $handler, ?string $name = null, array $middleware = []): void
    {
        $this->addRoute('POST', $pattern, $handler, $name, $middleware);
    }

    /**
     * Register a PUT route.
     *
     * @param string             $pattern    URI pattern.
     * @param callable|string    $handler    Closure or "Controller@method".
     * @param string|null        $name       Optional route name.
     * @param array<int, string> $middleware Route-level middleware.
     */
    public function put(string $pattern, callable|string $handler, ?string $name = null, array $middleware = []): void
    {
        $this->addRoute('PUT', $pattern, $handler, $name, $middleware);
    }

    /**
     * Register a PATCH route.
     *
     * @param string             $pattern    URI pattern.
     * @param callable|string    $handler    Closure or "Controller@method".
     * @param string|null        $name       Optional route name.
     * @param array<int, string> $middleware Route-level middleware.
     */
    public function patch(string $pattern, callable|string $handler, ?string $name = null, array $middleware = []): void
    {
        $this->addRoute('PATCH', $pattern, $handler, $name, $middleware);
    }

    /**
     * Register a DELETE route.
     *
     * @param string             $pattern    URI pattern.
     * @param callable|string    $handler    Closure or "Controller@method".
     * @param string|null        $name       Optional route name.
     * @param array<int, string> $middleware Route-level middleware.
     */
    public function delete(string $pattern, callable|string $handler, ?string $name = null, array $middleware = []): void
    {
        $this->addRoute('DELETE', $pattern, $handler, $name, $middleware);
    }

    /**
     * Register a route that matches any HTTP method.
     *
     * @param string             $pattern    URI pattern.
     * @param callable|string    $handler    Closure or "Controller@method".
     * @param string|null        $name       Optional route name.
     * @param array<int, string> $middleware Route-level middleware.
     */
    public function any(string $pattern, callable|string $handler, ?string $name = null, array $middleware = []): void
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'] as $method) {
            $this->addRoute($method, $pattern, $handler, $name, $middleware);
        }
    }

    // -------------------------------------------------------------------------
    // Route groups
    // -------------------------------------------------------------------------

    /**
     * Define a group of routes sharing a URI prefix and optional middleware.
     *
     * Groups can be nested; prefixes and middleware accumulate.
     *
     * @param string             $prefix     URI prefix added to all routes inside the group.
     * @param callable           $callback   Callback receiving $this Router as first argument.
     * @param array<int, string> $middleware Middleware applied to every route in this group.
     */
    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        // Save outer context.
        $previousPrefix     = $this->currentPrefix;
        $previousMiddleware = $this->currentMiddleware;

        // Apply group context.
        $this->currentPrefix     = $previousPrefix . $prefix;
        $this->currentMiddleware = array_merge($previousMiddleware, $middleware);

        // Register routes defined inside the callback.
        $callback($this);

        // Restore outer context.
        $this->currentPrefix     = $previousPrefix;
        $this->currentMiddleware = $previousMiddleware;
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    /**
     * Dispatch the incoming request to the matching route handler.
     *
     * Returns a Response produced by the handler, or a built-in error
     * response (404 Not Found / 405 Method Not Allowed).
     *
     * @param Request $request The incoming HTTP request.
     *
     * @return Response The response produced by the matched handler.
     */
    public function dispatch(Request $request): Response
    {
        $dispatcher = $this->buildDispatcher();

        $routeInfo = $dispatcher->dispatch(
            $request->getMethod(),
            $this->normalizePath($request->getPath())
        );

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                return Response::notFound('404 Not Found');

            case Dispatcher::METHOD_NOT_ALLOWED:
                $allowed = implode(', ', $routeInfo[1]);
                return new Response(
                    "405 Method Not Allowed. Allowed methods: {$allowed}",
                    405,
                    ['Allow' => $allowed]
                );

            case Dispatcher::FOUND:
                /** @var array{handler: callable|string, middleware: string[]} $routeData */
                $routeData = $routeInfo[1];
                $vars      = $routeInfo[2];

                return $this->callHandler($routeData['handler'], $request, $vars);
        }

        // Should never reach here.
        return Response::notFound();
    }

    // -------------------------------------------------------------------------
    // URL generation
    // -------------------------------------------------------------------------

    /**
     * Generate a URI for a named route.
     *
     * Placeholder values in the pattern (e.g. {id}, {slug}) are replaced
     * with the corresponding entries from $params. Remaining $params entries
     * are appended as a query string.
     *
     * @param string               $name   The route name.
     * @param array<string, mixed> $params Values to substitute into the pattern.
     *
     * @throws RuntimeException If no route with the given name is registered.
     *
     * @return string The generated URI.
     */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new RuntimeException("No route named '{$name}' found.");
        }

        $pattern = $this->namedRoutes[$name];
        $used    = [];

        // Replace {param} and {param:regex} placeholders.
        $uri = preg_replace_callback(
            '/\{(\w+)(?::[^}]+)?\}/',
            function (array $matches) use ($params, &$used): string {
                $key = $matches[1];
                $used[] = $key;
                return isset($params[$key]) ? (string) $params[$key] : $matches[0];
            },
            $pattern
        );

        // Append unused params as query string.
        $remaining = array_diff_key($params, array_flip($used));

        if (!empty($remaining)) {
            $uri .= '?' . http_build_query($remaining);
        }

        return $uri ?? $pattern;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Add a single route definition to the internal registry.
     *
     * @param string             $method     HTTP method (uppercase).
     * @param string             $pattern    URI pattern (relative, without group prefix).
     * @param callable|string    $handler    Closure or "Controller@method".
     * @param string|null        $name       Optional route name.
     * @param array<int, string> $middleware Route-specific middleware.
     */
    public function addRoute(
        string $method,
        string $pattern,
        callable|string $handler,
        ?string $name,
        array $middleware
    ): void {
        $fullPattern = $this->currentPrefix . $pattern;
        $fullPattern = $this->normalizePath($fullPattern);

        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $fullPattern,
            'handler'    => $handler,
            'name'       => $name,
            'middleware' => array_merge($this->currentMiddleware, $middleware),
        ];

        // Register the named route using only the first registration to avoid duplicates.
        if ($name !== null && !isset($this->namedRoutes[$name])) {
            $this->namedRoutes[$name] = $fullPattern;
        }
    }

    /**
     * Build a fast-route Dispatcher from the registered routes.
     *
     * @return Dispatcher The compiled dispatcher.
     */
    protected function buildDispatcher(): Dispatcher
    {
        $routes = $this->routes;

        return simpleDispatcher(function (RouteCollector $r) use ($routes): void {
            foreach ($routes as $route) {
                $r->addRoute($route['method'], $route['pattern'], [
                    'handler'    => $route['handler'],
                    'middleware' => $route['middleware'],
                ]);
            }
        });
    }

    /**
     * Call the matched route handler and return the Response.
     *
     * Supports:
     *   - Closure / callable — called directly with ($request, ...$vars)
     *   - "Controller@method" string — controller resolved via DI container or new instantiation
     *
     * @param callable|string      $handler The route handler.
     * @param Request              $request The current request.
     * @param array<string, mixed> $vars    Path variable values captured by fast-route.
     *
     * @throws RuntimeException If the handler format is invalid.
     *
     * @return Response The handler's response.
     */
    protected function callHandler(callable|string $handler, Request $request, array $vars): Response
    {
        if (is_callable($handler)) {
            return $handler($request, ...array_values($vars));
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$controllerClass, $method] = explode('@', $handler, 2);

            $controller = $this->container
                ? $this->container->make($controllerClass)
                : new $controllerClass();

            return $controller->{$method}($request, ...array_values($vars));
        }

        throw new RuntimeException(
            "Invalid route handler format. Use a callable or 'Controller@method' string."
        );
    }

    /**
     * Normalize a URI path: ensure it starts with "/" and strip duplicate slashes.
     *
     * @param string $path Raw path.
     *
     * @return string Normalized path.
     */
    protected function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        // Collapse multiple consecutive slashes.
        return preg_replace('#/{2,}#', '/', $path) ?? $path;
    }

    /**
     * Return all registered routes (useful for debugging / CLI listing).
     *
     * @return array<int, array<string, mixed>> All route definitions.
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
