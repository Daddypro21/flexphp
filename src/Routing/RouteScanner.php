<?php

declare(strict_types=1);

namespace FlexPHP\Routing;

use FlexPHP\Http\Router;
use FlexPHP\Routing\Attributes\Middleware as MiddlewareAttr;
use FlexPHP\Routing\Attributes\Prefix;
use FlexPHP\Routing\Attributes\Route;
use ReflectionClass;
use ReflectionMethod;

/**
 * Scans controller classes for route attributes and registers them with the Router.
 *
 * Supports:
 *   - #[Get], #[Post], #[Put], #[Patch], #[Delete], #[Any] on methods
 *   - #[Route(path, methods: [...], name: '...', middleware: [...])] on methods
 *   - #[Prefix('/prefix')] on the controller class
 *   - #[Middleware(Foo::class)] on the class (applies to all routes) or on a method
 */
class RouteScanner
{
    /**
     * @param Router   $router          The router to register routes into.
     * @param string[] $controllerDirs  Absolute directory paths to scan for controllers.
     * @param string   $controllerNs    PSR-4 namespace prefix that maps to those directories.
     */
    public function __construct(
        private readonly Router $router,
        private readonly array  $controllerDirs,
        private readonly string $controllerNs,
    ) {}

    /**
     * Discover all controller classes in the configured directories and register
     * any route attributes found on their public methods.
     */
    public function scan(): void
    {
        foreach ($this->controllerDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach ($this->phpFilesIn($dir) as $file) {
                $class = $this->fileToClassName($file, $dir);

                if ($class === null || !class_exists($class)) {
                    continue;
                }

                $this->registerController($class);
            }
        }
    }

    /**
     * Register all route attributes found on a single controller class.
     *
     * @param class-string $class
     */
    public function registerController(string $class): void
    {
        $reflection = new ReflectionClass($class);

        // Skip abstract classes.
        if ($reflection->isAbstract()) {
            return;
        }

        $classPrefix     = $this->resolvePrefix($reflection);
        $classMiddleware = $this->resolveMiddleware($reflection);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Only process methods declared directly on this class.
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            $routeAttributes = $method->getAttributes(Route::class, \ReflectionAttribute::IS_INSTANCEOF);

            if (empty($routeAttributes)) {
                continue;
            }

            $methodMiddleware = $this->resolveMiddleware($method);
            $allMiddleware    = array_merge($classMiddleware, $methodMiddleware);

            foreach ($routeAttributes as $routeAttr) {
                /** @var Route $route */
                $route   = $routeAttr->newInstance();
                $path    = $classPrefix . '/' . ltrim($route->path, '/');
                $path    = rtrim($path, '/') ?: '/';
                $handler = $class . '@' . $method->getName();
                $merged  = array_merge($allMiddleware, $route->middleware);

                foreach ($route->methods as $httpMethod) {
                    $this->router->addRoute(
                        strtoupper($httpMethod),
                        $path,
                        $handler,
                        $route->name,
                        $merged,
                    );
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the URI prefix from a #[Prefix] class attribute (or '' if absent).
     */
    private function resolvePrefix(ReflectionClass $class): string
    {
        $attrs = $class->getAttributes(Prefix::class);

        if (empty($attrs)) {
            return '';
        }

        /** @var Prefix $prefix */
        $prefix = $attrs[0]->newInstance();
        return '/' . trim($prefix->path, '/');
    }

    /**
     * Resolve middleware class names from #[Middleware] attributes on a class or method.
     *
     * @return string[]
     */
    private function resolveMiddleware(ReflectionClass|ReflectionMethod $reflection): array
    {
        $middleware = [];

        foreach ($reflection->getAttributes(MiddlewareAttr::class) as $attr) {
            /** @var MiddlewareAttr $mw */
            $mw         = $attr->newInstance();
            $middleware = array_merge($middleware, $mw->classes);
        }

        return $middleware;
    }

    /**
     * Recursively yield all .php files in a directory.
     *
     * @return iterable<string>
     */
    private function phpFilesIn(string $dir): iterable
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }

    /**
     * Convert an absolute file path to a fully-qualified class name.
     *
     * E.g. /app/Controllers/ArticleController.php → App\Controllers\ArticleController
     *
     * @return class-string|null
     */
    private function fileToClassName(string $filePath, string $baseDir): ?string
    {
        $relative = str_replace($baseDir, '', $filePath);
        $relative = ltrim($relative, '/\\');
        $relative = str_replace(['/', '\\'], '\\', $relative);
        $class    = $this->controllerNs . '\\' . str_replace('.php', '', $relative);

        return class_exists($class) ? $class : null;
    }
}
