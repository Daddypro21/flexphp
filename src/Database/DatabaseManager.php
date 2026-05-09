<?php

declare(strict_types=1);

namespace FlexPHP\Database;

use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\DatabaseManager as CycleDatabaseManager;
use Cycle\ORM\Factory;
use Cycle\ORM\ORM;
use Cycle\ORM\Schema;
use Cycle\ORM\SchemaInterface;
use Cycle\Database\DatabaseInterface;
use Cycle\Annotated\Embeddings;
use Cycle\Annotated\Entities;
use Cycle\Annotated\MergeColumns;
use Cycle\Annotated\MergeIndexes;
use Cycle\Schema\Compiler;
use Cycle\Schema\Registry;
use Cycle\Schema\Generator\GenerateRelations;
use Cycle\Schema\Generator\GenerateTypecast;
use Cycle\Schema\Generator\RenderRelations;
use Cycle\Schema\Generator\RenderTables;
use Cycle\Schema\Generator\ResetTables;
use Cycle\Schema\Generator\SyncTables;
use Cycle\Schema\Generator\ValidateEntities;
use Spiral\Tokenizer\ClassLocator;
use Symfony\Component\Finder\Finder;

/**
 * DatabaseManager bridges FlexPHP configuration to the Cycle DBAL and ORM.
 *
 * It is responsible for:
 * - Building a Cycle\Database\DatabaseManager (DBAL) from the application config.
 * - Lazily constructing and caching the Cycle ORM instance.
 * - Compiling the entity schema from annotated PHP classes.
 */
class DatabaseManager
{
    /** @var CycleDatabaseManager Cycle DBAL manager. */
    private CycleDatabaseManager $dbal;

    /** @var ORM|null Lazily instantiated ORM. */
    private ?ORM $orm = null;

    /** @var SchemaInterface|null Compiled ORM schema. */
    private ?SchemaInterface $schema = null;

    /**
     * @param array<string, mixed> $config Database configuration array.
     *
     * Expected structure:
     * [
     *   'default'     => 'mysql',
     *   'connections' => [
     *     'mysql' => [
     *       'driver'   => 'mysql',
     *       'host'     => 'localhost',
     *       'port'     => 3306,
     *       'database' => 'app',
     *       'username' => 'root',
     *       'password' => '',
     *       'charset'  => 'utf8mb4',
     *     ],
     *   ],
     *   'entity_dirs' => ['app/Models'],
     * ]
     */
    public function __construct(private readonly array $config)
    {
        $this->dbal = $this->buildDbal();
    }

    /**
     * Returns the default database connection.
     */
    public function getDatabase(): DatabaseInterface
    {
        return $this->dbal->database('default');
    }

    /**
     * Returns the Cycle ORM instance, building it lazily on first access.
     */
    public function getOrm(): ORM
    {
        if ($this->orm === null) {
            $this->buildOrm();
        }

        return $this->orm; // @phpstan-ignore-line (built above)
    }

    /**
     * Returns the compiled ORM schema, building it lazily on first access.
     */
    public function getSchema(): SchemaInterface
    {
        if ($this->schema === null) {
            $this->buildOrm();
        }

        return $this->schema; // @phpstan-ignore-line (built above)
    }

    /**
     * Maps a driver alias from config to its fully-qualified Cycle driver class.
     *
     * @param string $driver One of 'mysql', 'postgres', 'sqlite'.
     * @return class-string
     */
    private function resolveDriver(string $driver): string
    {
        return match ($driver) {
            'mysql'    => \Spiral\Database\Driver\MySQL\MySQLDriver::class,
            'postgres' => \Spiral\Database\Driver\Postgres\PostgresDriver::class,
            'sqlite'   => \Spiral\Database\Driver\SQLite\SQLiteDriver::class,
            default    => throw new \InvalidArgumentException(
                "Unsupported database driver: [{$driver}]."
            ),
        };
    }

    /**
     * Builds the Cycle DBAL manager from the application configuration.
     *
     * Converts the FlexPHP-style config array into the structure required by
     * Cycle\Database\Config\DatabaseConfig and returns a ready-to-use
     * Cycle\Database\DatabaseManager.
     */
    private function buildDbal(): CycleDatabaseManager
    {
        $connections = $this->config['connections'] ?? [];
        $default     = $this->config['default'] ?? array_key_first($connections);

        $drivers   = [];
        $databases = [];

        foreach ($connections as $name => $conn) {
            $driverClass = $this->resolveDriver($conn['driver'] ?? 'mysql');

            $options = [
                'connection' => $this->buildDsn($conn),
                'username'   => $conn['username'] ?? '',
                'password'   => $conn['password'] ?? '',
                'options'    => $conn['options'] ?? [],
            ];

            $drivers[$name]   = new \Cycle\Database\Config\DriverConfig(
                connection: new \Cycle\Database\Config\MySQL\TcpConnectionConfig(
                    database: $conn['database'] ?? '',
                    host:     $conn['host']     ?? 'localhost',
                    port:     (int) ($conn['port'] ?? 3306),
                    user:     $conn['username'] ?? 'root',
                    password: $conn['password'] ?? '',
                ),
            );

            $databases[$name] = new \Cycle\Database\Config\DatabasePartialConfig(
                driver: $name,
            );
        }

        $dbConfig = new DatabaseConfig([
            'default'   => $default,
            'databases' => $databases,
            'drivers'   => $drivers,
        ]);

        return new CycleDatabaseManager($dbConfig);
    }

    /**
     * Builds a PDO-compatible DSN string from a connection config array.
     *
     * @param array<string, mixed> $conn
     */
    private function buildDsn(array $conn): string
    {
        $driver = $conn['driver'] ?? 'mysql';

        return match ($driver) {
            'mysql' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $conn['host']     ?? 'localhost',
                $conn['port']     ?? 3306,
                $conn['database'] ?? '',
                $conn['charset']  ?? 'utf8mb4',
            ),
            'postgres' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $conn['host']     ?? 'localhost',
                $conn['port']     ?? 5432,
                $conn['database'] ?? '',
            ),
            'sqlite' => sprintf('sqlite:%s', $conn['database'] ?? ':memory:'),
            default  => throw new \InvalidArgumentException(
                "Cannot build DSN for driver: [{$driver}]."
            ),
        };
    }

    /**
     * Compiles the ORM schema from annotated entity classes and instantiates
     * the Cycle ORM.
     *
     * Entity directories are resolved relative to the project root. Each
     * directory is scanned for PHP classes that carry Cycle ORM attributes
     * (e.g. #[Entity], #[Column]).
     */
    private function buildOrm(): void
    {
        $entityDirs = $this->config['entity_dirs'] ?? ['app/Models'];

        // Locate all PHP files inside the configured entity directories.
        $finder = new Finder();
        $finder->files()->in($entityDirs)->name('*.php');

        $classLocator = new ClassLocator($finder);

        $schema = (new Compiler())->compile(new Registry($this->dbal), [
            new ResetTables(),
            new Embeddings($classLocator),
            new Entities($classLocator),
            new MergeColumns(),
            new GenerateRelations(),
            new ValidateEntities(),
            new RenderTables(),
            new RenderRelations(),
            new MergeIndexes(),
            new GenerateTypecast(),
        ]);

        $this->schema = new Schema($schema);
        $this->orm    = new ORM(new Factory($this->dbal), $this->schema);
    }
}
