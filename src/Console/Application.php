<?php

declare(strict_types=1);

namespace FlexPHP\Console;

use FlexPHP\Core\Application as App;

/**
 * FlexPHP Console Application.
 *
 * Receives the raw $argv array, resolves the requested command, instantiates
 * the command class and hands over execution. Output is colourised with ANSI
 * escape codes for terminals that support them.
 *
 * Usage:
 *   $console = new Application();
 *   $console->register('make:controller', MakeControllerCommand::class);
 *   exit($console->run($argv));
 */
class Application
{
    // -------------------------------------------------------------------------
    // ANSI colour constants
    // -------------------------------------------------------------------------

    private const COLOR_RESET  = "\033[0m";
    private const COLOR_GREEN  = "\033[32m";
    private const COLOR_RED    = "\033[31m";
    private const COLOR_YELLOW = "\033[33m";
    private const COLOR_BLUE   = "\033[34m";
    private const COLOR_CYAN   = "\033[36m";
    private const COLOR_WHITE  = "\033[97m";
    private const COLOR_BOLD   = "\033[1m";

    /**
     * Registered commands: [ name => FQCN ]
     *
     * @var array<string, class-string<BaseCommand>>
     */
    private array $commands;

    /**
     * Reference to the framework application (injected via setApp).
     */
    private ?App $app = null;

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /**
     * @param array<string, class-string<BaseCommand>> $commands Pre-registered commands.
     */
    public function __construct(array $commands = [])
    {
        $this->commands = $commands;
    }

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    /**
     * Register a command class under the given name.
     *
     * @param string $name         Command name (e.g. 'make:controller').
     * @param string $commandClass FQCN of a class extending BaseCommand.
     */
    public function register(string $name, string $commandClass): void
    {
        $this->commands[$name] = $commandClass;
    }

    /**
     * Inject the framework application instance so commands can access the container.
     *
     * @param App $app
     */
    public function setApp(App $app): void
    {
        $this->app = $app;
    }

    // -------------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------------

    /**
     * Parse the argument vector, resolve the command, execute it, and return
     * the exit code.
     *
     * Returns 0 on success, 1 on error, consistent with POSIX conventions.
     *
     * @param array $argv Raw argument vector from PHP's $argv global.
     * @return int Exit code (0 = success, non-zero = failure).
     */
    public function run(array $argv): int
    {
        // argv[0] is the script name; argv[1] is the command name
        $commandName = $argv[1] ?? null;

        // Show help when no command is given or --help is passed without a command
        if ($commandName === null || $commandName === '--help' || $commandName === '-h') {
            $this->printHelp();
            return 0;
        }

        // Check if the requested command exists
        if (!isset($this->commands[$commandName])) {
            self::error("Unknown command: '{$commandName}'");
            self::line('');
            $this->printHelp();
            return 1;
        }

        $commandClass = $this->commands[$commandName];

        // Instantiate and configure the command
        /** @var BaseCommand $command */
        $command = new $commandClass();
        $command->configure($argv, $this->app);

        // Show per-command help when --help is in the arguments
        if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
            self::info("Command: {$commandName}");
            self::line($command->getDescription());
            return 0;
        }

        // Execute the command and return its exit code
        try {
            return $command->handle();
        } catch (\Throwable $e) {
            self::error($e->getMessage());
            return 1;
        }
    }

    // -------------------------------------------------------------------------
    // Help output
    // -------------------------------------------------------------------------

    /**
     * Print the full command listing to STDOUT.
     */
    private function printHelp(): void
    {
        self::line(self::COLOR_BOLD . self::COLOR_CYAN . 'FlexPHP CLI' . self::COLOR_RESET);
        self::line('');
        self::line(self::COLOR_WHITE . 'Usage:' . self::COLOR_RESET . '  php flex <command> [arguments] [options]');
        self::line('');
        self::line(self::COLOR_WHITE . 'Available Commands:' . self::COLOR_RESET);
        self::line('');

        // Calculate column width from the longest command name
        $maxLen = 0;
        foreach (array_keys($this->commands) as $name) {
            $maxLen = max($maxLen, strlen($name));
        }

        foreach ($this->commands as $name => $class) {
            // Instantiate briefly just to read the description
            try {
                /** @var BaseCommand $cmd */
                $cmd         = new $class();
                $description = $cmd->getDescription();
            } catch (\Throwable) {
                $description = '';
            }

            $paddedName = str_pad($name, $maxLen + 2);
            self::line('  ' . self::COLOR_GREEN . $paddedName . self::COLOR_RESET . $description);
        }

        self::line('');
        self::line(self::COLOR_WHITE . 'Options:' . self::COLOR_RESET);
        self::line('  ' . self::COLOR_YELLOW . '--help, -h' . self::COLOR_RESET . '  Show this help message');
        self::line('');
    }

    // -------------------------------------------------------------------------
    // Static output helpers (also proxied from BaseCommand)
    // -------------------------------------------------------------------------

    /**
     * Print an informational message in cyan.
     *
     * @param string $msg
     */
    public static function info(string $msg): void
    {
        echo self::COLOR_CYAN . $msg . self::COLOR_RESET . PHP_EOL;
    }

    /**
     * Print an error message in red to STDERR.
     *
     * @param string $msg
     */
    public static function error(string $msg): void
    {
        fwrite(STDERR, self::COLOR_RED . '[ERROR] ' . $msg . self::COLOR_RESET . PHP_EOL);
    }

    /**
     * Print a success message in green.
     *
     * @param string $msg
     */
    public static function success(string $msg): void
    {
        echo self::COLOR_GREEN . $msg . self::COLOR_RESET . PHP_EOL;
    }

    /**
     * Print a warning message in yellow.
     *
     * @param string $msg
     */
    public static function warn(string $msg): void
    {
        echo self::COLOR_YELLOW . '[WARN] ' . $msg . self::COLOR_RESET . PHP_EOL;
    }

    /**
     * Print a plain line (no colour prefix).
     *
     * @param string $msg
     */
    public static function line(string $msg): void
    {
        echo $msg . PHP_EOL;
    }
}
