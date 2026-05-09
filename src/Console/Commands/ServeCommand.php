<?php

declare(strict_types=1);

namespace FlexPHP\Console\Commands;

use FlexPHP\Console\BaseCommand;

/**
 * serve command.
 *
 * Starts the PHP built-in development web server pointed at the public/
 * directory so developers can quickly preview the application without
 * configuring a full web server (Apache, Nginx, etc.).
 *
 * Usage:
 *   php flex serve
 *   php flex serve --host=0.0.0.0 --port=9000
 *
 * Note: the built-in server is NOT suitable for production use.
 */
class ServeCommand extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'serve';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Start the PHP built-in development server (public/ document root)';
    }

    /**
     * Execute the command.
     *
     * Reads --host and --port options, prints the server URL, then hands
     * control to `php -S` via passthru() so that server output is streamed
     * directly to the terminal. The process runs until the user sends SIGINT
     * (Ctrl+C).
     *
     * @return int Exit code returned by the underlying php process.
     */
    public function handle(): int
    {
        // Resolve host and port from options, falling back to safe defaults
        $host = $this->getOption('host', 'localhost');
        $port = (int) $this->getOption('port', 8000);

        // Basic port range validation
        if ($port < 1 || $port > 65535) {
            $this->error("Invalid port number: {$port}. Must be between 1 and 65535.");
            return 1;
        }

        $basePath  = $this->app?->getBasePath() ?? getcwd();
        $docRoot   = $basePath . '/public';

        // Ensure the public directory exists before starting the server
        if (!is_dir($docRoot)) {
            $this->error("Document root not found: {$docRoot}");
            return 1;
        }

        $address = "{$host}:{$port}";
        $url     = "http://{$address}";

        $this->info('FlexPHP Development Server');
        $this->line('');
        $this->success("Listening on: {$url}");
        $this->line("Document root: {$docRoot}");
        $this->warn('Press Ctrl+C to stop.');
        $this->line('');

        // Build the shell command.
        // -S  listen on address
        // -t  document root
        $command = sprintf(
            'php -S %s -t %s',
            escapeshellarg($address),
            escapeshellarg($docRoot)
        );

        // passthru() streams stdout/stderr directly to the terminal and
        // populates $returnCode with the process exit status.
        passthru($command, $returnCode);

        return (int) $returnCode;
    }
}
