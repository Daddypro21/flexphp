<?php

declare(strict_types=1);

namespace FlexPHP\Console\Commands;

use FlexPHP\Console\BaseCommand;

/**
 * make:controller command.
 *
 * Generates a new controller PHP class file under app/Controllers/.
 *
 * Usage:
 *   php flex make:controller UserController
 *   php flex make:controller UserController --resource
 *
 * The --resource flag adds the standard resourceful action methods:
 *   index, show, store, update, destroy
 */
class MakeControllerCommand extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'make:controller';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Generate a new controller class';
    }

    /**
     * Execute the command.
     *
     * Reads the controller name from argv[2], optionally adds resource methods
     * when --resource is passed, writes the file to app/Controllers/, and
     * prints a success or error message.
     *
     * @return int 0 on success, 1 on error.
     */
    public function handle(): int
    {
        // argv[2] is the first user-supplied argument after the command name
        $name = $this->getArgument(2);

        if (empty($name)) {
            $this->error('Please provide a controller name. Example: php flex make:controller UserController');
            return 1;
        }

        // Normalise: ensure the name ends with "Controller"
        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }

        $isResource  = $this->hasOption('resource');
        $basePath    = $this->app?->getBasePath() ?? getcwd();
        $targetDir   = $basePath . '/app/Controllers';
        $targetFile  = $targetDir . '/' . $name . '.php';

        // Create the directory if it does not exist
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            $this->error("Failed to create directory: {$targetDir}");
            return 1;
        }

        // Refuse to overwrite an existing file without a force flag
        if (file_exists($targetFile)) {
            $this->error("Controller already exists: {$targetFile}");
            return 1;
        }

        $stub = $isResource
            ? $this->buildResourceStub($name)
            : $this->buildBasicStub($name);

        if (file_put_contents($targetFile, $stub) === false) {
            $this->error("Failed to write file: {$targetFile}");
            return 1;
        }

        $this->success("Controller created: {$targetFile}");
        return 0;
    }

    // -------------------------------------------------------------------------
    // Stub builders
    // -------------------------------------------------------------------------

    /**
     * Build a minimal controller stub with no resource methods.
     *
     * @param string $name Controller class name.
     * @return string PHP source code.
     */
    private function buildBasicStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * {$name}
 *
 * Add your action methods here. Each public method can be mapped to a route
 * via the router, e.g.:
 *   \$router->get('/example', [{$name}::class, 'index']);
 */
class {$name} extends BaseController
{
    /**
     * Display the default view.
     */
    public function index(): mixed
    {
        // TODO: implement index action
    }
}
PHP;
    }

    /**
     * Build a resourceful controller stub with the five standard action methods.
     *
     * @param string $name Controller class name.
     * @return string PHP source code.
     */
    private function buildResourceStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Controllers;

use FlexPHP\Http\Request;
use FlexPHP\Http\Response;

/**
 * {$name} — Resourceful controller.
 *
 * Maps to the following routes when registered with the router:
 *   GET    /resource          → index()
 *   GET    /resource/{id}     → show(\$id)
 *   POST   /resource          → store(\$request)
 *   PUT    /resource/{id}     → update(\$request, \$id)
 *   DELETE /resource/{id}     → destroy(\$id)
 */
class {$name} extends BaseController
{
    /**
     * List all resources.
     */
    public function index(): mixed
    {
        // TODO: return a list of resources
    }

    /**
     * Display a single resource.
     *
     * @param int|string \$id Resource identifier.
     */
    public function show(int|string \$id): mixed
    {
        // TODO: return the resource identified by \$id
    }

    /**
     * Store a new resource.
     *
     * @param Request \$request Incoming HTTP request.
     */
    public function store(Request \$request): mixed
    {
        // TODO: validate and persist the new resource
    }

    /**
     * Update an existing resource.
     *
     * @param Request    \$request Incoming HTTP request.
     * @param int|string \$id      Resource identifier.
     */
    public function update(Request \$request, int|string \$id): mixed
    {
        // TODO: validate and update the resource identified by \$id
    }

    /**
     * Delete a resource.
     *
     * @param int|string \$id Resource identifier.
     */
    public function destroy(int|string \$id): mixed
    {
        // TODO: delete the resource identified by \$id
    }
}
PHP;
    }
}
