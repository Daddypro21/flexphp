<?php

declare(strict_types=1);

namespace Tests\Unit;

use FlexPHP\Core\Container;
use Psr\Container\NotFoundExceptionInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for the FlexPHP DI Container.
 *
 * Covers: basic bind/get, singleton lifecycle, auto-wiring via make(),
 * has() presence checks, and NotFoundException for unknown identifiers.
 */
class ContainerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Fixtures / helpers
    // -------------------------------------------------------------------------

    /**
     * Returns a fresh Container instance for each test.
     */
    private function makeContainer(): Container
    {
        return new Container();
    }

    // -------------------------------------------------------------------------
    // bind() / get()
    // -------------------------------------------------------------------------

    #[Test]
    public function bindAndGet(): void
    {
        $container = $this->makeContainer();

        $container->bind('greeting', fn() => 'Hello, FlexPHP!');

        $this->assertSame('Hello, FlexPHP!', $container->get('greeting'));
    }

    #[Test]
    public function bindReturnsNewInstanceOnEveryGet(): void
    {
        $container = $this->makeContainer();

        $container->bind(StubService::class, fn() => new StubService());

        $a = $container->get(StubService::class);
        $b = $container->get(StubService::class);

        $this->assertNotSame($a, $b, 'Non-singleton bindings must return a new instance each time.');
    }

    // -------------------------------------------------------------------------
    // singleton()
    // -------------------------------------------------------------------------

    #[Test]
    public function singletonReturnsSameInstance(): void
    {
        $container = $this->makeContainer();

        $container->singleton(StubService::class, fn() => new StubService());

        $first  = $container->get(StubService::class);
        $second = $container->get(StubService::class);

        $this->assertSame($first, $second, 'Singleton must return the identical object on every resolution.');
    }

    #[Test]
    public function singletonFactoryIsCalledOnlyOnce(): void
    {
        $container = $this->makeContainer();
        $callCount = 0;

        $container->singleton('counter', function () use (&$callCount) {
            $callCount++;
            return new \stdClass();
        });

        $container->get('counter');
        $container->get('counter');
        $container->get('counter');

        $this->assertSame(1, $callCount, 'The singleton factory must be invoked exactly once.');
    }

    // -------------------------------------------------------------------------
    // make() — auto-wiring
    // -------------------------------------------------------------------------

    #[Test]
    public function makeInstantiatesClassWithoutBinding(): void
    {
        $container = $this->makeContainer();

        $instance = $container->make(StubService::class);

        $this->assertInstanceOf(StubService::class, $instance);
    }

    #[Test]
    public function makeAutoWiresDependencies(): void
    {
        $container = $this->makeContainer();

        // StubConsumer depends on StubService; the container should resolve it automatically.
        $instance = $container->make(StubConsumer::class);

        $this->assertInstanceOf(StubConsumer::class, $instance);
        $this->assertInstanceOf(StubService::class, $instance->service);
    }

    // -------------------------------------------------------------------------
    // has()
    // -------------------------------------------------------------------------

    #[Test]
    public function hasReturnsTrueForBoundId(): void
    {
        $container = $this->makeContainer();
        $container->bind('my_service', fn() => new \stdClass());

        $this->assertTrue($container->has('my_service'));
    }

    #[Test]
    public function hasReturnsFalseForUnboundId(): void
    {
        $container = $this->makeContainer();

        $this->assertFalse($container->has('unknown_service'));
    }

    // -------------------------------------------------------------------------
    // NotFoundException
    // -------------------------------------------------------------------------

    #[Test]
    public function getThrowsNotFoundExceptionForUnknownId(): void
    {
        $container = $this->makeContainer();

        $this->expectException(NotFoundExceptionInterface::class);

        $container->get('this_does_not_exist');
    }

    #[Test]
    public function getThrowsNotFoundExceptionForUnboundConcreteClass(): void
    {
        $container = $this->makeContainer();

        // A class with a non-resolvable scalar constructor argument should
        // either throw NotFoundException or a ContainerException.
        $this->expectException(\Psr\Container\ContainerExceptionInterface::class);

        $container->get(StubUnresolvable::class);
    }

    // -------------------------------------------------------------------------
    // Bind with concrete string
    // -------------------------------------------------------------------------

    #[Test]
    public function bindWithConcreteClassString(): void
    {
        $container = $this->makeContainer();

        // Bind interface → concrete class name string.
        $container->bind(StubInterface::class, StubConcreteImpl::class);

        $result = $container->get(StubInterface::class);

        $this->assertInstanceOf(StubConcreteImpl::class, $result);
    }
}

// =============================================================================
// Stub classes used only within this test file
// =============================================================================

/** Simple stub with no dependencies. */
class StubService
{
    public string $value = 'stub';
}

/** Stub that depends on StubService (used to verify auto-wiring). */
class StubConsumer
{
    public function __construct(public readonly StubService $service)
    {
    }
}

/** Stub with a required scalar — cannot be auto-wired. */
class StubUnresolvable
{
    public function __construct(private readonly string $requiredScalar)
    {
    }
}

/** Minimal interface for bind-to-concrete tests. */
interface StubInterface
{
    public function greet(): string;
}

/** Concrete implementation of StubInterface. */
class StubConcreteImpl implements StubInterface
{
    public function greet(): string
    {
        return 'Hello from concrete!';
    }
}
