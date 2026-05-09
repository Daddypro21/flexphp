<?php

declare(strict_types=1);

namespace FlexPHP\Console;

use FlexPHP\Core\Application;

/**
 * Abstract base class for all FlexPHP console commands.
 *
 * Subclasses must implement:
 *   handle()         — the command's main logic; returns an integer exit code
 *   getName()        — the command's CLI name (e.g. 'make:controller')
 *   getDescription() — one-line description shown in the help listing
 *
 * Helper methods:
 *   getArgument(int $index)        — positional argument by index (0 = script, 1 = cmd, 2 = first arg)
 *   getOption(string $name)        — --name=value or --flag style options
 *   hasOption(string $name)        — check if a flag/option is present
 *
 * Output is delegated to the static methods on Console\Application.
 */
abstract class BaseCommand
{
    /**
     * The raw argument vector as passed to run().
     *
     * @var string[]
     */
    protected array $argv = [];

    /**
     * Reference to the framework application container.
     * Populated by configure() before handle() is called.
     */
    protected ?Application $app = null;

    // -------------------------------------------------------------------------
    // Abstract contract
    // -------------------------------------------------------------------------

    /**
     * Execute the command logic.
     *
     * @return int Exit code: 0 = success, non-zero = failure.
     */
    abstract public function handle(): int;

    /**
     * Return the unique CLI name used to invoke this command.
     *
     * @return string E.g. 'make:controller'
     */
    abstract public function getName(): string;

    /**
     * Return a one-line human-readable description for the help screen.
     *
     * @return string
     */
    abstract public function getDescription(): string;

    // -------------------------------------------------------------------------
    // Configuration — called by the console Application before handle()
    // -------------------------------------------------------------------------

    /**
     * Inject the argument vector and application instance.
     *
     * Called by Console\Application immediately before handle().
     *
     * @param string[]    $argv The full argument vector (including script name and command).
     * @param Application|null $app  The framework application instance.
     */
    public function configure(array $argv, ?Application $app = null): void
    {
        $this->argv = $argv;
        $this->app  = $app;
    }

    // -------------------------------------------------------------------------
    // Argument helpers
    // -------------------------------------------------------------------------

    /**
     * Return a positional argument from the argument vector.
     *
     * Index 0 = script path, index 1 = command name, index 2 = first user argument.
     *
     * @param int   $index   Argument position.
     * @param mixed $default Value returned when the position does not exist.
     * @return mixed The argument value or $default.
     */
    public function getArgument(int $index, mixed $default = null): mixed
    {
        return $this->argv[$index] ?? $default;
    }

    /**
     * Parse and return the value of a named option from the argument vector.
     *
     * Supports two forms:
     *   --name=value   → returns 'value'
     *   --flag         → returns true
     *
     * @param string $name    Option name without leading dashes.
     * @param mixed  $default Value returned when the option is absent.
     * @return mixed The option value, true for bare flags, or $default.
     */
    public function getOption(string $name, mixed $default = null): mixed
    {
        foreach ($this->argv as $arg) {
            // --name=value form
            if (str_starts_with($arg, "--{$name}=")) {
                return substr($arg, strlen("--{$name}="));
            }

            // --flag form (boolean)
            if ($arg === "--{$name}") {
                return true;
            }
        }

        return $default;
    }

    /**
     * Determine whether a named option or flag is present in the argument vector.
     *
     * @param string $name Option name without leading dashes.
     * @return bool True if the option is present.
     */
    public function hasOption(string $name): bool
    {
        return $this->getOption($name, null) !== null;
    }

    // -------------------------------------------------------------------------
    // Output proxy methods (delegates to static Console\Application helpers)
    // -------------------------------------------------------------------------

    /**
     * Print an informational message in cyan.
     *
     * @param string $msg
     */
    protected function info(string $msg): void
    {
        Application::info($msg);
    }

    /**
     * Print an error message in red to STDERR.
     *
     * @param string $msg
     */
    protected function error(string $msg): void
    {
        Application::error($msg);
    }

    /**
     * Print a success message in green.
     *
     * @param string $msg
     */
    protected function success(string $msg): void
    {
        Application::success($msg);
    }

    /**
     * Print a warning message in yellow.
     *
     * @param string $msg
     */
    protected function warn(string $msg): void
    {
        Application::warn($msg);
    }

    /**
     * Print a plain text line with no colour formatting.
     *
     * @param string $msg
     */
    protected function line(string $msg): void
    {
        Application::line($msg);
    }
}
