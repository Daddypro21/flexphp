<?php

declare(strict_types=1);

namespace FlexPHP\Console\Commands;

use FlexPHP\Console\BaseCommand;

/**
 * route:list command.
 *
 * Fetches all registered routes from the framework router and renders them
 * in a formatted ASCII table. HTTP methods are colour-coded for readability:
 *
 *   GET     → green
 *   POST    → yellow
 *   PUT     → blue
 *   PATCH   → cyan
 *   DELETE  → red
 *   HEAD    → white (default)
 *
 * Usage:
 *   php flex route:list
 */
class RouteListCommand extends BaseCommand
{
    // -------------------------------------------------------------------------
    // ANSI colour helpers
    // -------------------------------------------------------------------------

    private const COLOR_RESET  = "\033[0m";
    private const COLOR_GREEN  = "\033[32m";
    private const COLOR_YELLOW = "\033[33m";
    private const COLOR_BLUE   = "\033[34m";
    private const COLOR_CYAN   = "\033[36m";
    private const COLOR_RED    = "\033[31m";
    private const COLOR_WHITE  = "\033[97m";
    private const COLOR_BOLD   = "\033[1m";

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'route:list';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'List all registered routes in a formatted table';
    }

    /**
     * Execute the command.
     *
     * Retrieves the router from the service container, collects all routes,
     * and prints them as an ASCII table with colourised HTTP method names.
     *
     * @return int 0 on success, 1 on error.
     */
    public function handle(): int
    {
        if ($this->app === null) {
            $this->error('Application instance is not available.');
            return 1;
        }

        // Attempt to resolve the router from the container.
        // The router is expected to expose a getRoutes(): array method.
        try {
            $router = $this->app->make(\FlexPHP\Routing\Router::class);
        } catch (\Throwable $e) {
            $this->error('Could not resolve the Router: ' . $e->getMessage());
            return 1;
        }

        if (!method_exists($router, 'getRoutes')) {
            $this->error('The Router class does not implement getRoutes().');
            return 1;
        }

        $routes = $router->getRoutes();

        if (empty($routes)) {
            $this->warn('No routes registered.');
            return 0;
        }

        $this->printTable($routes);
        return 0;
    }

    // -------------------------------------------------------------------------
    // Table rendering
    // -------------------------------------------------------------------------

    /**
     * Render the route list as an ASCII table.
     *
     * Each route is expected to be an associative array (or object) with the
     * fields: method, uri, name, action. Missing fields are shown as '-'.
     *
     * @param array<int, array<string, string>|object> $routes
     */
    private function printTable(array $routes): void
    {
        // Normalise each route to an associative array
        $rows = array_map(fn($r) => $this->normaliseRoute($r), $routes);

        // Determine the maximum column widths for alignment
        $widths = [
            'method' => strlen('METHOD'),
            'uri'    => strlen('URI'),
            'name'   => strlen('NAME'),
            'action' => strlen('ACTION'),
        ];

        foreach ($rows as $row) {
            $widths['method'] = max($widths['method'], strlen($row['method']));
            $widths['uri']    = max($widths['uri'],    strlen($row['uri']));
            $widths['name']   = max($widths['name'],   strlen($row['name']));
            $widths['action'] = max($widths['action'], strlen($row['action']));
        }

        $separator = $this->buildSeparator($widths);

        // Header
        $this->line('');
        $this->line($separator);
        $this->line($this->buildHeaderRow($widths));
        $this->line($separator);

        // Data rows
        foreach ($rows as $row) {
            $this->line($this->buildDataRow($row, $widths));
        }

        $this->line($separator);
        $this->line('');
        $this->info(count($rows) . ' route(s) registered.');
    }

    /**
     * Build the horizontal separator line.
     *
     * @param array<string, int> $widths Column width map.
     * @return string A line of dashes and plus signs.
     */
    private function buildSeparator(array $widths): string
    {
        $parts = array_map(fn(int $w) => str_repeat('-', $w + 2), array_values($widths));
        return '+' . implode('+', $parts) . '+';
    }

    /**
     * Build the header row string.
     *
     * @param array<string, int> $widths Column width map.
     * @return string
     */
    private function buildHeaderRow(array $widths): string
    {
        $cells = [
            str_pad('METHOD', $widths['method']),
            str_pad('URI',    $widths['uri']),
            str_pad('NAME',   $widths['name']),
            str_pad('ACTION', $widths['action']),
        ];

        return '| ' . self::COLOR_BOLD . implode(' | ', $cells) . self::COLOR_RESET . ' |';
    }

    /**
     * Build a single data row string with colourised HTTP method.
     *
     * @param array<string, string> $row    Normalised route row.
     * @param array<string, int>    $widths Column width map.
     * @return string
     */
    private function buildDataRow(array $row, array $widths): string
    {
        $methodColour  = $this->methodColour($row['method']);
        $colouredMethod = $methodColour . str_pad($row['method'], $widths['method']) . self::COLOR_RESET;

        $cells = [
            $colouredMethod,
            str_pad($row['uri'],    $widths['uri']),
            str_pad($row['name'],   $widths['name']),
            str_pad($row['action'], $widths['action']),
        ];

        return '| ' . implode(' | ', $cells) . ' |';
    }

    /**
     * Return the ANSI colour code for a given HTTP method.
     *
     * @param string $method HTTP verb.
     * @return string ANSI escape sequence.
     */
    private function methodColour(string $method): string
    {
        return match (strtoupper($method)) {
            'GET'    => self::COLOR_GREEN,
            'POST'   => self::COLOR_YELLOW,
            'PUT'    => self::COLOR_BLUE,
            'PATCH'  => self::COLOR_CYAN,
            'DELETE' => self::COLOR_RED,
            default  => self::COLOR_WHITE,
        };
    }

    /**
     * Normalise a route (array or object) to a flat associative array.
     *
     * Handles both array routes (from simple routers) and objects that expose
     * public properties or getter methods.
     *
     * @param array<string,mixed>|object $route
     * @return array{method: string, uri: string, name: string, action: string}
     */
    private function normaliseRoute(array|object $route): array
    {
        if (is_array($route)) {
            return [
                'method' => strtoupper($route['method'] ?? $route[0] ?? '-'),
                'uri'    => $route['uri']    ?? $route['path'] ?? $route[1] ?? '-',
                'name'   => $route['name']   ?? '-',
                'action' => $this->formatAction($route['action'] ?? $route['handler'] ?? $route[2] ?? '-'),
            ];
        }

        // Object — try common getter / property patterns
        return [
            'method' => strtoupper($this->readProp($route, ['method', 'getMethod'])),
            'uri'    => $this->readProp($route, ['uri', 'path', 'getUri', 'getPath']),
            'name'   => $this->readProp($route, ['name', 'getName'], '-'),
            'action' => $this->formatAction($this->readProp($route, ['action', 'handler', 'getAction', 'getHandler'])),
        ];
    }

    /**
     * Read a property or call a method on an object; try each candidate in order.
     *
     * @param object   $obj        Target object.
     * @param string[] $candidates Property names or method names to try.
     * @param string   $default    Value returned when no candidate is found.
     * @return string
     */
    private function readProp(object $obj, array $candidates, string $default = '-'): string
    {
        foreach ($candidates as $candidate) {
            if (method_exists($obj, $candidate)) {
                return (string) $obj->$candidate();
            }
            if (property_exists($obj, $candidate)) {
                return (string) $obj->$candidate;
            }
        }
        return $default;
    }

    /**
     * Format an action (closure, array pair, or string) as a human-readable string.
     *
     * @param mixed $action Raw action value.
     * @return string
     */
    private function formatAction(mixed $action): string
    {
        if ($action instanceof \Closure) {
            return 'Closure';
        }

        if (is_array($action)) {
            $class  = is_object($action[0]) ? get_class($action[0]) : (string) ($action[0] ?? '');
            $method = (string) ($action[1] ?? '');
            return $class . '@' . $method;
        }

        return (string) $action;
    }
}
