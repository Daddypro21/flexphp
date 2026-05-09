<?php

declare(strict_types=1);

namespace FlexPHP\Console\Commands;

use FlexPHP\Console\BaseCommand;

/**
 * make:migration command.
 *
 * Generates a timestamped migration file stub under database/migrations/.
 *
 * Usage:
 *   php flex make:migration create_users_table
 *   php flex make:migration add_email_to_users_table
 *
 * The generated file contains empty up() and down() method stubs that the
 * developer fills in with schema-building calls.
 */
class MakeMigrationCommand extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'make:migration';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Generate a new database migration file';
    }

    /**
     * Execute the command.
     *
     * Reads the migration name from argv[2], generates a timestamped filename,
     * writes the PHP stub to database/migrations/, and reports the result.
     *
     * @return int 0 on success, 1 on error.
     */
    public function handle(): int
    {
        $name = $this->getArgument(2);

        if (empty($name)) {
            $this->error('Please provide a migration name. Example: php flex make:migration create_users_table');
            return 1;
        }

        // Normalise to snake_case
        $name = $this->toSnakeCase($name);

        $basePath  = $this->app?->getBasePath() ?? getcwd();
        $targetDir = $basePath . '/database/migrations';

        // Create the migrations directory if it does not exist
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            $this->error("Failed to create directory: {$targetDir}");
            return 1;
        }

        // Timestamp prefix ensures migrations are applied in creation order
        $timestamp  = date('Y_m_d_His');
        $filename   = $timestamp . '_' . $name . '.php';
        $targetFile = $targetDir . '/' . $filename;

        // Generate a PascalCase class name from the snake_case migration name
        $className = $this->toClassName($name) . '_' . $timestamp;
        // Simplify: just use a readable class name
        $className = $this->toClassName($name);

        $stub = $this->buildStub($className);

        if (file_put_contents($targetFile, $stub) === false) {
            $this->error("Failed to write migration: {$targetFile}");
            return 1;
        }

        $this->success("Migration created: {$targetFile}");
        return 0;
    }

    // -------------------------------------------------------------------------
    // Stub builder
    // -------------------------------------------------------------------------

    /**
     * Build the PHP stub for the migration class.
     *
     * The stub includes placeholder up() and down() methods that the developer
     * fills in with database schema operations.
     *
     * @param string $className PascalCase migration class name.
     * @return string PHP source code.
     */
    private function buildStub(string $className): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

use Cycle\Database\Schema\AbstractTable;

/**
 * Migration: {$className}
 *
 * Implement the up() method to apply your schema changes and the down()
 * method to reverse them (used by migrate --rollback).
 */
class {$className}
{
    /**
     * Apply the migration — create or alter database objects.
     *
     * Example (Cycle ORM DBAL):
     *   \$table = \$schema->table('users');
     *   \$table->column('id')->primary();
     *   \$table->column('email')->string(255);
     *   \$table->save();
     *
     * @param AbstractTable|mixed \$schema Schema builder provided by the migration runner.
     */
    public function up(mixed \$schema): void
    {
        // TODO: implement schema changes
    }

    /**
     * Reverse the migration — undo what up() did.
     *
     * Example:
     *   \$schema->table('users')->drop();
     *
     * @param AbstractTable|mixed \$schema Schema builder provided by the migration runner.
     */
    public function down(mixed \$schema): void
    {
        // TODO: reverse schema changes
    }
}
PHP;
    }

    // -------------------------------------------------------------------------
    // String helpers
    // -------------------------------------------------------------------------

    /**
     * Convert a string to snake_case.
     *
     * Replaces spaces and hyphens with underscores and lowercases everything.
     *
     * @param string $value Input string.
     * @return string snake_case value.
     */
    private function toSnakeCase(string $value): string
    {
        $value = preg_replace('/[\s\-]+/', '_', $value) ?? $value;
        $value = preg_replace('/(?<=[a-z\d])(?=[A-Z])/', '_', $value) ?? $value;
        return strtolower($value);
    }

    /**
     * Convert a snake_case string to a PascalCase class name.
     *
     * Example: create_users_table → CreateUsersTable
     *
     * @param string $value snake_case input.
     * @return string PascalCase class name.
     */
    private function toClassName(string $value): string
    {
        return str_replace('_', '', ucwords($value, '_'));
    }
}
