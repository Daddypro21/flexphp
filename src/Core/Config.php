<?php

declare(strict_types=1);

namespace FlexPHP\Core;

use InvalidArgumentException;

/**
 * Configuration manager with dot-notation access.
 *
 * Config files are PHP files stored in a directory and must return an array.
 * They are loaded lazily on first access.
 *
 * Usage:
 *   $config = new Config('/path/to/config');
 *   $config->get('app.name');            // reads config/app.php, key 'name'
 *   $config->get('database.default');    // reads config/database.php, key 'default'
 *   $config->set('app.debug', true);
 *   $config->has('app.timezone');        // true / false
 */
class Config
{
    /**
     * Loaded configuration data, keyed by filename (without .php extension).
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Absolute path to the directory that contains config PHP files.
     *
     * @param string $configPath Absolute path to the config directory.
     */
    public function __construct(protected string $configPath)
    {
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Retrieve a configuration value using dot notation.
     *
     * The first segment of the key is the filename (without .php).
     * Subsequent segments are array keys within that file's array.
     *
     * Examples:
     *   get('app.name')           => config/app.php['name']
     *   get('database.connections.mysql.host')
     *
     * @param string $key     Dot-notation config key.
     * @param mixed  $default Value returned when the key does not exist.
     *
     * @return mixed The resolved config value, or $default if not found.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        [$file, $remaining] = $this->parseKey($key);

        $this->loadFile($file);

        if ($remaining === '') {
            return $this->data[$file] ?? $default;
        }

        return $this->dotGet($this->data[$file] ?? [], $remaining, $default);
    }

    /**
     * Set a configuration value at runtime using dot notation.
     *
     * This does NOT write back to the config file on disk.
     *
     * @param string $key   Dot-notation config key.
     * @param mixed  $value The value to assign.
     */
    public function set(string $key, mixed $value): void
    {
        [$file, $remaining] = $this->parseKey($key);

        $this->loadFile($file);

        if (!isset($this->data[$file]) || !is_array($this->data[$file])) {
            $this->data[$file] = [];
        }

        if ($remaining === '') {
            $this->data[$file] = $value;
            return;
        }

        $this->dotSet($this->data[$file], $remaining, $value);
    }

    /**
     * Determine whether a configuration key exists and is not null.
     *
     * @param string $key Dot-notation config key.
     *
     * @return bool True if the key exists with a non-null value.
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Return all loaded configuration data.
     *
     * @return array<string, mixed> All loaded config arrays, keyed by file name.
     */
    public function all(): array
    {
        return $this->data;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Parse a dot-notation key into the config file name and the remaining key path.
     *
     * @param string $key Full dot-notation key (e.g. "database.connections.mysql").
     *
     * @return array{0: string, 1: string} [fileName, remainingKey]
     *
     * @throws InvalidArgumentException If the key is empty.
     */
    protected function parseKey(string $key): array
    {
        if ($key === '') {
            throw new InvalidArgumentException('Config key must not be empty.');
        }

        $parts = explode('.', $key, 2);

        return [
            $parts[0],
            $parts[1] ?? '',
        ];
    }

    /**
     * Lazily load a config file by name.
     * If the file has already been loaded or does not exist, this is a no-op.
     *
     * @param string $file Config file name (without .php extension).
     */
    protected function loadFile(string $file): void
    {
        if (array_key_exists($file, $this->data)) {
            return;
        }

        $path = rtrim($this->configPath, '/\\') . DIRECTORY_SEPARATOR . $file . '.php';

        if (!is_file($path)) {
            // Mark as loaded (empty array) so we don't retry on each access.
            $this->data[$file] = [];
            return;
        }

        $loaded = require $path;

        $this->data[$file] = is_array($loaded) ? $loaded : [];
    }

    /**
     * Retrieve a value from a nested array using dot notation.
     *
     * @param array<mixed> $array   The array to traverse.
     * @param string       $key     Remaining dot-notation path.
     * @param mixed        $default Default value if not found.
     *
     * @return mixed The found value or $default.
     */
    protected function dotGet(array $array, string $key, mixed $default): mixed
    {
        $segments = explode('.', $key);

        foreach ($segments as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Set a value in a nested array using dot notation.
     * Intermediate arrays are created automatically.
     *
     * @param array<mixed> $array Reference to the array to mutate.
     * @param string       $key   Remaining dot-notation path.
     * @param mixed        $value The value to assign.
     */
    protected function dotSet(array &$array, string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $last     = array_pop($segments);

        foreach ($segments as $segment) {
            if (!isset($array[$segment]) || !is_array($array[$segment])) {
                $array[$segment] = [];
            }
            $array = &$array[$segment];
        }

        $array[$last] = $value;
    }
}
