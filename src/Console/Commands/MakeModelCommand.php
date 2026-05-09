<?php

declare(strict_types=1);

namespace FlexPHP\Console\Commands;

use FlexPHP\Console\BaseCommand;

/**
 * make:model command.
 *
 * Generates a new Cycle ORM entity model class under app/Models/.
 *
 * Usage:
 *   php flex make:model User
 *   php flex make:model BlogPost
 *
 * The generated class includes the #[Entity] attribute required by Cycle ORM
 * and pre-defined id, createdAt, and updatedAt columns.
 */
class MakeModelCommand extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'make:model';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Generate a new Cycle ORM model / entity class';
    }

    /**
     * Execute the command.
     *
     * Reads the model name from argv[2], creates the app/Models/ directory if
     * needed, generates the PHP class file, and reports success or failure.
     *
     * @return int 0 on success, 1 on error.
     */
    public function handle(): int
    {
        // The first user argument (after the command name) is the model name
        $name = $this->getArgument(2);

        if (empty($name)) {
            $this->error('Please provide a model name. Example: php flex make:model User');
            return 1;
        }

        // Normalise to PascalCase (basic: just uppercase the first letter)
        $name = ucfirst($name);

        $basePath   = $this->app?->getBasePath() ?? getcwd();
        $targetDir  = $basePath . '/app/Models';
        $targetFile = $targetDir . '/' . $name . '.php';

        // Ensure the target directory exists
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            $this->error("Failed to create directory: {$targetDir}");
            return 1;
        }

        // Do not overwrite an existing model
        if (file_exists($targetFile)) {
            $this->error("Model already exists: {$targetFile}");
            return 1;
        }

        $stub = $this->buildStub($name);

        if (file_put_contents($targetFile, $stub) === false) {
            $this->error("Failed to write file: {$targetFile}");
            return 1;
        }

        $this->success("Model created: {$targetFile}");
        return 0;
    }

    // -------------------------------------------------------------------------
    // Stub builder
    // -------------------------------------------------------------------------

    /**
     * Generate the PHP source for the entity class.
     *
     * Uses Cycle ORM's PHP 8 attribute syntax for schema declaration.
     * The table name is derived by converting PascalCase to snake_case and
     * pluralising with a simple "s" suffix.
     *
     * @param string $name Model class name.
     * @return string PHP source code.
     */
    private function buildStub(string $name): string
    {
        // Derive a default table name from the class name (e.g. BlogPost → blog_posts)
        $tableName = $this->toSnakeCase($name) . 's';

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Models;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeImmutable;

/**
 * {$name} entity.
 *
 * Managed by Cycle ORM. Update the #[Entity] table attribute and add
 * #[Column] properties to match your database schema.
 */
#[Entity(table: '{$tableName}')]
class {$name}
{
    /**
     * Primary key — auto-incremented by the database.
     */
    #[Column(type: 'primary')]
    public int \$id;

    /**
     * Timestamp set when the record is first inserted.
     */
    #[Column(type: 'datetime', name: 'created_at')]
    public DateTimeImmutable \$createdAt;

    /**
     * Timestamp updated on every write.
     */
    #[Column(type: 'datetime', name: 'updated_at', nullable: true)]
    public ?DateTimeImmutable \$updatedAt = null;

    // TODO: add your own columns here, e.g.:
    // #[Column(type: 'string')]
    // public string \$name;

    /**
     * Set timestamps on construction.
     */
    public function __construct()
    {
        \$this->createdAt = new DateTimeImmutable();
    }
}
PHP;
    }

    /**
     * Convert a PascalCase or camelCase string to snake_case.
     *
     * Examples:
     *   BlogPost   → blog_post
     *   UserProfile → user_profile
     *
     * @param string $value Input string.
     * @return string snake_case output.
     */
    private function toSnakeCase(string $value): string
    {
        $pattern = '/(?<=[a-z\d])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/';
        return strtolower(preg_replace($pattern, '_', $value));
    }
}
