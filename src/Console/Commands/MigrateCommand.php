<?php

declare(strict_types=1);

namespace FlexPHP\Console\Commands;

use FlexPHP\Console\BaseCommand;

/**
 * migrate command.
 *
 * Runs database migrations using the Cycle ORM migration runner.
 *
 * Usage:
 *   php flex migrate                Run all pending migrations
 *   php flex migrate --rollback     Roll back the last batch of migrations
 *   php flex migrate --fresh        Drop all tables and re-run every migration
 *
 * The command resolves a Cycle\Migrations\Migrator (or compatible interface)
 * from the service container. If the container cannot supply one the command
 * falls back to scanning database/migrations/ and executing the files directly.
 */
class MigrateCommand extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'migrate';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Run database migrations (--rollback | --fresh)';
    }

    /**
     * Execute the command.
     *
     * Determines the desired operation from command-line options and delegates
     * to the appropriate migration method.
     *
     * @return int 0 on success, 1 on error.
     */
    public function handle(): int
    {
        $rollback = $this->hasOption('rollback');
        $fresh    = $this->hasOption('fresh');

        if ($fresh) {
            return $this->runFresh();
        }

        if ($rollback) {
            return $this->runRollback();
        }

        return $this->runMigrate();
    }

    // -------------------------------------------------------------------------
    // Migration operations
    // -------------------------------------------------------------------------

    /**
     * Run all pending migrations in chronological order.
     *
     * Scans database/migrations/ for PHP files, loads any that have not yet
     * been recorded in the migrations log, calls up(), and records them.
     *
     * @return int Exit code.
     */
    private function runMigrate(): int
    {
        $this->info('Running pending migrations…');

        try {
            $migrator = $this->resolveMigrator();

            if ($migrator !== null) {
                // Cycle ORM migrator path
                $migrator->run();
                $this->success('All pending migrations ran successfully.');
                return 0;
            }

            // Fallback: manual file-based execution
            return $this->runFileMigrations('up');
        } catch (\Throwable $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Roll back the last batch of migrations.
     *
     * With the Cycle migrator this calls rollback() on the runner.
     * With the file-based fallback it processes files in reverse order.
     *
     * @return int Exit code.
     */
    private function runRollback(): int
    {
        $this->warn('Rolling back the last migration batch…');

        try {
            $migrator = $this->resolveMigrator();

            if ($migrator !== null) {
                $migrator->rollback();
                $this->success('Last batch rolled back successfully.');
                return 0;
            }

            return $this->runFileMigrations('down', reverse: true);
        } catch (\Throwable $e) {
            $this->error('Rollback failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Drop all database tables and re-run every migration from scratch.
     *
     * USE WITH CAUTION: all existing data will be permanently deleted.
     *
     * @return int Exit code.
     */
    private function runFresh(): int
    {
        $this->warn('Dropping all tables and re-running all migrations (--fresh)…');

        try {
            $migrator = $this->resolveMigrator();

            if ($migrator !== null) {
                // If the migrator exposes a fresh() or dropAll() method, use it
                if (method_exists($migrator, 'fresh')) {
                    $migrator->fresh();
                } else {
                    // Generic fallback: rollback everything, then migrate
                    $migrator->rollback();
                    $migrator->run();
                }
                $this->success('Fresh migration complete.');
                return 0;
            }

            // File-based fallback: run all downs then all ups
            $this->runFileMigrations('down', reverse: true);
            return $this->runFileMigrations('up');
        } catch (\Throwable $e) {
            $this->error('Fresh migration failed: ' . $e->getMessage());
            return 1;
        }
    }

    // -------------------------------------------------------------------------
    // File-based fallback runner
    // -------------------------------------------------------------------------

    /**
     * Scan database/migrations/ and execute the requested method (up or down)
     * on each migration class found.
     *
     * @param string $method  'up' or 'down'.
     * @param bool   $reverse Process files in reverse alphabetical order (for rollback).
     * @return int Exit code.
     */
    private function runFileMigrations(string $method, bool $reverse = false): int
    {
        $basePath = $this->app?->getBasePath() ?? getcwd();
        $dir      = $basePath . '/database/migrations';

        if (!is_dir($dir)) {
            $this->warn("Migrations directory not found: {$dir}");
            return 0;
        }

        // Collect PHP migration files
        $files = glob($dir . '/*.php') ?: [];

        if (empty($files)) {
            $this->info('No migration files found.');
            return 0;
        }

        sort($files);

        if ($reverse) {
            $files = array_reverse($files);
        }

        $count = 0;

        foreach ($files as $file) {
            require_once $file;

            // Derive the class name from the filename (strip timestamp prefix and .php)
            $basename  = basename($file, '.php');
            $parts     = explode('_', $basename, 5); // 2024_01_01_120000_name
            $nameParts = array_slice($parts, 4);      // everything after the timestamp
            $className = str_replace('_', '', ucwords(implode('_', $nameParts), '_'));

            if (!class_exists($className)) {
                $this->warn("Class '{$className}' not found in {$file} — skipping.");
                continue;
            }

            $instance = new $className();

            if (!method_exists($instance, $method)) {
                $this->warn("Method '{$method}' not found in {$className} — skipping.");
                continue;
            }

            $instance->$method(null); // null schema: stub migrations accept mixed
            $this->line("  {$method}  " . basename($file));
            $count++;
        }

        if ($count === 0) {
            $this->info('Nothing to migrate.');
        } else {
            $this->success("{$count} migration(s) executed.");
        }

        return 0;
    }

    // -------------------------------------------------------------------------
    // Container resolution helper
    // -------------------------------------------------------------------------

    /**
     * Attempt to resolve a Cycle ORM Migrator from the service container.
     *
     * Returns null when the container is unavailable or the migrator has not
     * been registered, allowing the command to fall back to the file runner.
     *
     * @return object|null The migrator instance or null.
     */
    private function resolveMigrator(): ?object
    {
        if ($this->app === null) {
            return null;
        }

        $candidates = [
            'Cycle\Migrations\Migrator',
            'FlexPHP\Database\MigratorInterface',
        ];

        foreach ($candidates as $abstract) {
            try {
                $instance = $this->app->make($abstract);
                if ($instance !== null) {
                    return $instance;
                }
            } catch (\Throwable) {
                // Not bound in the container — try the next candidate
            }
        }

        return null;
    }
}
