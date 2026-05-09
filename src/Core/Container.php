<?php

declare(strict_types=1);

namespace FlexPHP\Core;

use Closure;
use FlexPHP\Core\Exceptions\ContainerException;
use FlexPHP\Core\Exceptions\NotFoundException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

/**
 * PSR-11 compliant Dependency Injection Container.
 *
 * Supports bindings, singletons, pre-built instances, and automatic
 * constructor dependency resolution through PHP Reflection.
 */
class Container implements ContainerInterface
{
    /**
     * Registered factory bindings (abstract => Closure|string).
     *
     * @var array<string, Closure|string>
     */
    protected array $bindings = [];

    /**
     * Singleton factory bindings (abstract => Closure|string).
     * Each key is resolved at most once; the result is cached in $instances.
     *
     * @var array<string, Closure|string>
     */
    protected array $singletons = [];

    /**
     * Already-resolved instances (abstract => object|mixed).
     * Used for singletons and manually registered instances.
     *
     * @var array<string, mixed>
     */
    protected array $instances = [];

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    /**
     * Register a transient binding.
     * Every call to make() / get() will produce a fresh instance.
     *
     * @param string         $abstract The abstract type / identifier.
     * @param Closure|string $concrete Factory closure or concrete class name.
     */
    public function bind(string $abstract, Closure|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Register a singleton binding.
     * The concrete is built only once; subsequent resolutions return the same instance.
     *
     * @param string         $abstract The abstract type / identifier.
     * @param Closure|string $concrete Factory closure or concrete class name.
     */
    public function singleton(string $abstract, Closure|string $concrete): void
    {
        $this->singletons[$abstract] = $concrete;
    }

    /**
     * Register an already-instantiated object as a singleton.
     * Subsequent calls to get() / make() for $abstract return this exact object.
     *
     * @param string $abstract The abstract type / identifier.
     * @param mixed  $instance The pre-built instance to store.
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    // -------------------------------------------------------------------------
    // PSR-11
    // -------------------------------------------------------------------------

    /**
     * Find an entry of the container by its identifier and return it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @throws NotFoundException  If no entry was found for the given identifier.
     * @throws ContainerException If an error occurs while resolving the entry.
     *
     * @return mixed The resolved entry.
     */
    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new NotFoundException(
                "No binding found for identifier: {$id}"
            );
        }

        return $this->make($id);
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * @param string $id Identifier of the entry to look for.
     */
    public function has(string $id): bool
    {
        return isset($this->instances[$id])
            || isset($this->singletons[$id])
            || isset($this->bindings[$id])
            || class_exists($id);
    }

    // -------------------------------------------------------------------------
    // Resolution
    // -------------------------------------------------------------------------

    /**
     * Resolve an abstract type, optionally passing extra constructor parameters.
     *
     * Resolution order:
     *   1. Pre-built instance (instances[])
     *   2. Singleton (singletons[]) — built once, then cached
     *   3. Transient binding (bindings[])
     *   4. Auto-wired concrete class (if the identifier is a valid class name)
     *
     * @param string  $abstract The abstract type / identifier.
     * @param array<string, mixed> $params Optional named or positional overrides.
     *
     * @throws ContainerException If resolution fails.
     * @throws NotFoundException  If no concrete can be found.
     *
     * @return mixed The resolved value.
     */
    public function make(string $abstract, array $params = []): mixed
    {
        // 1. Return cached instance (singletons + pre-built instances).
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // 2. Resolve singleton — build once, cache result.
        if (isset($this->singletons[$abstract])) {
            $instance = $this->build($this->singletons[$abstract], $params);
            $this->instances[$abstract] = $instance;
            return $instance;
        }

        // 3. Resolve transient binding — always build fresh.
        if (isset($this->bindings[$abstract])) {
            return $this->build($this->bindings[$abstract], $params);
        }

        // 4. Auto-wire: the identifier itself is a concrete class.
        if (class_exists($abstract)) {
            return $this->buildClass($abstract, $params);
        }

        throw new NotFoundException(
            "Unable to resolve '{$abstract}': no binding or class found."
        );
    }

    // -------------------------------------------------------------------------
    // Internal build helpers
    // -------------------------------------------------------------------------

    /**
     * Build a concrete from a Closure or a class-name string.
     *
     * @param Closure|string $concrete The factory or class name to build.
     * @param array<string, mixed> $params Extra constructor parameters.
     *
     * @throws ContainerException On build failure.
     *
     * @return mixed The built value.
     */
    protected function build(Closure|string $concrete, array $params = []): mixed
    {
        if ($concrete instanceof Closure) {
            try {
                return $concrete($this, $params);
            } catch (Throwable $e) {
                throw new ContainerException(
                    "Error while invoking factory closure: {$e->getMessage()}",
                    (int) $e->getCode(),
                    $e
                );
            }
        }

        return $this->buildClass($concrete, $params);
    }

    /**
     * Instantiate a class, auto-wiring its constructor dependencies via Reflection.
     *
     * @param string $class  Fully-qualified class name.
     * @param array<string, mixed> $params Optional parameter overrides keyed by parameter name.
     *
     * @throws ContainerException If the class cannot be instantiated or a dependency is unresolvable.
     *
     * @return object The constructed instance.
     */
    protected function buildClass(string $class, array $params = []): object
    {
        try {
            $reflector = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new ContainerException(
                "Target class [{$class}] does not exist.",
                0,
                $e
            );
        }

        if (!$reflector->isInstantiable()) {
            throw new ContainerException(
                "Target [{$class}] is not instantiable (it may be abstract or an interface)."
            );
        }

        $constructor = $reflector->getConstructor();

        // No constructor — just instantiate directly.
        if ($constructor === null) {
            return new $class();
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters(), $params);

        try {
            return $reflector->newInstanceArgs($dependencies);
        } catch (ReflectionException $e) {
            throw new ContainerException(
                "Failed to instantiate [{$class}]: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Resolve an array of ReflectionParameter objects into concrete values.
     *
     * For each parameter the resolution priority is:
     *   1. Caller-provided override in $params (by parameter name).
     *   2. Type-hinted class — recursively resolved via make().
     *   3. Default value declared on the parameter.
     *   4. ContainerException (unresolvable).
     *
     * @param ReflectionParameter[]    $parameters The constructor parameters to resolve.
     * @param array<string, mixed>     $params     Optional named overrides.
     *
     * @throws ContainerException If a required dependency cannot be resolved.
     *
     * @return array<int, mixed> Ordered list of resolved values.
     */
    protected function resolveDependencies(array $parameters, array $params = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            // 1. Caller supplied an explicit override.
            if (array_key_exists($name, $params)) {
                $dependencies[] = $params[$name];
                continue;
            }

            // 2. Try to resolve from type hint.
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                try {
                    $dependencies[] = $this->make($type->getName());
                    continue;
                } catch (NotFoundException $e) {
                    // Fall through to default / exception handling below.
                }
            }

            // 3. Use the declared default value.
            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            // 4. Nullable with no default — pass null.
            if ($parameter->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            throw new ContainerException(
                "Unresolvable dependency [{$name}] in class "
                . ($parameter->getDeclaringClass()?->getName() ?? 'unknown')
                . ". Cannot auto-wire a primitive with no default value."
            );
        }

        return $dependencies;
    }
}
